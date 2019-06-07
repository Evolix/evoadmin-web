<h2>Gestion Let's Encrypt</h2>

<?php
if (isset($_POST['submit'])) {
    if (count($failed_domains_http) > 0) {
        echo '<h3 class="form-error">Erreur HTTP</h3>';

        echo '<p>';
        echo 'Le challenge HTTP a échoué pour le(s) domaine(s) ci-dessous.
              Merci de vérifier que le dossier <code>/.well-known/acme-challenge/</code> est accessible.';
        echo '</p>';

        echo '<p>';
        foreach ($failed_domains_http as $failed_domain) {
            echo $failed_domain . "<br>";
        }
        echo '</p>';
    } elseif (count($failed_domains_dns) > 0) {
        echo '<h3 class="form-error">Erreur DNS</h3>';

        echo '<p>';
        echo 'La vérification DNS a échoué pour les domaines ci-dessous.
              Merci de vérifier les enregistrements de type A et AAAA.';
        echo '</p>';

        foreach ($failed_domains_dns as $failed_domain) {
            echo $failed_domain . "<br>";
        }
    } else {
        echo "all checks succeeded";
    }
} else {
    echo "<p>Les domaines suivants seront intégrés au certificat : </p>";
    if (count($_SESSION['letsencrypt-domains']) > 0) {
        echo '<p>';
        foreach ($_SESSION['letsencrypt-domains'] as $domain) {
            echo $domain . '<br>';
        }
        echo '</p>';
        ?>
        <form name="form-confirm-delete-alias" id="form-confirm-delete-alias" action="" method="POST">
            <p><input type="submit" name="submit" value="Poursuivre l'installation du certificat" style="margin-left:0px;"></p>
        </form>
        <?php
    }
}
