<?php

/**
 * Config for the master node, which is usually the default router and provides
 * a master context
 */

namespace Ensemble;
use Ensemble\MQTT as MQTT;
use Ensemble\Device\Light as Light;

date_default_timezone_set('Europe/London');

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

$conf['devices'][] = $ctx = new Device\LoggingContextDevice('global.context', "mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
$ctx->repopulate();

/**
 * Forecast
 */
$conf['devices'][] = new Device\Forecast\ForecastDevice('forecast', $datapoint_key, '353868', 'global.context', 'forecast-');

/**
 * The Shower Socket limits use of the power shower using a tasmota smart socket
 */
$host = gethostbyname('mosquitto');
$mqtthost = $host == 'mosquitto' ? '10.0.0.8' : 'mosquitto'; // Hostname used in docker, IP used when testing
echo "MQTT Host is $mqtthost (lookup=$host)\n";
$client = new \Ensemble\MQTT\Client($mqtthost, 1883);
$conf['devices'][] = $socket = new Device\Socket\ShowerSocket("showersocket", $client, "socket4");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-shower');

/**
 * Scheduling!
 */
// Create a context device to broker schedules
$conf['devices'][] = $sctx = new Device\ContextDevice('global.schedules');

/**
* Create some schedules
*/

// Daily offpeak
$doffpeak = new Schedule\Schedule();
$doffpeak->setPoint('00:00:00', 'OFF');
$doffpeak->setPoint('07:00:00', 'ON');
$doffpeak->setPoint('16:00:00', 'OFF');
$doffpeak->setPoint('19:00:00', 'ON');
$doffpeak->setPoint('22:00:00', 'OFF');
$sd_doffpeak = new Schedule\DailyScheduler('daytime.scheduler', 'global.schedules', 'daytimeoffpeak', $doffpeak);
$conf['devices'][] = $sd_doffpeak;

// offpeak
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'ON');
$bsched->setPoint('16:00:00', 'OFF');
$bsched->setPoint('19:00:00', 'ON');
$sd_offpeak = new Schedule\DailyScheduler('offpeak.scheduler', 'global.schedules', 'offpeak', $bsched);
$conf['devices'][] = $sd_offpeak;

// offpeak oppoff
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'ON');
$bsched->setPoint('14:00:00', 'OPOFF');
$bsched->setPoint('16:00:00', 'OFF');
$bsched->setPoint('19:00:00', 'ON');
$sd_opoff = new Schedule\DailyScheduler('offpeak_opoff.scheduler', 'global.schedules', 'offpeak_opoff', $bsched);
$conf['devices'][] = $sd_opoff;

/**
 * Smart lights
 */
$lsched = new Schedule\Schedule();
$lsched->setPoint('00:00:00', 'auto 30%');
$lsched->setPoint('05:00:00', 'auto 30%');
$lsched->setPoint('06:00:00', 'auto 100%');
$lsched->setPoint('22:00:00', 'auto 100%');
$lsched->setPoint('23:00:00', 'auto 30%');

$sd_lights = new Schedule\DailyScheduler('light.scheduler', 'global.schedules', 'daylightschedule', $lsched);
$conf['devices'][] = $sd_lights;

// Create a socket to be controlled and bind it to the schedule in the broker
$client = new MQTT\Client($mqtthost, 1883);
$conf['devices'][] = $socket = new Light\RGBWCT("light1", $client, "light1", 'global.schedules', 'daylightschedule');
$conf['devices'][] = $socket = new Light\RGBWCT("light2", $client, "light2", 'global.schedules', 'daylightschedule');
$conf['devices'][] = $socket = new Light\RGBWCT("light3", $client, "light3", 'global.schedules', 'daylightschedule');

/**
 * Attach sockets to schedules
 */

// Office ventilator
// Uses the daytime offpeak schedule, but translates to only be active May - September
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-vent-office", $client, "socket5", 'global.schedules', 'daytimeoffpeak');
$socket->getDriver()->setTranslator(function($v) {
    $m = (int) date('m');
    return $m >= 5 && $m <= 9 ? $v : "OFF";
});
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

// Greenhouse heating
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-greenhouse", $client, "socket9", 'global.schedules', 'offpeak');
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-greenhouse');

// Network socket is for power monitoring only
$conf['devices'][] = $socket = new Device\Socket\Socket("socket-network", $client, "socket6");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-network');

// TV socket is for power monitoring only
$conf['devices'][] = $socket = new Device\Socket\Socket("socket-tv", $client, "socket7");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-tv');

/**
 * Toilet Heater
 */
// Convert daily offpeak schedule to target temperatures
$sched_heat = $doffpeak->translate(function($s){
 return $s == 'ON' ? '19' : '10';
});
$sd_heat = new Schedule\DailyScheduler('electric_heat.scheduler', 'global.schedules', 'electric_heat', $sched_heat);
$conf['devices'][] = $sd_heat;

$ir1state = 'ir1-htr-temp-st';

// Set the initial heater state to 18, if the context field isn't set already
if(count($s = $ctx->get($ir1state)) < 1) {
    echo "Set $ir1state to 18\n";
    $ctx->update($ir1state, 18);
}

// Configure the heater itself
$conf['devices'][] = $ir1 = new Device\IR\NettaHeater("ir1-heater", $client, "ir1", 'global.context', $ir1state);

// And add a driver to control the
$conf['devices'][] = new Schedule\Driver($ir1, function($device, $temp) {
    $device->setTemperature($temp);
}, 'global.schedules', 'electric_heat');

/**
 * Additional Pump
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('04:00:00', 'ON');
$bsched->setPoint('04:01:30', 'OFF');
$bsched->setPoint('12:00:00', 'ON');
$bsched->setPoint('12:01:30', 'OFF');
$bsched->setPoint('20:00:00', 'ON');
$bsched->setPoint('20:01:30', 'OFF');
$sd = new Schedule\DailyScheduler('pump2.scheduler', 'global.schedules', 'pump2', $bsched);
$conf['devices'][] = $sd;

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-pump2", $client, "socket8", 'global.schedules', 'pump2');
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-pump2');
