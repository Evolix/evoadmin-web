<?php
namespace lib;

/**
 * LetsEncrypt
 */
class LetsEncrypt
{
    const HTTP_OK = 200;
    const HTTP_CHALLENGE_URL = '/.well-known/acme-challenge/';

    /**
     * perform a cURL call on the remote resource
     * the cURL call follows redirections and pushes the last valid URL to an array
     * @param  Array  $domains list of domains
     * @return Array  $checked_domains list of checked domains
     */
    public function checkRemoteResourceAvailability($domains)
    {
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
            curl_setopt($curl_handles[$key], CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP, CURLPROTO_HTTPS);
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
                // retrieve the FQDN
                $returned_http_url = str_replace(self::HTTP_CHALLENGE_URL, '.', $returned_http_url);
                $returned_http_url = preg_replace('#^https?://#', '', $returned_http_url);

                array_push($checked_domains, $returned_http_url);
            }
            curl_multi_remove_handle($curl_multi, $curl_handle);
        }
        curl_multi_close($curl_multi);

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
}
