#!/bin/sh

set -eu

if [ $# != 1 ] ; then
	echo usage: $0 '<suffix>'
	exit 1
fi

# the SUFFIX must not contain spaces or shell meta characters
SUFFIX=$1

if [ -e /etc/apache2-$SUFFIX ] ; then
	echo ERROR: /etc/apache2-$SUFFIX already exists
	exit 2
fi

echo Setting up /etc/apache2-$SUFFIX ...
cp -a /etc/apache2-template /etc/apache2-$SUFFIX

echo "systemd is in use, no init script installed"
echo "use the 'apache2@$SUFFIX.service' service to control your new instance"
echo "sample commands:"
echo "systemctl start apache2@$SUFFIX.service"
echo "systemctl enable apache2@$SUFFIX.service"

echo -n Setting up symlinks: 
for a in a2enmod a2dismod a2ensite a2dissite a2enconf a2disconf apache2ctl ; do
	echo -n " $a-$SUFFIX"
	ln -s /usr/sbin/$a /usr/local/sbin/$a-$SUFFIX
done
echo

echo Setting up /etc/logrotate.d/apache2-$SUFFIX and /var/log/apache2-$SUFFIX ...
cp -a /etc/logrotate.d/apache2 /etc/logrotate.d/apache2-$SUFFIX
perl -p -i -e s,/var/log/apache2,/var/log/apache2-$SUFFIX,g /etc/logrotate.d/apache2-$SUFFIX
perl -p -i -e "s,\sapache2\s, apache2\@$SUFFIX ,g" /etc/logrotate.d/apache2-$SUFFIX
mkdir /var/log/apache2-$SUFFIX
chmod 750 /var/log/apache2-$SUFFIX
chown root:adm /var/log/apache2-$SUFFIX

echo "Setting up /etc/default/apache-htcacheclean-$SUFFIX"
cp -a /etc/default/apache-htcacheclean /etc/default/apache-htcacheclean-$SUFFIX
