<?php

/**
 * Config for the main node, which is usually the default router and provides
 * the main context broker
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
 * Octopus Utility Data
 */
$oct = new Schedule\Octopus($octo_key);
$oct->setElecMeter($octo_elec_meter_mpan, $octo_elec_meter_serial);
$oct->setGasMeter($octo_gas_meter_mprn, $octo_gas_meter_serial);
$oct->setTariff($octo_prodcode, $octo_trfcode);

$conf['devices'][] = $tariffdevice = new Schedule\OctopusTariffDevice('tariffscheduler', 'global.schedules', 'electariff', $oct);
$conf['devices'][] = new Schedule\OctopusGasUsageDevice('gasusagescheduler', 'global.context', 'gasusage', $oct);
$conf['devices'][] = new Schedule\OctopusElecUsageDevice('elecusagescheduler', 'global.context', 'elecusage', $oct);


/**
 * The Shower Socket limits use of the power shower using a tasmota smart socket
 */
$host = gethostbyname('mosquitto');
$mqtthost = $host == 'mosquitto' ? '10.0.0.8' : 'mosquitto'; // Hostname used in docker, IP used when testing
echo "MQTT Host is $mqtthost (lookup=$host)\n";
$client = new \Ensemble\MQTT\Client($mqtthost, 1883);
$conf['devices'][] = $swrsocket = new Device\Socket\ShowerSocket("showersocket", $client, "socket4");
($conf['devices'][] = $swrsocket->getPowerMeter())->addDestination('global.context', 'power-shower');

// Tie shower socket to shower extractor
$conf['devices'][] = $extractor = new Device\Socket\TimedSocket("loftextractor", $client, "bathroom", "3"); // POWER3 on 'bathroom' MQTT device
$swrsocket->getStatus()->sub('SENSOR.ENERGY.POWER', array($extractor, 'trigger')); // Trigger the extractor when the socket current draw changes

// Wall extractor similar, but comes on AFTER the shower is turned off
$conf['devices'][] = $extractor = new Device\Socket\TimedSocket("wallextractor", $client, "bathroom", "4"); // POWER3 on 'bathroom' MQTT device
$swrsocket->getStatus()->sub('SENSOR.ENERGY.POWER', array($extractor, 'trigger')); // Trigger the extractor when the socket current draw changes
$extractor->setOffOnly();

// Turn the light on when the shower is switched on
$brlight = new Device\Socket\Socket("bathroomlight", $client, "bathroom", "1");
$swrsocket->getStatus()->sub('SENSOR.ENERGY.POWER', function($key, $value) use ($brlight) {
    if($value > 1) {
        $brlight->on();
    }
});


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
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-vent-office", $client, new Device\ContextPointer('global.schedules', 'daytimeoffpeak'), "socket5");
$socket->getDriver()->setTranslator(function($v) {
    $m = (int) date('m');
    return $m >= 5 && $m <= 9 ? $v : "OFF";
});
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-officevent');

// Tumble dryer
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-dryer", $client, new Device\ContextPointer('global.schedules', 'offpeak'), "socket1");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-dryer');

// Washing machine
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-washingmachine", $client, new Device\ContextPointer('global.schedules', 'offpeak_opoff'), "socket2");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-washingmachine');

// Dishwasher
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-dishwasher", $client, new Device\ContextPointer('global.schedules', 'offpeak_opoff'), "socket3");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-dishwasher');

// Network socket is for power monitoring only
$conf['devices'][] = $socket = new Device\Socket\Socket("socket-network", $client, "socket6");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-network');

// TV socket is for power monitoring only
$conf['devices'][] = $socket = new Device\Socket\Socket("socket-tv", $client, "socket7");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-tv');

// Pond pump
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-dryer", $client, new Device\ContextPointer('global.schedules', 'offpeak'), "socket11");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-pond');


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
 * Toilet Heater
 */
// Convert daily offpeak schedule to target temperatures
$sched_heat = $doffpeak->translate(function($s){
 return $s == 'ON' ? '17' : '10';
});
$sd_heat = new Schedule\DailyScheduler('electric_heat.scheduler', 'global.schedules', 'electric_heat', $sched_heat);
$conf['devices'][] = $sd_heat;

$ir1state = 'ir1-htr-temp-st';

// Set the initial heater state to 17, if the context field isn't set already
// This seems unreliable? maybe get is broken?
//if(count($s = $ctx->get($ir1state)) < 1) {
//    $ctx->update($ir1state, 17);
//}

// Configure the heater itself
$conf['devices'][] = $ir1 = new Device\IR\NettaHeater("ir1-heater", $client, "ir1", 'global.context', $ir1state);

// And add a driver to control the temperature
$conf['devices'][]  = $ir1driver = new Schedule\Driver($ir1, function($device, $temp) {
    $device->setTemperature($temp);
}, new Device\ContextPointer('global.schedules', 'electric_heat'));

// Link the light switch to turn the temperature up
$conf['devices'][] = $sw_toilet = new Device\Light\LightSwitch("switch-toilet", $client, "lightswitch2");

$sw_toilet->getStatus()->sub('STATE.POWER', function($key, $value) use ($sw_toilet, $ir1driver) {
    static $laststate = 'OFF';

    $sw_toilet->log("Status set to $value, previously $laststate");

    // Boost temperature when light switches ON (for three minutes)
    if($value == 'ON' && $laststate == 'OFF') {
        $sw_toilet->log("Boosting heater");
        $ir1driver->getOverride()->setPeriod(time(), time() + 240, 20);
    } elseif($value == 'OFF') {
        // Clear the override
        $ir1driver->getOverride()->setPeriod(0, time() + 3600, false);
    }

    $laststate = $value;

    $ir1driver->continue(); // Apply immediately
});


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

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-pump2", $client, new Device\ContextPointer('global.schedules', 'pump2'), "socket8");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-pump2');



/**
 * Immersion
 */
$conf['devices'][] = $isched = new Schedule\QuickSchedulerDevice('immersionscheduler');
$isched->setContext( 'global.schedules', 'immersionschedule');

// Triggered by the tariff device
$tariffdevice->setCallback(function($tariff) use ($isched) {

    echo "Immersion scheduler received tariff\n";
    $baseTariff = new Schedule\TariffSchedule($tariff);

    // Don't reschedule after 11pm because we'll overwrite with TOMORROW night's schedule!
    if(date('h') >= 23) {
        return;
    }


    // 3.5p/kwh ~= 3.03p/kwh / 90% efficiency
    $immersion = $baseTariff->between('23:00', '06:00')->lessThan(3.5)->cheapest(120)->getOnSchedule();
    echo $immersion->prettyPrint();
    $isched->setSchedule($immersion);

});

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("immersion", $client, new Device\ContextPointer('global.schedules', 'immersionschedule'), "immersion");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-immersion');
