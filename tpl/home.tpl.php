<?php

/**
 * HomePage template 
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

require_once EVOADMIN_BASE . '../evolibs/Form.php';

if (is_mcluster_mode()) {
    echo '<p>Bienvenue, sélectionnez le cluster que vous voulez administrer,
puis utilisez le menu ci-dessus pour administrer votre compte.</p>';

    print "<form name=\"form-add\" id=\"form-add\" action=\"\" method=\"POST\">";
    print "   <fieldset>";
    print "        <legend>Choisissez un cluster</legend>";
    print $form;
    print "        <p><input type=\"submit\" value=\"Ok\"/></p>";
    print "     </fieldset>";
    print "</form>";
}
else {
    echo '<p>Bienvenue, utilisez le menu ci-dessus pour administrer votre compte.</p>';
}
?>