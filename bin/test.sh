#!/bin/bash
# This script is for testing with a single local device
# it loads the config/test.php script for config

SCRIPT=`realpath $0`
BASEPATH=`dirname $( dirname $SCRIPT )`
cd $BASEPATH

cd www
php -S 127.0.0.1:8182 &

cd ../bin
php run.php --local-ep=http://127.0.0.1:8182/ensemble-iot/1.0/index.php --disable-direct-local --config=test

kill %1
