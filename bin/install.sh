#!/bin/bash

if [ "$EUID" -ne 0 ]
  then echo "Please run as root"
  exit
fi

SCRIPT=`realpath $0`
BASEPATH=`dirname $( dirname $SCRIPT )`
WWWPATH="$BASEPATH/www"
BINPATH="$BASEPATH/bin"
VARPATH="$BASEPATH/var"
USER=$(stat -c '%U' "$BINPATH/run.php") # Service will run as the current owner of run.php

echo "ensemble-iot service will be installed as follows:"
echo ""
echo "    www path:     " $WWWPATH
echo "    working path: " $BINPATH
echo "    run as:       " $USER
echo ""
echo "If any of the above paths are wrong, you might need to edit /etc/lighttpd/lighttpd.conf"
echo "and/or /etc/systemd/system/ensembleiot.service after installation"
echo ""
echo "If runtime username is incorrect, change the ownership of bin/run.php before"
echo "running this script"
echo ""
echo "lighttpd, wiringpi, php and composer will be installed"
echo ""
read -p "Press any key to continue"

# Set permissions on var, so web server can edit
chmod 0777 $VARPATH -R

# Install deps
apt-get update
apt-get -y install php-cli lighttpd php-cgi php-json curl
apt-get -y install wiringpi # Run separately because it doesn't exist on most platforms

# Set lighttpd document root
cp /etc/lighttpd/lighttpd.conf /etc/lighttpd/lighttpd.conf.orig
sed -i "s/\(server\.document-root.*=.*\)//g" /etc/lighttpd/lighttpd.conf
echo "server.document-root=\"${WWWPATH}\"" >> /etc/lighttpd/lighttpd.conf

# Enable PHP support in Lighttpd
lighttpd-enable-mod fastcgi
lighttpd-enable-mod fastcgi-php

# Restart HTTPD
/etc/init.d/lighttpd force-reload

# Enable httpd at boot
systemctl enable lighttpd.service

# Register as a service
cp "$BINPATH/ensembleiot.service" /etc/systemd/system/ensembleiot.service
sed -i "s|BINPATH|$BINPATH|g" /etc/systemd/system/ensembleiot.service
sed -i "s|USER|$USER|g" /etc/systemd/system/ensembleiot.service
systemctl enable ensembleiot.service

# Install composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install composer deps as the runtime user
cd $BASEPATH
su $USER -c '/usr/local/bin/composer install'
