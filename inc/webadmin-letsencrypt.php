<?php

require_once EVOADMIN_BASE . '../lib/letsencrypt.php';

use lib\LetsEncrypt as letsencryt;

// store domain and aliases in session
if (!isset($_SESSION['lestencrypt-domains']) || empty($_SESSION['letsencrypt-domains'])) {
    $domain = $params[1];
    $cmd = 'web-add.sh list-vhost ' . $domain;

    sudoexec($cmd, $data_output, $exec_return);

    $data_split = explode(':', $data_output[0]);
    $aliases = explode(',', $data_split[3]);

    $domains = array();

    // store domain and aliases
    array_push($domains, $data_split[2]);
    foreach ($aliases as $alias) {
        array_push($domains, $alias);
    }

    $_SESSION['letsencrypt-domains'] = array_filter($domains);
}

include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';

$letsencrypt = new letsencryt();
$errorMessage = '';
$warningMessage = '';

// it's an array if we want to display multiple messages in the future
$messages = array();

if (isset($_POST['submit'])) {
    while (true) {
        // check HTTP
        $isRemoteResourceAvailable = $letsencrypt->checkRemoteResourceAvailability($_SESSION['letsencrypt-domains'][0]);

        if (!$isRemoteResourceAvailable) {

            $errorMessage = "Erreur : Le challenge HTTP a échoué.<br>
                              Merci de vérifier que le dossier <code>/.well-known/evoacme-challenge/</code> est accessible.";

            array_push($messages, ["type" => "error", "content" => $errorMessage]);
            
            break;
        }

        // check DNS
        $valid_domains = $letsencrypt->checkDNSValidity($_SESSION['letsencrypt-domains']);

        $failed_domains = array_diff($_SESSION['letsencrypt-domains'], $valid_domains);
        if (!empty($failed_domains)) {

            $errorMessage = "Erreur : La vérification DNS a échoué.<br>
                              Merci de vérifier les enregistrements de type A et AAAA pour les domaine(s) suivant(s) :";

            array_push($messages, ["type" => "error", "content" => $errorMessage]);

            break;
        }

        // make csr
        $isCsrGenerated = $letsencrypt->makeCsr($params[1], $_SESSION['letsencrypt-domains']);

        if (!$isCsrGenerated) {

            $errorMessage = "Erreur : La génération de demande de certificat a échoué.<br>
                              Merci de contacter un administrateur pour continuer.";

            array_push($messages, ["type" => "error", "content" => $errorMessage]);

            break;
        }

        // evoacme TEST
        $testGenerateCert = $letsencrypt->generateSSLCertificate($params[1]);

        if (!$testGenerateCert) {

            $errorMessage = "Erreur : La génération de certificat en mode TEST a échoué.<br>
                              Merci de contacter un administrateur pour continuer.";

            array_push($messages, ["type" => "error", "content" => $errorMessage]);

            break;
        }

        // evoacme
        $generateCert = $letsencrypt->generateSSLCertificate($params[1], false);

        if (!$generateCert) {

            $errorMessage = "Erreur : La génération de certificat a échoué.<br>
                              Merci de contacter un administrateur pour continuer.";

            array_push($messages, ["type" => "error", "content" => $errorMessage]);

            break;
        }

        break;
    }
} else {
    $validUntil = '';

    while(true) {
        // check domains list
        if (empty($_SESSION['letsencrypt-domains'])) {

            $errorMessage = "Erreur : la liste des domaines est vide.";

            array_push($messages, ["type" => "error", "content" => $errorMessage]);

            break;
        }

        // check if evoacme is installed
        $binaries_installed = $letsencrypt->isEvoacmeInstalled();
        if (!$binaries_installed) {

            $errorMessage = "Erreur : les binaires Evoacme ne sont pas installés.
                              Veuillez contacter un administrateur.";

            array_push($messages, ["type" => "error", "content" => $errorMessage]);

            break;
        }

        // Check existing SSL certificate
        $domainsIncluded = array();
        foreach ($_SESSION['letsencrypt-domains'] as $domain) {

            $isDomainReal = $letsencrypt->isDomainReal($domain);

            if ($isDomainReal === false) {
                $errorMessage = "Erreur : le domaine " . $domain . " n'existe pas. Veuillez vérifier les enregistrements DNS.";

                array_push($messages, ["type" => "error", "content" => $errorMessage]);
                break 2;
            }

            $existingSSLCertificate = $letsencrypt->getCertificate($domain);
            // if no certificate is present (false returned) for this domain, go to the next domain
            if (is_bool($existingSSLCertificate)) {
                continue;
            }
            $parsedCertificate = $letsencrypt->parseCertificate($existingSSLCertificate);

            // check if LE is the certificate issuer
            $isIssuerValid = $letsencrypt->isCertIssuedByLetsEncrypt($parsedCertificate["issuer"]);
            if (!$isIssuerValid) {

                $errorMessage = "Erreur : le certificat existant n'est pas géré par Let's Encrypt.";

                array_push($messages, ["type" => "error", "content" => $errorMessage]);

                break 2; // break the foreach and the while
            }

            // check wether the certificate is valid or expired

            $isCertValid = $letsencrypt->isCertValid($parsedCertificate["validUntil"]);
            if (!$isCertValid && !isset($_POST['force_renew'])) {

                $warningMessage = "Attention : le certificat existant n'est plus valide.
                                 Souhaitez-vous le renouveller ?";

                array_push($messages, ["type" => "warning", "content" => $warningMessage]);

                break 2;
            } else {
                $validUntil = date("d/m/Y", $parsedCertificate["validUntil"]);
            }

            // check if the domain is already in the certificate
            $isDomainIncluded = $letsencrypt->isDomainIncludedInCert($domain, $parsedCertificate["includedDomains"]);
            if ($isDomainIncluded) {
                array_push($domainsIncluded, $domain);
                continue; // break only the current foreach iteration
            }
        }

        // contains all the domains included in the existing certificate
        if (!empty($domainsIncluded) && !isset($_POST['force_renew'])) {
            $domainsNotIncluded = array_diff($_SESSION['letsencrypt-domains'], $domainsIncluded);

            if (empty($domainsNotIncluded)) {

                $noticeMessage = "Le certificat existant couvre déjà tous les domaines jusqu'au " . $validUntil . ".";

                array_push($messages, ["type" => "notice", "content" => $noticeMessage]);

                break;
            }


            $warningMessage = "Attention : le certificat existant couvre déjà le(s) domaine(s) jusqu'au " . $validUntil . " :<br>";

            foreach ($domainsIncluded as $domainIncluded) {
                $warningMessage .= $domainIncluded . "<br>";
            }

            $warningMessage .= "<br><strong>En confirmant le renouvellement, vous allez ajouter :</strong><br>";

            foreach ($domainsNotIncluded as $domainNotIncluded) {
                $warningMessage .= $domainNotIncluded . "<br>";
            }

            array_push($messages, ["type" => "warning", "content" => $warningMessage]);
            break;
        }

        break;
    }


}

include_once EVOADMIN_BASE . '../tpl/webadmin-letsencrypt.tpl.php';
include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
