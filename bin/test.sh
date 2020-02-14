#!/bin/bash
# Help with testing by starting a PHP web server alongside the daemon
# specify a config file as argument

SCRIPT=`realpath $0`
BASEPATH=`dirname $( dirname $SCRIPT )`
cd $BASEPATH

cd www
php -S 10.0.1.1:8182 &

if [ $# -eq 0 ]; then
    conf='test';
else
    conf=$1;
fi

cd ../bin
php run.php --local-ep=http://10.0.1.1:8182/ensemble-iot/1.0/index.php --config=$conf

kill %1
