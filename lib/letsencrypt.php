<?php
namespace lib;

/**
 * LetsEncrypt
 */
class LetsEncrypt
{
    const HTTP_OK = 200;
    const HTTP_CHALLENGE_URL = '/.well-known/acme-challenge';

    /**
     * create the file used to test the HTTP challenge
     */
    private function createFileHttpChallenge()
    {
        $cmd = 'web-add.sh manage-http-challenge-file create';
        sudoexec($cmd, $data_output, $exec_return);
    }

    /**
     * delete the file used to test the HTTP challenge
     */
    private function deleteFileHttpChallenge()
    {
        $cmd = 'web-add.sh manage-http-challenge-file delete';
        sudoexec($cmd, $data_output, $exec_return);
    }

    /**
     * perform a cURL call on the remote resource
     * the cURL call follows redirections and pushes the last valid URL to an array
     * @param  Array  $domains list of domains
     * @return Array  $checked_domains list of checked domains
     */
    public function checkRemoteResourceAvailability($domains)
    {
        $this->createFileHttpChallenge();

        $curl_multi = curl_multi_init();
        $curl_handles = array();
        $checked_domains = array();

        foreach ($domains as $key => $domain) {
            $curl_handles[$key] = curl_init($domain . self::HTTP_CHALLENGE_URL);

            // setting cURL options
            curl_setopt($curl_handles[$key], CURLOPT_TIMEOUT, 3);
            curl_setopt($curl_handles[$key], CURLOPT_HEADER, true);
            curl_setopt($curl_handles[$key], CURLOPT_NOBODY, true);
            curl_setopt($curl_handles[$key], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl_handles[$key], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl_handles[$key], CURLOPT_MAXREDIRS, 3);
            curl_setopt($curl_handles[$key], CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($curl_handles[$key], CURLOPT_RETURNTRANSFER, true);

            curl_multi_add_handle($curl_multi, $curl_handles[$key]);
        }

        do {
            curl_multi_exec($curl_multi, $active);
        } while ($active);

        foreach ($curl_handles as $curl_handle) {
            $returned_http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
            $returned_http_url = curl_getinfo($curl_handle, CURLINFO_EFFECTIVE_URL);

            if ($returned_http_code === self::HTTP_OK && strpos($returned_http_url, self::HTTP_CHALLENGE_URL)) {
                $returned_http_url = str_replace(self::HTTP_CHALLENGE_URL, '', $returned_http_url);
                $returned_http_url = preg_replace('#^https?://#', '', $returned_http_url);

                array_push($checked_domains, $returned_http_url);
            }
            curl_multi_remove_handle($curl_multi, $curl_handle);
        }
        curl_multi_close($curl_multi);

        $this->deleteFileHttpChallenge();

        return $checked_domains;
    }

    /**
     * Query the corresponding IP for each domain
     * @param  Array $domains list of HTTP checked domains
     * @return Array $valid_dns_domains list of valid domains
     */
    public function checkDNSValidity($domains)
    {
        $valid_dns_domains = array();

        foreach ($domains as $domain) {
            //FQDN syntax
            $domain .= '.';
            $dns_record_ipv4 = dns_get_record($domain, DNS_A);
            $dns_record_ipv6 = dns_get_record($domain, DNS_AAAA);

            if ($dns_record_ipv4 || $dns_record_ipv6) {
                // remove the last dot added for the FQDN syntax
                $domain = rtrim($domain, '.');
                array_push($valid_dns_domains, $domain);
            }
        }

        return $valid_dns_domains;
    }

    /**
     * check the presence of make-csr and evoacme binaries
     * @return boolean
     */
    public function isEvoacmeInstalled()
    {
        $output_make_csr = shell_exec("which make-csr");
        $output_evoacme = shell_exec("which evoacme");

        if (empty($output_make_csr) || empty($output_evoacme)) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve the SSL certificate from the URL
     * @param  string $domain
     * @return Array|false $cont list of parameters of the certificate, or false
     */
    public function getCertificate($domain)
    {
        $stream = stream_context_create(array("ssl" => array("capture_peer_cert" => true)));
        $read = fopen("https://" . $domain , "rb", false, $stream);
        $cont = stream_context_get_params($read);

        return $cont;
    }

    /**
     * Parse the certificat arguments and extract data
     * @param  Array $certificateParameters certificat arguments
     * @return Array $infosCert contains only the issuer, domains and expiration date
     */
    public function parseCertificate($certificateParameters)
    {
        $infosCert = array();
        $parsedParameters = openssl_x509_parse($certificateParameters["options"]["ssl"]["peer_certificate"]);
        $issuer = $parsedParameters["issuer"]["O"];
        $includedDomains = $parsedParameters["extensions"]["subjectAltName"];
        $validUntil = $parsedParameters["validTo_time_t"];

        array_push($infosCert, $issuer);
        array_push($infosCert, $includedDomains);
        array_push($infosCert, $validUntil);

        return $infosCert;
    }

    /**
     * Check wether the certificat is issued by Let's Encrypt or not
     * @param  string  $issuer name of the certificat issuer
     * @return boolean
     */
    public function isCertIssuedByLetsEncrypt($issuer)
    {
        return ($issuer === "Let's Encrypt") ? true : false;
    }

    /**
     * Check wether the certificat is valid or not
     * @param  string  $timestampCertValidUntil certificat expiration date in timestamp
     * @return boolean
     */
    public function isCertValid($timestampCertValidUntil)
    {
        $currentDate = time();

        return ($timestampCertValidUntil > $currentDate) ? true : false;
    }

    public function isDomainIncludedInCert($domainRequested, $san)
    {
        $san = preg_replace('/DNS:| DNS:/', '', $san);
        $sanArray = explode(',', $san);

        return (in_array($domainRequested, $sanArray)) ? true : false;
    }
}
