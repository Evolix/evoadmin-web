<?php

/**
 * Virtual FTP Accounts Management Page 
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

$action=$params[1];
$account=$params[2];

$errors = array();
$errors[1] = "Les mots de passe de correspondent pas";
$errors[2] = "Le login doit contenir entre 6 et 16 caractères";
$errors[3] = "Le répertoire existe, continuer quand même ?";
$errors[4] = "Impossible de créer le dossier racine. Merci de contacter votre administrateur";
$errors[5] = "Le nom de compte choisi est déjà utilisé sur le système. Merci de le modifier";
$errors[6] = "Le chemin saisi n'est pas valide. Merci d'en saisir un autre";
$errors[7] = "Le mot de passe doit contenir au moins 6 caractères";

$user_id = $_SESSION['user_id'];

if ($action=="add") {
    $owner_list = get_owner_list();
 
  if(array_key_exists('submit_button', $_POST)) {
    //print "Ajout du compte {$_POST['login']}";
    $login = $_POST['login'];
    $passw = $_POST['passwd'];
    $pass2 = $_POST['passwd_c'];
    $dossier = $_POST['path'];
    if (array_key_exists('confirm', $_POST)) $confirm = $_POST['confirm'];

    // On vérifie que le login choisi n'est pas déjà pris
    $output = ""; 
    exec("cat /etc/proftpd/vpasswd | grep $login:", $output, $exec_return);
    if (!empty($output)) {
      $_SESSION['error']=5;
      $_SESSION['form']['login'] = $login;
      $_SESSION['form']['passw'] = $passw;
      $_SESSION['form']['pass2'] = $pass2;
      $_SESSION['form']['dossier'] = $dossier;
      http_redirect('/ftpadmin/add');
    }



    // On vérifie la taille du login
    if (strlen($login)<6 || strlen($login)>16) {
      $login='';
      $_SESSION['error']=2;
      http_redirect("/ftpadmin/add");
    }

    // On vérifie qu'on a bien saisi un mot de passe
    if (strlen($passw) < 6) {
      $_SESSION['error'] = 7;
      $_SESSION['form']['login'] = $login;
      $_SESSION['form']['passw'] = '';
      $_SESSION['form']['pass2'] = '';
      $_SESSION['form']['dossier'] = $dossier;
      http_redirect('/ftpadmin/add');
    }

    // On vérifie que les 2 mots de passe sont identiques
    if ($passw != $pass2) {
      $passw = $pass2 = '';
      $_SESSION['error']=1;
      $_SESSION['form']['login'] = $login;
      $_SESSION['form']['passw'] = '';
      $_SESSION['form']['pass2'] = '';
      $_SESSION['form']['dossier'] = $dossier;
      http_redirect("/ftpadmin/add");
    }

    /* Le superadmin est capable de créer un compte pour quelqu'un d'autre */
    /* ATTENTION : dans la suite du code $user_id est modifié */
    if(is_superadmin()) {
      $owner_info = posix_getpwnam($_POST['owner']);
      $user_id = $owner_info['uid'];
      $complete_path = sprintf('/home/%s/%s', $_POST['owner'], $dossier);
    } else {
      $complete_path = "/home/{$_SESSION['user']}/$dossier";
    }

    $_SESSION['error']=null;
    $_SESSION['form']=null;
    // Création du répertoire source
    $complete_path = str_replace('//', '/', $complete_path);
 
    // Vérification de l'intégrité du chemin saisi
    if (strpos($complete_path, '../')!==FALSE) {
      $_SESSION['error']=6;
      $_SESSION['form']['login'] = $login;
      $_SESSION['form']['passw'] = $passw;
      $_SESSION['form']['pass2'] = $pass2;
      $_SESSION['form']['dossier'] = $dossier;
      http_redirect('/ftpadmin/add');
    }

    // Utilmisation du script de vérification d'existence d'un répertoire en sudo 
    $exists = '';
    exec("sudo /usr/share/scripts/evoadmin/directorycheck.sh $complete_path", $exists, $return);

    // On teste d'abord si le répertoire n'existe pas déjà ou si l'utilisateur 
    if ($exists[0]=="1" && !isset($confirm)) {
      $_SESSION['error']=3;
      $_SESSION['form']['login'] = $login;
      $_SESSION['form']['passw'] = $passw;
      $_SESSION['form']['pass2'] = $pass2;
      $_SESSION['form']['dossier'] = $dossier;

      http_redirect('/ftpadmin/add');
    } elseif ($exists[0]=="1" && isset($confirm) && $confirm==0) {
      $_SESSION['error']=null; 
      $_SESSION['form']['login'] = $login;
      $_SESSION['form']['passw'] = $passw;
      $_SESSION['form']['pass2'] = $pass2;
      $_SESSION['form']['dossier'] = "";
      http_redirect('/ftpadmin/add');
    } elseif ($exists[0]==0 || (isset($confirm) && $confirm==1)) {
     
      // On appelle le script de création de compte FTP
      sudoexec("ftpadmin.sh -a a -u $user_id -n $login -f $complete_path -p $passw", $standard_output, $function_output);

      // On revient à la page de listing
      $_SESSION['error']=null;
      $_SESSION['form']=null;
      $ftp_added_login = $login;
    
    } else {
      print "Le cas de figure présent n'existe pas... Avez-vous essayé de modifier l'adresse manuellement ?";
    }
  }

  include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
  include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
  if(!empty($ftp_added_login)) {
    include_once EVOADMIN_BASE . '../tpl/ftpadmin-new-ok.tpl.php';
  } else {
    include_once EVOADMIN_BASE . '../tpl/ftpadmin-new.tpl.php';
  }
  include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

} elseif ($action=="edit") {

  print "Modification du mot de passe de $account";

} elseif ($action=="delete") {

  sudoexec("ftpadmin.sh -a d -u $user_id -n $account -f /dev/null -p azertyuiop", $standard_output, $function_output);


  $_SESSION['error'] = null;
  $_SESSION['form'] = null;
  http_redirect('/ftpadmin/');

} else {


    $account_list = array();
    $table = array(); 
    $num_line=0;
    
    #exec("sudo /usr/share/scripts/evoadmin/ftpadmin.sh -a l -u $user_id", $account_list, $exec_return);
    $list_cmd = "ftpadmin.sh -a l";
    if(!is_superadmin()) {
        $list_cmd .= " -u $user_id";
    }
    sudoexec($list_cmd, $account_list, $exec_return);

    sort($account_list);
    
    $size_total = 0;
   
    foreach ($account_list as $account) {
 
        $infos = split(':', $account); 
         
  
        if (!empty($infos[0])) {
            $table[$num_line]['owner'] = $infos[0];
            $table[$num_line]['name'] = $infos[1];
            
            $path = split("/", $infos[2]);
            $rel_path='';
            foreach($path as $id => $folder) {
              if ($id>2) $rel_path.='/'.$folder;
            }
            $table[$num_line]['path'] = str_replace('//', '/', $rel_path);

            if ($infos[3]>0) {
              $size_total += $infos[3];
              $table[$num_line]['size'] = formatBytes($infos[3]);
            } else {
	      $table[$num_line]['size'] = 0;
            }

            if ($infos[4]>0) {
              $table[$num_line]['date'] = date("d/m/Y h:i:s", $infos[4]);
            } else {
	      $table[$num_line]['date'] = "01/01/1970";
            }

            $num_line++;
        }
    }

    $size_total = formatBytes($size_total);

    /* Création d'un tableau trié contenant la liste de tous les comptes,
       utilisé pour la sélection du propriétaire en mode superadmin */
    $owner_list = get_owner_list();

    include_once EVOADMIN_BASE . '../tpl/header.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/menu.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/ftpadmin.tpl.php';
    include_once EVOADMIN_BASE . '../tpl/footer.tpl.php';

}

$_SESSION['error']=null;
$_SESSION['form']=null;

function formatBytes($bytes, $precision = 2) {
    $bytes *= 1024;
    $units = array('o', 'Ko', 'Mo', 'Go', 'To');
  
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
  
    $bytes /= pow(1024, $pow);
  
    return round($bytes, $precision) . ' ' . $units[$pow];
} 


function get_owner_list() {
    $owner_list = array();
    
    $fd_passwd = fopen('/etc/passwd', 'r');

    $usernames = array();
    while($line = fgets($fd_passwd)) {
        $tmp = explode(':', $line);
        $usernames[$tmp[0]] = 1;
    }

    foreach(array_keys($usernames) as $cur) {
        if(preg_match('/^www-/', $cur)) { continue; }
        if(empty($usernames["www-$cur"])) { continue; }
        $owner_list[] = $cur;
    }

    asort($owner_list);

    return $owner_list;
}

?>
