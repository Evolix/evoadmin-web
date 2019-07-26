<h2>Gestion Let's Encrypt</h2>

<?php
if (isset($_POST['submit'])) {
    if (!empty($errorMessage)) {
        echo '<span class="form-error">' . $errorMessage . '</span>';

        if (count($failed_domains) > 0) {
            echo '<p>';
            foreach ($failed_domains as $failed_domain) {
                echo $failed_domain . "<br>";
            }
            echo '</p>';
        }
    } else {
        echo "Votre certificat SSL a bien été installé !";
    }
} else {
    if (!empty($errorMessage)) {
        echo '<span class="form-error">' . $errorMessage . '</span>';

        if (count($failed_domains) > 0) {
            echo '<p>';
            foreach ($failed_domains as $failed_domain) {
                echo $failed_domain . "<br>";
            }
            echo '</p>';
        }
    } elseif (!empty($warningMessage)) {
        echo '<span class="form-warning">' . $warningMessage . '</span>'; ?>
        <form name="form-confirm-renew-cert" id="form-confirm-renew-cert" action="" method="POST">
            <p>
                <input type="hidden" name="force_renew">
                <input type="submit" name="submit" value="Confirmer l'installation" style="margin-left:0px;">
            </p>
        </form>
        <?php
    } else {
        echo "<p>Les domaines suivants seront intégrés au certificat : </p>";
        if (count($_SESSION['letsencrypt-domains']) > 0) {
            echo '<p>';
            foreach ($_SESSION['letsencrypt-domains'] as $domain) {
                echo $domain . '<br>';
            }
            echo '</p>';
            ?>
            <form name="form-confirm-install-cert" id="form-confirm-install-cert" action="" method="POST">
                <p><input type="submit" name="submit" value="Installer le certificat" style="margin-left:0px;"></p>
            </form>
            <?php
        }
    }
}
