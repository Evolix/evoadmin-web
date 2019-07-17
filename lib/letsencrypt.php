<?php
namespace lib;

/**
 * LetsEncrypt
 */
class LetsEncrypt
{
    const HTTP_OK = 200;
    const HTTP_CHALLENGE_URL = '/.well-known/acme-challenge/testfile';

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
     * generate a CSR
     * @param  string $vhost
     * @param  Array $domains
     * @return boolean
     */
    public function makeCsr($vhost, $domains)
    {
        $domains = implode(' ', $domains);
        $cmd = 'web-add.sh generate-csr ' . $vhost . ' ' . "$domains";

        sudoexec($cmd, $data_output, $exec_return);

        if ($exec_return == 0) {
            return true;
        }

        return false;
    }

    /**
     * Generate a SSL certificate
     * @param  string  $vhost
     * @param  boolean $test  generate in TEST mode or not
     * @return boolean
     */
    public function generateSSLCertificate($vhost, $test = true)
    {
        $cmd = 'web-add.sh generate-ssl-certificate ' . $vhost . ' ' . ($test ? "true" : "false");

        sudoexec($cmd, $data_output, $exec_return);

        if ($exec_return == 0) {
            return true;
        }

        return false;
    }

    /**
     * perform a cURL call on the remote resource
     * the cURL call follows redirections
     * @param  Array  $domains list of domains
     * @return boolean
     */
    public function checkRemoteResourceAvailability($domain)
    {
        $this->createFileHttpChallenge();

        $curl_handler = curl_init();

        // setting cURL options
        curl_setopt($curl_handler, CURLOPT_URL, $domain . self::HTTP_CHALLENGE_URL);
        curl_setopt($curl_handler, CURLOPT_TIMEOUT, 3);
        curl_setopt($curl_handler, CURLOPT_HEADER, true);
        curl_setopt($curl_handler, CURLOPT_NOBODY, true);
        curl_setopt($curl_handler, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_handler, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl_handler, CURLOPT_MAXREDIRS, 3);
        curl_setopt($curl_handler, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($curl_handler, CURLOPT_RETURNTRANSFER, true);

        curl_exec($curl_handler);

        $returned_http_code = curl_getinfo($curl_handler, CURLINFO_HTTP_CODE);
        $returned_http_url = curl_getinfo($curl_handler, CURLINFO_EFFECTIVE_URL);

        $this->deleteFileHttpChallenge();

        if ($returned_http_code === self::HTTP_OK && strpos($returned_http_url, self::HTTP_CHALLENGE_URL)) {
            $returned_http_url = str_replace(self::HTTP_CHALLENGE_URL, '', $returned_http_url);
            $returned_http_url = preg_replace('#^https?://#', '', $returned_http_url);

            return true;
        }

        return false;
    }

    /**
     * Query the corresponding IP for each domain
     * @param  Array $domains list of HTTP checked domains
     * @return Array $valid_dns_domains list of valid domains
     */
    public function checkDNSValidity($domains)
    {
        $valid_dns_domains = array();
        $serverIP = exec("ip route get 1 | sed -n 's/^.*src \([0-9.]*\) .*$/\\1/p'");

        foreach ($domains as $domain) {
            //FQDN syntax
            $domain .= '.';
            $dns_record_ipv4 = dns_get_record($domain, DNS_A);
            $dns_record_ipv6 = dns_get_record($domain, DNS_AAAA);

            if ($dns_record_ipv4[0]['ip'] === $serverIP || $dns_record_ipv6[0]['ip'] === $serverIP) {
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
        $read = stream_socket_client("ssl://" . $domain . ":443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $stream);
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

        $infosCert["issuer"] = $issuer;
        $infosCert["includedDomains"] = $includedDomains;
        $infosCert["validUntil"] = $validUntil;

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
