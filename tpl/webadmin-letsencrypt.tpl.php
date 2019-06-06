<h2>Gestion Let's Encrypt</h2>

<?php
if (count($_SESSION['letsencrypt-domains']) > 0) {
    ?>

<p>Liste des domaines à intégrer dans le certificat : </p>
<ul>
    <?php
    foreach ($_SESSION['letsencrypt-domains'] as $domain) {
        echo '<li>' . $domain . '</li>';
    }
    ?>
</ul>


    <?php
} else {
   print "<p>Aucun domaine.</p>";
}
