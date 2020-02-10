#!/bin/bash
# On docker (which doesn't have system services) we need to spawn lighttpd manually

if [[ -z "$ENDPOINTURL" ]]; then
    echo "Must provide ENDPOINTURL environment variable because endpoint URL can't be determined in docker" 1>&2
    exit 1
fi

if [[ -z "$CONFIGNAME" ]]; then
    echo "Must provide CONFIGNAME environment variable because IP-based autoloading doesn't work in docker" 1>&2
    exit 1
fi

SCRIPT=`realpath $0`
BASEPATH=`dirname $( dirname $SCRIPT )`
cd $BASEPATH
lighttpd -f /etc/lighttpd/lighttpd.conf
php bin/run.php --local-ep=$ENDPOINTURL --config=$CONFIGNAME
