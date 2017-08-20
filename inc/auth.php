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

// sha256 hashs (TODO: move in conf file)
$logins=array();
$logins['foo'] = 'd5d3c723fb82cb0078f399888af78204234535ec2ef3da56710fdd51f90d2477';
$logins['bar'] = '7938c84d6e43d1659612a7ea7c1101ed02e52751bb64597a8c20ebaba8ba4303';

if ((empty($_GET['form']) || $_GET['form']!=1) && !empty($_POST)) {
  $username=$_POST['login'];
  $password=$_POST['passw'];

  if (hash("sha256",$password) == $logins[$username]) {
    $_SESSION['auth']=1;
    $_SESSION['user']=$username;
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
