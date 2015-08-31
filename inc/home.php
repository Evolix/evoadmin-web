<?php

/**
 * HomePage 
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
    $form = new FormPage("Sélection du cluster", FALSE);
    $form->addField('cluster_name', new SelectFormField('cluster', FALSE, $conf['clusters']));

    if (!empty($_POST)) {
        $form->isCurrentPage(TRUE);
        $form->initFields();

        if ($form->verify(TRUE)) {
            $_SESSION['cluster'] = $form->getField('cluster_name')->getReadableValue();
        }
    }
}

include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
include_once EVOADMIN_BASE . '../tpl/home.tpl.php';
include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';
