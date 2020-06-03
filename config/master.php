<?php

/**
 * Config for the master node, which is usually the default router and provides
 * a master context
 */

namespace Ensemble;

/**
 * global.log is intended as a central logging device
 */
$conf['devices'][] = new Device\LoggerDevice('global.log', new Log\TextLog(_VAR.'global.log'));

/**
 * global.context is intended as the master context device
 */
if(!file_exists(__DIR__.'/dbcreds.php')) {
        echo "Set \$dbhost, \$dbname, \$dbuser and \$dbpass in config/dbcreds.php\n";
        exit;
}

require 'dbcreds.php';

$db = new \PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);

$st = $db->prepare("INSERT INTO context(`source`, `field`, `value`, `time`) VALUES (:source, :field, :value, :time)");

$conf['devices'][] = new Device\LoggingContextDevice('global.context', $st);

/**
 * The Shower Socket limits use of the power shower using a tasmota smart socket
 */
$client = new MQTTClient('10.0.0.8', 1883);
$conf['devices'][] = $socket = new Device\Socket\ShowerSocket("socket", $client, "socket4");
