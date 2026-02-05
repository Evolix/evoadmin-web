<?php print('<h2>Gestion des certificats pour le compte "' . $web_account . '"</h2>');

if (! empty($errorMessage)) {
    print('<span style="color:red;font-weight:bold;">' . $errorMessage . '</span>');
    print('<p>En cas de besoin, vous pouvez <a href="https://ssl.evolix.net/evogestion/evotickets/index.php?mod=add">ouvrir un ticket dans Evogestion</a></p>');
} else {
    ?>
    <h3>Certificat</h3>
    <table id="tab-list">
        <thead>
            <tr>
                <th>Signé par</th>
                <th>Expiration</th>
                <th>Renouvellement auto</th>
                <th>Valide</th>
            </tr>
        </thead>
        <tbody>
            <?php
            print('<tr>');
            if (! empty($parsed_cert)) {
                printf('<td>%s</td>', $cert_issuer);
                printf('<td>%s</td>', $cert_valid_until);
                printf('<td>%s</td>', $cert_is_letsencrypt ? 'Oui' : '<span style="color:darkorange;font-weight:bold;">Non</span>');
                printf('<td>%s</td>', $cert_is_valid ? 'Oui' : '<span style="color:red;font-weight:bold;">Non</span>');
            } else {
                print('<td colspan=4><span style="color:red;font-weight:bold;">Pas de certificat SSL</span></td>');
            }
            print('</tr>');
            if (isset($cert_gen_succeded) && $cert_gen_succeded == True) {
                print('<p style="color:green;font-weight:bold;">Votre nouveau certificat a bien été généré et est actif.</p>');
            }
            ?>
        </tbody>
    </table>
    <h3>Domaines</h3>
    <table id="tab-list">
        <thead>
            <tr>
                <th>Domaine</th>
                <th>Dans le vhost</th>
                <th>Dans le certificat</th>
                <?php if (isset($eligible_domains)) { print('<th>Test éligibilité</th>'); } ?>
                <th>IP(s)</th>
                <th>Commentaire</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $is_dns_ok = True; # for later warning
            foreach ($domains_details as $domain => $domain_details) {
                $domain_details = $domains_details[$domain];
                if (! $domain_details['is_dns_ok']) {
                    $is_dns_ok = False;
                }

                if (is_null($domain_details['is_in_vhost'])) {
                    $in_vhost_txt = '<span style="color:red;font-weight:bold;">?</span>';
                } else {
                    $in_vhost_txt = $domain_details['is_in_vhost'] ? 'Oui' : '<span style="color:darkorange;font-weight:bold;">Non</span>';
                }
                if (is_null($domain_details['is_in_cert'])) {
                    $in_cert_txt = '<span style="color:red;font-weight:bold;">?</span>';
                } else {
                    if (! $domain_details['is_in_vhost'] && $domain_details['is_in_cert']) {
                        $in_cert_txt = '<span style="color:darkorange;font-weight:bold;">Oui</span>';
                    } else {
                        $in_cert_txt = $domain_details['is_in_cert'] ? 'Oui' : '<span style="color:darkorange;font-weight:bold;">Non</span>';
                    }
                }

                if (isset($domain_details['is_eligible']) && $domain_details['is_eligible']) {
                    $eligible_txt = 'Oui';
                } else {
                    if (isset($domain_details['certbot_fail_msg'])) {
                        $eligible_txt = '<span style="color:darkorange;font-weight:bold;"><div class="tooltip">Non 💬<span class="tooltiptext">' . $domain_details['certbot_fail_msg'] . '</span></div></span>';
                    } else {
                        $eligible_txt = '<span style="color:darkorange;font-weight:bold;">Non</span>';
                    }
                }
                $ip_txt = implode("<br/>", $domain_details['domain_ips']);
                $comment_txt = $domain_details['is_dns_ok'] ? '' : '<span style="color:darkorange;font-weight:bold;">'.$domain_details['dns_msg'].'</span>';

                print('<tr>');
                printf('<td>%s</td>', $domain);
                printf('<td>%s</td>', $in_vhost_txt);
                printf('<td>%s</td>', $in_cert_txt);
                if (isset($eligible_domains)) {
                    printf('<td>%s</td>', $eligible_txt);
                }
                printf('<td>%s</td>', $ip_txt);
                printf('<td>%s</td>', $comment_txt);
                print('</tr>');
            }
            ?>
        </tbody>
    </table>

    <?php
    if (($cert_is_letsencrypt || $cert_is_self_signed) && (!$is_dns_ok || !$are_covered_domains_not_in_vhost)) {
        print('<p><b>Attention :</b> Le renouvellement automatique de votre certificat peut échouer,<br/>
        si les domaines qu\'il contient ne pointent <b>plus</b> sur le serveur (suppression domaine, load blalancer, CDN…)<br/>
        ou ne redirigent pas les requêtes de Let\'s Encrypt vers lui.<br/>');
    }
    ?>

    <h3>Actions</h3>

    <form name="form-test-domains" id="form-test-domains" action="" method="POST">
        <p>1. Tester les domaines éligibles pour un certificat Let's Encrypt (avec les modes dry-run et test) :</p>
        <?php
        $disabled_attr = '';
        if (! $allow_test_domains) {
            $disabled_attr = 'disabled="disabled"';
        }
        $js_str = "document.getElementById('loading-animation-test').style.display='inline-block'";
        print('<input type="submit" ' . $disabled_attr . ' name="submit-test-domains" onClick="'.$js_str.'" value="Tester les domaines"/>');
        ?>
        <span id=loading-animation-test></span>
    </form>

    <form name="form-generate-cert" id="form-generate-cert" action="" method="POST">
        <p>2. Générer un certificat Let's Encrypt pour les domaines éligibles :</p>
        <?php
        $disabled_attr = '';
        if (! $allow_new_cert) {
            $disabled_attr = 'disabled="disabled"';
        } else if ($are_covered_domains_not_eligible) {
            # Button enabled, but add warning if currently covered domains won't be covered by the new certificate.
            print('<p style="color:darkorange;font-weight:bold;">Attention, des domaines couverts par le certificat actuel et non éligibles (voir ci-dessus) ne seront pas intégrés dans le nouveau certificat.</p>');
        }

        if (isset($eligible_domains)) {
            $domains_str = '\n\n- ' . implode('\n- ', $eligible_domains) . '\n\n';
            $js_str = "var validation = confirm('Une demande de certificat va être faite aux serveurs de Let\'s Encrypt pour les domaines suivants :" . $domains_str . "Validez-vous cette action ?');
                       if (validation == true) { document.getElementById('loading-animation-generate').style.display='inline-block'; }
                       return validation";
            print('<input type="submit" ' . $disabled_attr . ' name="submit-generate-cert" value="Générer un certificat Let\'s Encrypt" style="margin-left:0px;" onClick="'.$js_str.'"/>');
            print("<span id=loading-animation-generate></span>");
        } else {
            print('<input type="submit" ' . $disabled_attr . ' name="submit-generate-cert" value="Générer un certificat Let\'s Encrypt" style="margin-left:0px;"/>');
        }
        if (! $allow_new_cert && ! empty($disallow_new_cert_msg)) {
            print('<p style="color:darkorange;font-weight:bold;">' . $disallow_new_cert_msg . '</p>');
        }
        if (! empty($parsed_cert) && ! $cert_is_letsencrypt && ! $cert_is_self_signed) {
            print("<p><span style='color:darkorange;font-weight:bold;'>Votre compte a été configuré pour utiliser des certificats à renouvellement manuel.</span><br/>
                Pour revenir à des certificats Let's Encrypt à renouvellement automatique,
                veuillez <a href='https://ssl.evolix.net/evogestion/evotickets/index.php?mod=add'>ouvrir un ticket</a>
                dans <a href='https://ssl.evolix.net'>Evogestion.</a></p>");
        }
        ?>
    </form>

    <?php
}
