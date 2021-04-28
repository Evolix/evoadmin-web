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
  $username=$_POST['login'];
  $password=$_POST['passw'];

  if (hash("sha256",$password) == $conf['logins'][$username]) {
    $_SESSION['auth']=1;
    $_SESSION['user']=$username;
    $_SESSION['user_id'] = posix_getpwnam($username) ? posix_getpwnam($username)['uid'] : 65534;
    $_SESSION['error']='';
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
