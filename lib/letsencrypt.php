<?php

require "Net/DNS2.php";

/**
 * LetsEncrypt
 */
class LetsEncrypt
{
    const HTTP_OK = 200;
    const HTTP_CHALLENGE_URL = '/.well-known/acme-challenge/testfile';

    /**
     * Create the file used to test the HTTP challenge
     * @return void
     */
    private function createFileHttpChallenge()
    {
        $cmd = 'web-add.sh manage-http-challenge-file create';
        sudoexec($cmd, $data_output, $exec_return);
    }

    /**
     * Delete the file used to test the HTTP challenge
     * @return void
     */
    private function deleteFileHttpChallenge()
    {
        $cmd = 'web-add.sh manage-http-challenge-file delete';
        sudoexec($cmd, $data_output, $exec_return);
    }

    /**
     * Generate a CSR
     * @param  string $vhost
     * @param  string[] $domains
     * @return boolean
     */
    public function makeCsr($vhost, $domains, $test = True)
    {
        $domains = implode(' ', $domains);
        if ($test) {
            $cmd = 'web-add.sh generate-csr --test ' . $vhost . ' ' . "$domains";
        } else {
            $cmd = 'web-add.sh generate-csr ' . $vhost . ' ' . "$domains";
        }
        sudoexec($cmd, $data_output, $exec_return);

        if ($exec_return == 0) {
            return True;
        }

        print("<br/>" . $cmd . "<br/>");
        print_r($data_output); print("<br/>");
        return False;
    }

    /**
     * Generate a SSL certificate
     * @param  string  $vhost
     * @param  boolean $test  generate in TEST mode (True) or not (False)
     * @return list of arrays $failed_domains
     */
    public function generateSSLCertificate($vhost, $test = True)
    {
        $cmd = 'web-add.sh generate-ssl-certificate ' . $vhost . ' ' . ($test ? "true" : "false");

        sudoexec($cmd, $output, $exec_return);

        $failed = [];

        # Parse Certbot output to get challenge failure reasons
        if ($exec_return != 0) {

            $current_domain = '';
            $current_fail_type = '';
            $current_detail = '';

            $is_challenge_failed = False;
            $is_evoacme_failed = False;
            $is_rate_limited = False;
            foreach ($output as $line) {

                $line = trim($line);

                if (str_starts_with($line, 'Some challenges have failed')) {
                    $is_challenge_failed = True;
                    continue;
                }

                if (str_starts_with($line, 'evoacme: Certbot has exited with a non-zero exit code')) {
                    $is_evoacme_failed = True;
                    continue;
                }

                # Rate-limiting make certbot version < 2.3 fail with an AttributeError
                if (str_contains($line, 'AttributeError:') or str_contains($line, 'too many')) {
                    $is_rate_limited = True;
                    continue;
                }

                if (str_starts_with($line, 'Domain: ')) {
                    $current_domain = explode(' ', $line)[1];
                    continue;
                }

                if (str_starts_with($line, 'Type: ')) {
                    $split = explode(' ', $line);
                    $current_fail_type = implode(' ', array_slice($split, 1));
                    continue;
                }

                if (str_starts_with($line, 'Detail: ')) {
                    $split = explode(' ', $line);
                    $current_detail = implode(' ', array_slice($split, 1));

                    $failed[$current_domain] = array('type' => $current_fail_type, 'detail' => $current_detail);

                    $current_domain = '';
                    $current_fail_type = '';
                    $current_detail = '';
                    continue;
                }
            }
        }
        if ($is_challenge_failed) {
            return $failed;
        }
        else if ($is_rate_limited) {
            # If Evoacme failed but there was no challenge error
            if ($test) {
                throw new Exception('Le test des domaines a échoué car la limite de Let\'s Encrypt a été atteinte. Merci de réessayer plus tard.');
            } else {
                throw new Exception('La génération de certificat a échoué car la limite de Let\'s Encrypt a été atteinte. Merci de réessayer plus tard.');
            }
        } else if ($is_evoacme_failed) {
            # If Evoacme failed but there was no challenge error, neither rate limit
            if ($test) {
                throw new Exception('Le test des domaines a échoué pour une raison inconnue. Merci de contacter un administrateur.');
            } else {
                throw new Exception('La génération de certificat a échoué pour une raison inconnue. Merci de contacter un administrateur.');
            }
        } else {
            return [];
        }

    }

    /**
     * perform a cURL call on the remote resource
     * the cURL call follows redirections
     * @param  array  $domains list of domains
     * @return boolean
     */
    public function checkRemoteResourceAvailability($domain)
    {
        $this->createFileHttpChallenge();

        $curl_handler = curl_init();

        // setting cURL options
        curl_setopt($curl_handler, CURLOPT_URL, $domain . self::HTTP_CHALLENGE_URL);
        curl_setopt($curl_handler, CURLOPT_TIMEOUT, 3);
        curl_setopt($curl_handler, CURLOPT_HEADER, True);
        curl_setopt($curl_handler, CURLOPT_NOBODY, True);
        curl_setopt($curl_handler, CURLOPT_SSL_VERIFYPEER, False);
        curl_setopt($curl_handler, CURLOPT_FOLLOWLOCATION, True);
        curl_setopt($curl_handler, CURLOPT_MAXREDIRS, 3);
        curl_setopt($curl_handler, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($curl_handler, CURLOPT_RETURNTRANSFER, True);

        curl_exec($curl_handler);

        $returned_http_code = curl_getinfo($curl_handler, CURLINFO_HTTP_CODE);
        $returned_http_url = curl_getinfo($curl_handler, CURLINFO_EFFECTIVE_URL);

        $this->deleteFileHttpChallenge();

        if ($returned_http_code === self::HTTP_OK && strpos($returned_http_url, self::HTTP_CHALLENGE_URL)) {
            $returned_http_url = str_replace(self::HTTP_CHALLENGE_URL, '', $returned_http_url);
            $returned_http_url = preg_replace('#^https?://#', '', $returned_http_url);

            return True;
        }

        return False;
    }

    /**
     * Query the corresponding IP for each domain
     * @param  string[] $domains list of HTTP checked domains
     * @return array $valid_dns_domains list of valid domains
     */
    public function checkDNSValidity($domains)
    {
        $valid_dns_domains = [];
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
     * Check the presence of make-csr and evoacme binaries
     * @return boolean
     */
    public function isEvoacmeInstalled()
    {
        $output_make_csr = shell_exec("which make-csr");
        $output_evoacme = shell_exec("which evoacme");

        if (empty($output_make_csr) || empty($output_evoacme)) {
            return False;
        }

        return True;
    }

    /**
     * Return the root domain of $domain
     * @param string $domain
     * @return $root_domain
     */
    public function getRootDomain($domain)
    {
        $splitted_domain = explode('.', $domain);
        $root_domain = implode('.', array_slice($splitted_domain, -2));
        return $root_domain;
    }

    /**
     * Return the autoritative DNS IPs of domain
     * @param string $domain
     * @return array|False the NS IPs, or False
     */
    public function getDomainAuthoritativeDNS($domain)
    {
        $root_domain = $this->getRootDomain($domain);

        $soa_records = dns_get_record($root_domain, DNS_SOA);
        if ($soa_records == False) {
            return False;
        }
        $auth_DNS_ips = [];
        foreach ($soa_records as $soa_record) {
            if (isset($soa_record["mname"])) {
                $primary_DNS = $soa_record["mname"];
                $ns_a_records = dns_get_record($primary_DNS, DNS_A);
                foreach ($ns_a_records as $ns_a_record) {
                    if (isset($ns_a_record["ip"])) {
                        $auth_DNS_ips[] = $ns_a_record["ip"];
                    }
                }
            }
        }
        return $auth_DNS_ips;
    }

    /**
     * Return the A records from the autoritative DNS.
     * Requires package php-net-dns2
     * @param string $domain
     * @return array|False the IPs, or False
     */
    public function getIPsFromAuthoritativeDNS($domain)
    {
        $auth_DNS_ips = $this->getDomainAuthoritativeDNS($domain);
        if (is_bool($auth_DNS_ips)) {
            return False;
        }

        try {
            $resolver = new Net_DNS2_Resolver(['nameservers' => $auth_DNS_ips, 'timeout' => 2]);
            $a_records = $resolver->query($domain, 'A');
            $aaaa_records = $resolver->query($domain, 'AAAA');
            $answers = array_merge($a_records->answer, $aaaa_records->answer);
        } catch (Net_DNS2_Exception $e) {
            return False;
        }

        $IPs = [];
        foreach ($answers as $record) {
            if ($record->type == 'A' || $record->type == 'AAAA') {
                $IP = $record->address;
                if (! $this->isIPInArray($IP, $IPs)) {
                    $IPs[] = strval($record->address);
                }
            } else if ($record->type == 'CNAME') {
                $recursive_IPs = $this->getIPsFromAuthoritativeDNS($record->cname);
                if (! is_bool($recursive_IPs)) {
                    foreach ($recursive_IPs as $IP) {
                        if (! $this->isIPInArray($IP, $IPs)) {
                            $IPs[] = $IP;
                        }
                    }
                }
            }
        }

        return $IPs;
    }

    /**
     * Compare IPs in their 32bit or 128bit binary format.
     */
    public function areIPsEqual($IP1, $IP2)
    {
        return inet_pton($IP1) == inet_pton($IP2);
    }

    /**
     *
     */
    public function isIPInArray($IP, $IPArray)
    {
        foreach ($IPArray as $anIP) {
            if ($this->areIPsEqual($anIP, $IP)) {
                return True;
            }
        }
        return False;
    }

    /**
     * Retrieve the SSL certificate from the URL
     * @param string $domain
     * @return array|False the certificate, or False
     */
    public function getCertificate($domain)
    {
        $stream = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => True,
                "peer_name" => $domain,
                "verify_peer" => False,
            ]
        ]);
        #$read = stream_socket_client("ssl://" . $domain . ":443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $stream);
        $read = stream_socket_client("ssl://127.0.0.1:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $stream);
        if ($read === False) {
            return False;
        }
        $stream_params = stream_context_get_params($read);

        return $stream_params["options"]["ssl"]["peer_certificate"];
    }

    /**
     * Return the certificate fingerprint / hash
     * @param  array $certificate certificate argument
     * @return string $fingerprint
     */
    public function getCertificateFingerprint($certificate)
    {
        return openssl_x509_fingerprint($certificate);
    }

    /**
     * Parse the certificate argument and extract data
     * @param  array $certificate certificate argument
     * @return array $infosCert contains only the issuer, domains and expiration date
     */
    public function parseCertificate($certificate)
    {
        $infosCert = [];
        $parsedParameters = openssl_x509_parse($certificate);
        $issuer = $parsedParameters["issuer"]["O"];
        $includedDomains = $parsedParameters["extensions"]["subjectAltName"];
        $validUntil = $parsedParameters["validTo_time_t"];

        $infosCert["issuer"] = $issuer;
        $infosCert["isLetsEncrypt"] = $issuer == "Let's Encrypt";
        $infosCert["isSelfSigned"] = $parsedParameters["issuer"] == $parsedParameters["subject"];
        $infosCert["includedDomains"] = $includedDomains;
        $infosCert["validUntil"] = $validUntil;

        return $infosCert;
    }

    /**
     * Check wether the certificat is issued by Let's Encrypt or not
     * @param  string  $issuer name of the certificat issuer
     * @return boolean
     */
    #public function isCertIssuedByLetsEncrypt($issuer)
    #{
    #    return ($issuer === "Let's Encrypt") ? True : False;
    #}

    /**
     * Check wether the certificat is valid or not
     * @param  string  $timestampCertValidUntil certificat expiration date in timestamp
     * @return boolean
     */
    public function isCertValid($timestampCertValidUntil)
    {
        $currentDate = time();

        return ($timestampCertValidUntil > $currentDate) ? True : False;
    }

    /**
     * Check if the requested domain is included in the certificate
     * @param string $domainRequested
     * @param string[]|string $san
     * @return bool
     */
    public function isDomainIncludedInCert($domainRequested, $san)
    {
        $san = preg_replace('/DNS:| DNS:/', '', $san);
        $sanArray = explode(',', $san);

        return (in_array($domainRequested, $sanArray)) ? True : False;
    }

    /**
     * Return an array containing the domains in the SAN.
     * @param  array $certificateInfos certificate infos as returned by parseCertificate()
     * @return array string[] $domains
     */
    public function getCertificateDomains($certificateInfos)
    {
        $cleaned_san = preg_replace('/DNS:| DNS:/', '', $certificateInfos['includedDomains']);
        return explode(',', $cleaned_san);
    }

    /**
     * Return an array containing the IPs v4 and v6 of the host.
     * @return array string[] $ips
     */
    public function getHostIPs() {
        $ifaces = net_get_interfaces();
        $ips = [];
        foreach ($ifaces as $iface => $iface_attrs) {
            foreach ($iface_attrs['unicast'] as $attr) {
                if ($attr['family'] == AF_INET || $attr['family'] == AF_INET6) {
                    $ips[] = $attr['address'];
                }
            }
        }
        return $ips;
    }

}
