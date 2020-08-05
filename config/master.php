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
$mqtthost = gethostbyname('mosquitto') == 'mosquitto' ? '10.0.0.8' : 'mosquitto'; // Hostname used in docker, IP used when testing
$client = new \Ensemble\MQTT\Client($mqtthost, 1883);
$conf['devices'][] = $socket = new Device\Socket\ShowerSocket("showersocket", $client, "socket4");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-shower');

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
$sd = new Schedule\DailyScheduler('daytime.scheduler', 'global.schedules', 'daytimeoffpeak', $bsched);
$conf['devices'][] = $sd;

// offpeak
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'ON');
$bsched->setPoint('16:00:00', 'OFF');
$bsched->setPoint('19:00:00', 'ON');
$sd = new Schedule\DailyScheduler('offpeak.scheduler', 'global.schedules', 'offpeak', $bsched);
$conf['devices'][] = $sd;

// offpeak oppoff
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'ON');
$bsched->setPoint('14:00:00', 'OPOFF');
$bsched->setPoint('16:00:00', 'OFF');
$bsched->setPoint('19:00:00', 'ON');
$sd = new Schedule\DailyScheduler('offpeak_opoff.scheduler', 'global.schedules', 'offpeak_opoff', $bsched);
$conf['devices'][] = $sd;

/**
 * Attach sockets to schedules
 */

// Office ventilator
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-vent-office", $client, "socket5", 'global.schedules', 'daytimeoffpeak');
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-officevent');

// Tumble dryer
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-dryer", $client, "socket1", 'global.schedules', 'offpeak');
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-dryer');

// Washing machine
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-washingmachine", $client, "socket2", 'global.schedules', 'offpeak_opoff');
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-washingmachine');

// Dishwasher
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-dishwasher", $client, "socket3", 'global.schedules', 'offpeak_opoff');
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-dishwasher');

// Network socket is for power monitoring only
$conf['devices'][] = $socket = new Device\Socket\Socket("socket-network", $client, "socket6");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-network');
