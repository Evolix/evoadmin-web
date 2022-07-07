<?php
/**
 * Authentification controler 
 *
 * Copyright (c) 2009-2022 Evolix - Tous droits reserves
 *
 * @author  Evolix <info@evolix.fr>
 * @author  Gregory Colpart <reg@evolix.fr>
 * @author  Thomas Martin <tmartin@evolix.fr>
 * @author  Sebastien Palma <spalma@evolix.fr>
 * @author  and others.
 * @version 1.0
 */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST)) {
    $input_username = $_POST['login'];
    $input_password = $_POST['passw'];

    if (isset($conf['logins'][$input_username]) && strlen($conf['logins'][$input_username]) != 64 && password_verify($input_password, $conf['logins'][$input_username]) ) {
        $_SESSION['auth'] = true;
        $_SESSION['user'] = $input_username;
        $_SESSION['user_id'] = posix_getpwnam($input_username) ? posix_getpwnam($input_username)['uid'] : 65534;
        unset($_SESSION['error']);

    } elseif (isset($conf['logins'][$input_username]) && strlen($conf['logins'][$input_username]) == 64 && hash("sha256",$input_password) === $conf['logins'][$input_username]) {
        // Compatibility mode for previous installs (sha256)
        $_SESSION['auth'] = true;
        $_SESSION['user'] = $input_username;
        $_SESSION['user_id'] = posix_getpwnam($input_username) ? posix_getpwnam($input_username)['uid'] : 65534;
        unset($_SESSION['error']);

    } else {
        $_SESSION['auth'] = false;
        $_SESSION['user'] = '';
        $_SESSION['error'] = true;
    }

    http_redirect('/'); 

} else {

    if (!empty($_SESSION['error'])) {
        $error = $_SESSION['error'];
        unset($_SESSION['error']);
    }
    
    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/auth.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

} 
