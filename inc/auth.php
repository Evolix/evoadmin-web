<?php

/**
 * Authentification page 
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



if ((empty($_GET['form']) || $_GET['form']!=1) && !empty($_POST)) {
  $login=0;
  $username=$_POST['login'];
  $password=$_POST['passw'];


  $login = pam_auth($username, $password);
 
  if ($login==1) {
    $_SESSION['auth']=1;
    $_SESSION['user']=$username;
    $_SESSION['error']='';

    $user = posix_getpwnam($username);
    // On nettoie le nom complet
    $gecos = explode(',',$user['gecos']);
    $user['gecos'] = $gecos[0];
    $_SESSION['user_id'] = $user['uid'];
    $_SESSION['user_gid'] = $user['gid'];
    $_SESSION['user_name'] = $user['gecos'];
  } else {
    $_SESSION['auth']=0;
    $_SESSION['user']='';
    $_SESSION['error']=1;
  }
  http_redirect('/'); 

} else {

if(!empty($_SESSION['error'])) {
  $error=$_SESSION['error'];
}
  
  include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
  include_once EVOADMIN_BASE . '../tpl/auth.tpl.php';
  include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

} 

?>
