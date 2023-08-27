<?php

/**
 * Config for the main node, which is usually the default router and provides
 * the main context broker
 */

namespace Ensemble;
require 'home_common.inc.php';


/**
 * global.log is intended as a central logging device
 */
$conf['devices'][] = new Device\LoggerDevice('global.log', new Log\TextLog(_VAR.'global.log'));

/**
 * global.context is intended as the master context device
 */
$conf['devices'][] = $ctx = new Device\LoggingContextDevice('global.context', "mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
$ctx->repopulate();

// Non-persistent schedule store
$conf['devices'][] = $sctx = new Device\ContextDevice('global.schedules');


/**
 * Office blind
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', '100');
$bsched->setPoint('08:45:00', '0'); // Reopen in the morning
$bsched->setPoint('12:00:00', 'auto'); // Afternoons, use the auto-closer based on sun
$bsched->setPoint('21:30:00', '100'); // Close at night

$sd = new Schedule\DailyScheduler('officeblind.scheduler', 'global.schedules', 'officeblindschedule', $bsched);
$conf['devices'][] = $sd;

$conf['devices'][] = $sdb = new Device\Blind\ScheduledBlind("blind1", $bridge, "blind1", new Device\ContextPointer('global.schedules', 'officeblindschedule'));
$sdb->vPos = 120;
$sdb->distance = 80;
$sdb->horizon = 10/180 * M_PI;

/**
 * Bedroom blind
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', '100');
$bsched->setPoint('07:50:00', '0');
$bsched->setPoint('18:00:00', 'dusk');
$bsched->setPoint('22:00:00', '100');

$sd = new Schedule\DailyScheduler('bedroomblind.scheduler', 'global.schedules', 'bedroomblindschedule', $bsched);
$conf['devices'][] = $sd;
$conf['devices'][] = $socket = new Device\Blind\ScheduledBlind("blind2", $bridge, "blind2", new Device\ContextPointer('global.schedules', 'bedroomblindschedule'));


/**
 * Downstairs Blinds
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', '100');
$bsched->setPoint('07:30:00', 'dawn');
$bsched->setPoint('11:00:00', '0');
$bsched->setPoint('18:00:00', 'dusk');
$bsched->setPoint('22:00:00', '100');

$sd = new Schedule\DailyScheduler('thermalblind.scheduler', 'global.schedules', 'thermalblindschedule', $bsched);
$conf['devices'][] = $sd;

// Kitchen front
$conf['devices'][] = $socket = new Device\Blind\ScheduledBlind("blind3", $bridge, "blind3", new Device\ContextPointer('global.schedules', 'thermalblindschedule'));

// Kitchen rear
$conf['devices'][] = $socket = new Device\Blind\ScheduledBlind("blind4", $bridge, "blind4", new Device\ContextPointer('global.schedules', 'thermalblindschedule'));

// Lounge front
$conf['devices'][] = $socket = new Device\Blind\ScheduledBlind("blind5", $bridge, "blind5", new Device\ContextPointer('global.schedules', 'thermalblindschedule'));

// Lounge rear
$conf['devices'][] = $socket = new Device\Blind\ScheduledBlind("blind6", $bridge, "blind6", new Device\ContextPointer('global.schedules', 'thermalblindschedule'));


/**
 * The Shower Socket limits use of the power shower using a tasmota smart socket
 */
$conf['devices'][] = $swrsocket = new Device\Socket\ShowerSocket("showersocket", $bridge, "socket14");
($conf['devices'][] = $swrsocket->getPowerMeter())->addDestination('global.context', 'power-shower');

// Tie shower socket to shower extractor
$conf['devices'][] = $extractor = new Device\Socket\TimedSocket("loftextractor", $bridge, "bathroom", "3"); // POWER3 on 'bathroom' MQTT device
$swrsocket->getStatus()->sub('SENSOR.ENERGY.POWER', array($extractor, 'trigger')); // Trigger the extractor when the socket current draw changes

// Wall extractor similar, but comes on AFTER the shower is turned off
$conf['devices'][] = $extractor = new Device\Socket\TimedSocket("wallextractor", $bridge, "bathroom", "4"); // POWER4 on 'bathroom' MQTT device
$swrsocket->getStatus()->sub('SENSOR.ENERGY.POWER', array($extractor, 'trigger')); // Trigger the extractor when the socket current draw changes
$extractor->setOffOnly();


/**
 * Weather Forecast
 */
$conf['devices'][] = new Device\Forecast\ForecastDevice('forecast', $datapoint_key, '353868', 'global.context', 'forecast-');


/**
 * Driveway Pump
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'OFF');
for($h = 6; $h < 24; $h = $h+2) {
    $H = sprintf("%2u", $h);
    $bsched->setPoint("$H:00:00", 'ON');
    $bsched->setPoint("$H:02:00", 'OFF');
}
$sd = new Schedule\DailyScheduler('pump2.scheduler', 'global.schedules', 'pump2', $bsched);
$conf['devices'][] = $sd;

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-pump2", $bridge, new Device\ContextPointer('global.schedules', 'pump2'), "socket8");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-pump2');
