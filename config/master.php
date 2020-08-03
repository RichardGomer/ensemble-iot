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
$client = new \Ensemble\MQTT\Client('mosquitto', 1883);
$conf['devices'][] = $socket = new Device\Socket\ShowerSocket("showersocket", $client, "socket4");


/**
 * Scheduling!
 */
// Create a context device to broker schedules
$conf['devices'][] = $ctx = new Device\ContextDevice('global.schedules');

/**
* Create some schedules
*/

// Daily offpeak
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'OFF');
$bsched->setPoint('08:00:00', 'ON');
$bsched->setPoint('16:00:00', 'OFF');
$bsched->setPoint('19:00:00', 'ON');
$bsched->setPoint('21:00:00', 'OFF');
$sd = new Schedule\DailyScheduler('daily.scheduler', 'global.schedules', 'dailyoffpeak', $bsched);
$conf['devices'][] = $sd;


// Office ventilator
$client = new MQTTClient('10.0.0.8', 1883);
$conf['devices'][] = new Device\Socket\ScheduledSocket("socket-vent-office", $client, "socket5", 'global.schedules', 'dailyoffpeak');
