<?php

/**
 * Gestion des comptes utilisateurs
 *
 * Copyright (c) 2009 Evolix - Tous droits reserves
 *
 * vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2 
 *
 * @author Gregory Colpart <reg@evolix.fr>
 * @author Thomas Martin <tmartin@evolix.fr>
 * @author Sebastien Palma <spalma@evolix.fr>
 * @version 1.0
 */

?>

<?php 
    if(!empty($exec_info)) {
        print '<pre>';
        if ($conf['debug'] == TRUE)
            printf("Exécution de : %s\n", $exec_info[0]);

        if ($exec_info[1]) {
            print "La commande a <strong>échoué</strong>\n";
	    print_r($exec_info);
        }
        else print "Le compte a été créé avec succès\n";

        if ($conf['debug'] == TRUE)
            foreach($exec_info[2] as $exec_line) {
                printf("%s\n", $exec_line);
            }

        print '</pre>';
    } else {
?>

<form name="form-add" id="form-add" action="" method="POST">
    <fieldset>
        <legend>Ajout d'un compte</legend>
<?php print $form; ?>
        <p><input type="submit" value="Créer"/></p>
    </fieldset>
</form>

<?php } ?>
