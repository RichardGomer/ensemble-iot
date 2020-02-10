#!/bin/bash
SCRIPT=`realpath $0`
BASEPATH=`dirname $( dirname $SCRIPT )`
cd $BASEPATH
php bin/run.php
