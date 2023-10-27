<?php

namespace Ensemble;
use Ensemble\MQTT as MQTT;

date_default_timezone_set('Europe/London');

$LAT = 50.9288;
$LNG = -1.3372;

$conf['default_endpoint'] = 'http://10.0.0.8:3107/ensemble-iot/1.0/';

if(!file_exists(__DIR__.'/dbcreds.php')) {
        echo "Create and populate config/dbcreds.php\n";
        exit;
}

require 'dbcreds.php';


/**
 * MQTT connection
 */
$host = gethostbyname('mosquitto');
$mqtthost = $host == 'mosquitto' ? '10.0.0.8' : 'mosquitto'; // Hostname used in docker, IP used when testing
echo "MQTT Host is $mqtthost (lookup=$host)\n";
$client = new MQTT\Client($mqtthost, 1883);
$conf['devices'][] = $bridge = new MQTT\Bridge('_main.mqttbridge', $client);
