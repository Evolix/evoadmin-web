<h2>Gestion Let's Encrypt</h2>

<?php
if (isset($_POST['submit'])) {
    if (!empty($error_message)) {
        echo '<span class="form-error">' . $error_message . '</span>';

        if (count($failed_domains) > 0) {
            echo '<p>';
            foreach ($failed_domains as $failed_domain) {
                echo $failed_domain . "<br>";
            }
            echo '</p>';
        }
    } else {
        echo 'checks succeeded.';
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
            <p><input type="submit" name="submit" value="Installer le certificat" style="margin-left:0px;"></p>
        </form>
        <?php
    }
}
