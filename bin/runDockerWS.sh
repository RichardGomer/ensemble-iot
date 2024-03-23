#!/bin/bash
# Run the websocket proxy in docker

if [[ -z "$ENDPOINTURL" ]]; then
    echo "Must provide ENDPOINTURL environment variable because endpoint URL can't be determined in docker" 1>&2
    exit 1
fi

if [[ -z "$DEFAULTENDPOINTURL" ]]; then
    echo "Must provide DEFAULTENDPOINTURL environment variable, specifying and endpoint to relay commands through" 1>&2
    exit 1
fi

SCRIPT=`realpath $0`
BASEPATH=`dirname $( dirname $SCRIPT )`
cd $BASEPATH
lighttpd -f /etc/lighttpd/lighttpd.conf
php websocket/run.php --local-ep=$ENDPOINTURL --default-ep=$DEFAULTENDPOINTURL
