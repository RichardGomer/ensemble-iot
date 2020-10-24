<?php

/**
 * Test schedule repopulation
 */

namespace Ensemble;
use Ensemble\MQTT as MQTT;

date_default_timezone_set('Europe/London');

if(!file_exists(__DIR__.'/dbcreds.php')) {
     echo "Set \$dbhost, \$dbname, \$dbuser and \$dbpass in config/dbcreds.php\n";
     exit;
}

require 'dbcreds.php';

$conf['devices'][]  = $ctx = new Device\LoggingContextDevice('global.context', "mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);

$ctx->repopulate();

$fields = $ctx->getAll();

var_dump($fields);
exit;
