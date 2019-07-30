<h2>Gestion Let's Encrypt</h2>

<?php
if (!empty($messages)) {
    foreach($messages as $message) {
        switch ($message["type"]) {
            case "error":
                echo '<span class="form-error">' . $message["content"] . '</span>';

                if (count($failed_domains) > 0) {
                    echo '<p>';
                    foreach ($failed_domains as $failed_domain) {
                        echo $failed_domain . "<br>";
                    }
                    echo '</p>';
                }
                break;
            case "warning":
                echo '<span class="form-warning">' . $message["content"] . '</span>'; ?>
                <form name="form-confirm-renew-cert" id="form-confirm-renew-cert" action="" method="POST">
                    <p>
                        <input type="hidden" name="force_renew">
                        <input type="submit" name="submit" value="Confirmer l'installation" style="margin-left:0px;">
                    </p>
                </form>
                <?php
                break;
            case "notice":
                echo '<span class="form-notice">' . $message["content"] . '</span>';
                break;
            default:
                break;
        }
    }
} else {
    if (!isset($_POST["submit"])) {
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
