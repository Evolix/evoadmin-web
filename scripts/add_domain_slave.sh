#!/bin/bash

slaveServer="ns.slave.example.com"
sshUser="example"
clientName="example"
webServerName="example"
mailAddress="admin@example.com"

usage() {
    cat <<EOT
$0 domain_to_add
Exemple: $0 mydomain.com
EOT
exit 1
}

if [[ $# != 1 ]]; then
    echo "Need only one argument!"
    usage
fi

domain=$1
# Remove www. if any.
domain=${domain#www.}

# Connect to $slaveServer and add slave domain.
stdin=/tmp/empty-in
stdout=/tmp/empty-out
result=/tmp/result
[[ -e $stdin ]] && rm -f $stdin
[[ -e $stdout ]] && rm -f $stdout
empty -f -i $stdin -o $stdout -L $result ssh ${sshUser}@${slaveServer} "sudo /usr/share/scripts/bind-slave-${clientName}.sh"
# Responses to question of add slave domain script.
sleep 2
echo "$domain" > $stdin
sleep 2
# Send mail
subject="[AUTO-${clientName}] Added slave domain of $webServerName $domain"
mail -s "$subject" $mailAddress < $result
cat $result
# Cleaning
rm $result

