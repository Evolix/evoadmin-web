#!/bin/bash

############################################################
#                                                          #
#  EvoAdmin : gestion des comptes FTP virtuels de ProFTPd  #
#                                                          #
#  Copyright (c) 2009 Evolix - Tous droits reserves        #
#                                                          #
#  @author Sebastien Palma <spalma@evolix.fr>              #
#  @version 1.0                                            #
#                                                          #
############################################################

# vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2

VPASSWD_PATH="/etc/proftpd/vpasswd"
FTPLOG_PATH="/var/log/evolix-ftp.log"

usage() {

cat <<EOT >&2

Usage: $0 [ACTION UID [PARAMS]]

    Manage ProFTPd virtual accounts.

    -a ACTION
       l (list), a (add), m (modify), d (delete) ProFTPd virtual account(s)

    -u UID
       UID of the real account the virtual FTP accounts will be related to

    Available PARAMS are :

    -n ACCOUNTNAME
       Name of the ProFTPd virtual account you want to add/edit/delete.
       Mandatory in all actions.

    -f FOLDER
       Directory that the virtual account will have as home directory.
       Mandatory for add and modify action.

    -p PASSWD
       Password for the virtual account.
       Mandatory for add and modify action.

EOT
}

log_msg() {
    curdate=`date +"%Y/%m/%d %H:%M:%S"`
    echo "$curdate $1" >>$FTPLOG_PATH
}

get_user_login_by_UID() {
    uid=$1
    grep $uid /etc/passwd | awk -F : "{if (\$3==$uid) print \$1}"
}

list_accounts_by_UID() {
  uid=$1

  account_list=''
  oldIFS=IFS
  IFS=$'\n'

  for line in `cat $VPASSWD_PATH`
  do
     line_uid=`echo $line | cut -d":" -f3`

     if [ ! "$uid" ] ||  [ "$line_uid" == "$uid" ]; then
       username=`get_user_login_by_UID $line_uid`
       account=`echo $line | cut -d":" -f1`
       path=`echo $line | cut -d":" -f6`
       if [ -r $path/.size ]; then
           size=`cat $path/.size`
       else
           size=0
       fi
       #modif=`cat $path/.lastmodified`       
       # Passage en minuscule ?
       #account=`echo $account | tr '[A-Z]' '[a-z]'`
       #path=`echo $path | tr '[A-Z]' '[a-z]'`

       account_list="${account_list}$username:$account:$path:$size:$modif\n"
       
     fi
  done

  echo "$account_list"

  IFS=$oldIFS
}

add_account() {
  user_id=$1
  account_name=$2
  path=$3
  passwd=$4

  cmd="{if (\$3==$user_id) print \$4}"
  user_gid=`awk -F : "$cmd" /etc/passwd`

  # Si le répoertoire de travail du compte FTP n'existe pas, on le crée
  if [ ! -d "$path" ]; then
    mkdir -p $path
    chown $user_id:$user_gid $path
    # fix by tmartin : s/655/755/
    chmod 755 $path
    setfacl -R -d -m 'o:rX' $path
  fi

  echo `echo $passwd | ftpasswd --passwd --file=$VPASSWD_PATH --name=$account_name --uid=$user_id --gid=$user_gid --home=$path --shell=/bin/false --stdin`
  log_msg "Creation du compte $account_name (uid=$user_id, gid=$user_gid, home=$path)"
}

edit_password() {
  account_name=$1
  passwd=$2

  echo `echo $passwd | ftpasswd --passwd --file=$VPASSWD_PATH --name=$account_name --uid=9999 --gid=9999 --home=/dev/null --shell=/dev/null --change-password --stdin`

}


delete_account() {

  account_name=$1

  echo `ftpasswd --passwd --file=$VPASSWD_PATH --name=$account_name --uid=9999 --gid=9999 --home=/dev/null --shell=/dev/null --delete-user`
  log_msg "Suppression du compte $account_name"
}


while getopts a:u:n:f:p: opt; do
   case "$opt" in
   a)
	in_action=$OPTARG
	;;
   u)
	in_userid=$OPTARG
	;;
   n)
	in_accountname=$OPTARG
	;;
   f)
	in_workpath=$OPTARG
	;;
   p)
	in_password=$OPTARG
	;;
   esac
done

case "$in_action" in
   l)
	account_list=`list_accounts_by_UID $in_userid`
	echo -e -n $account_list
	exit 1
	;;
   a)
	echo -e -n `add_account $in_userid $in_accountname $in_workpath $in_password`
	exit 1
	;;
   m)
	echo -e -n `edit_password $in_accountname $in_password`
	exit 1;
	;;
   d)
	echo -e -n `delete_account $in_accountname`
	exit 1;
	;;
esac

