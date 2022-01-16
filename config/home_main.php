<?php

/**
 * Config for the main node, which is usually the default router and provides
 * the main context broker
 */

namespace Ensemble;
use Ensemble\MQTT as MQTT;
use Ensemble\Device\Light as Light;

date_default_timezone_set('Europe/London');


if(!file_exists(__DIR__.'/dbcreds.php')) {
        echo "Create and populate config/dbcreds.php\n";
        exit;
}

require 'dbcreds.php';


/**
 * global.log is intended as a central logging device
 */
$conf['devices'][] = new Device\LoggerDevice('global.log', new Log\TextLog(_VAR.'global.log'));

/**
 * global.context is intended as the master context device
 */
$conf['devices'][] = $ctx = new Device\LoggingContextDevice('global.context', "mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
$ctx->repopulate();

/**
 * Weather Forecast
 */
$conf['devices'][] = new Device\Forecast\ForecastDevice('forecast', $datapoint_key, '353868', 'global.context', 'forecast-');


$host = gethostbyname('mosquitto');
$mqtthost = $host == 'mosquitto' ? '10.0.0.8' : 'mosquitto'; // Hostname used in docker, IP used when testing
echo "MQTT Host is $mqtthost (lookup=$host)\n";
$client = new MQTT\Client($mqtthost, 1883);
$bridge = new MQTT\Bridge('main.mqttbridge', $client);

/**
 * Smart lights
 */
$lsched = new Schedule\Schedule();
$lsched->setPoint('00:00:00', 'auto 30%');
$lsched->setPoint('05:00:00', 'auto 30%');
$lsched->setPoint('06:00:00', 'auto 100%');
$lsched->setPoint('21:30:00', 'auto 100%');
$lsched->setPoint('23:00:00', 'auto 30%');

$sd_lights = new Schedule\DailyScheduler('light.scheduler', 'global.schedules', 'daylightschedule', $lsched);
$conf['devices'][] = $sd_lights;

// Create a socket to be controlled and bind it to the schedule in the broker
$conf['devices'][] = $socket = new Light\RGBWCT("light1", $client, "light1", 'global.schedules', 'daylightschedule');
$conf['devices'][] = $socket = new Light\RGBWCT("light2", $client, "light2", 'global.schedules', 'daylightschedule');
$conf['devices'][] = $socket = new Light\RGBWCT("light3", $client, "light3", 'global.schedules', 'daylightschedule');


/**
 * Greenhouse Growlight
 */
// Use a custom schedule, 0600-1600 = 10hours of light per day
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'OFF');
$bsched->setPoint('06:00:00', 'ON');
$bsched->setPoint('16:00:00', 'OFF');
$sd_growlight = new Schedule\DailyScheduler('growlight.scheduler', 'global.schedules', 'growlight', $bsched);
$conf['devices'][] = $sd_growlight;

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-growlight", $client, new Device\ContextPointer('global.schedules', 'growlight'), "socket10");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-growlight');


/**
 * Additional Pump
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('08:00:00', 'ON');
$bsched->setPoint('08:02:00', 'OFF');
$bsched->setPoint('12:00:00', 'ON');
$bsched->setPoint('12:02:00', 'OFF');
$bsched->setPoint('16:00:00', 'ON');
$bsched->setPoint('16:02:00', 'OFF');
$bsched->setPoint('20:00:00', 'ON');
$bsched->setPoint('20:02:00', 'OFF');
$sd = new Schedule\DailyScheduler('pump2.scheduler', 'global.schedules', 'pump2', $bsched);
$conf['devices'][] = $sd;

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-pump2", $client, new Device\ContextPointer('global.schedules', 'pump2'), "socket8");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-pump2');


/**
 * Office blind
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', '100');
$bsched->setPoint('08:45:00', '0'); // Reopen in the morning
$bsched->setPoint('12:00:00', 'auto'); // Afternoons, use the auto-closer based on sun
$bsched->setPoint('21:30:00', '100'); // Close at night

$sd = new Schedule\DailyScheduler('officeblind.scheduler', 'global.schedules', 'officeblindschedule', $bsched);
$sd->vPos = 120;
$sd->distance = 80;
$sd->horizon = 10/180 * M_PI;
$conf['devices'][] = $sd;

$conf['devices'][] = $socket = new Device\Blind\ScheduledBlind("blind1", $client, "blind1", new Device\ContextPointer('global.schedules', 'officeblindschedule'));


/**
 * Bedroom blind
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', '100');
$bsched->setPoint('08:10:00', '0');
$bsched->setPoint('22:00:00', '100');

$sd = new Schedule\DailyScheduler('bedroomblind.scheduler', 'global.schedules', 'bedroomblindschedule', $bsched);
$conf['devices'][] = $sd;
$conf['devices'][] = $socket = new Device\Blind\ScheduledBlind("blind2", $client, "blind2", new Device\ContextPointer('global.schedules', 'bedroomblindschedule'));
