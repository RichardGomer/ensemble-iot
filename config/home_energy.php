<?php

/**
 * Energy planning configuration
 *
 */

namespace Ensemble;
use Ensemble\Schedule;
use Ensemble\Device\ContextDevice;

require 'home_common.inc.php';

/**
 * Solcast solar forecast
 */
$conf['devices'][] = $solcast = new Device\EnergyPlan\SolcastDevice('solcast', $solcast_key, $solcast_site);
$solcast->setContext('global.context', 'solcast', SchedulerDevice::MODE_SERIES);

/**
 * Octopus Utility Data
 */
$oct = new Schedule\Octopus($octo_key);
$oct->setElecMeter($octo_elec_meter_mpan, $octo_elec_meter_serial);
$oct->setGasMeter($octo_gas_meter_mprn, $octo_gas_meter_serial);
$oct->setTariff($octo_prodcode, $octo_trfcode);

// Agile: $conf['devices'][] = $tariffdevice = new Schedule\OctopusTariffDevice('tariffscheduler', 'global.context', 'electariff', $oct);
$conf['devices'][] = $tariffdevice = new Schedule\OctopusGoTariffDevice('tariffscheduler', 'global.context', 'electariff'); // Go
$conf['devices'][] = new Schedule\OctopusGasUsageDevice('gasusagescheduler', 'global.context', 'gasusage', $oct);
$conf['devices'][] = new Schedule\OctopusElecUsageDevice('elecusagescheduler', 'global.context', 'elecusage', $oct);

/**
 * Scheduling!
 */
$conf['devices'][] = $sctx = new Device\ContextDevice('energy.schedules');

// Daytime
$daytime = new Schedule\Schedule();
$daytime->setPoint('00:00:00', 'OFF');
$daytime->setPoint('07:00:00', 'ON');
$daytime->setPoint('22:00:00', 'OFF');
$sd_daytime = new Schedule\DailyScheduler('daytime.scheduler', 'energy.schedules', 'daytime', $daytime);
$conf['devices'][] = $sd_daytime;

// Short Daytime
$shdaytime = new Schedule\Schedule();
$shdaytime->setPoint('00:00:00', 'OFF');
$shdaytime->setPoint('07:00:00', 'ON');
$shdaytime->setPoint('17:00:00', 'OFF');
$sd_shdaytime = new Schedule\DailyScheduler('shortdaytime.scheduler', 'energy.schedules', 'shortdaytime', $shdaytime);
$conf['devices'][] = $sd_shdaytime;

// offpeak
$offpeak = new Schedule\Schedule();
$offpeak->setPoint('00:00:00', 'OFF');
$offpeak->setPoint('00:30:00', 'ON');
$offpeak->setPoint('04:30:00', 'OFF');
$sd_offpeak = new Schedule\DailyScheduler('offpeak.scheduler', 'energy.schedules', 'offpeak', $offpeak);
$conf['devices'][] = $sd_offpeak;

// offpeak oppoff
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'OFF');
$bsched->setPoint('00:30:00', 'ON');
$bsched->setPoint('02:30:30', 'OPOFF');
$bsched->setPoint('05:00:00', 'OFF');
$sd_opoff = new Schedule\DailyScheduler('offpeak_opoff.scheduler', 'energy.schedules', 'offpeak_opoff', $bsched);
$conf['devices'][] = $sd_opoff;


/**
* Attach sockets to schedules
*/

$host = gethostbyname('mosquitto');
$mqtthost = $host == 'mosquitto' ? '10.0.0.8' : 'mosquitto'; // Hostname used in docker, IP used when testing
echo "MQTT Host is $mqtthost (lookup=$host)\n";
$client = new MQTT\Client($mqtthost, 1883);
$conf['devices'][] = $bridge = new MQTT\Bridge('_energy.mqttbridge', $client);


// Office ventilator
// Uses the daytime offpeak schedule, but translates to only be active April - September
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-vent-office", $bridge, new Device\ContextPointer('energy.schedules', 'daytime'), "socket5");
$socket->getDriver()->setTranslator(function($v) {
 $m = (int) date('m');
 return $m >= 4 && $m <= 9 ? $v : "OFF"; // Only run April to September
});
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-officevent');




// Tumble dryer
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-dryer", $bridge, new Device\ContextPointer('energy.schedules', 'offpeak'), "socket1");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-dryer');


// Washing machine
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-washingmachine", $bridge, new Device\ContextPointer('energy.schedules', 'offpeak_opoff'), "socket2");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-washingmachine');


// Dishwasher
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-dishwasher", $bridge, new Device\ContextPointer('energy.schedules', 'offpeak_opoff'), "socket3");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-dishwasher');


// Network socket is for power monitoring only
$conf['devices'][] = $socket = new Device\Socket\Socket("socket-network", $bridge, "socket6");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-network');


// TV socket is for power monitoring only
$conf['devices'][] = $socket = new Device\Socket\Socket("socket-tv", $bridge, "socket7");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-tv');



/**
 * Toilet light on schedule
 */
$conf['devices'][] = $sw_toilet = new Device\Light\LightSwitch("switch-toilet", $bridge, "lightswitch2");
$conf['devices'][] = $toiletswdriver = new Schedule\Driver($sw_toilet, function($sw, $status, $time){ if($status == 'ON') { $sw->on(); } elseif($status == 'OFF' && $time > time() - 15) { $sw->off(); } }, new Device\ContextPointer("energy.schedules", "shortdaytime"));


/**
* Toilet Heater
*/
// Convert daily offpeak schedule to target temperatures
$sched_heat = $offpeak->translate(function($s){
    return $s == 'ON' ? '17' : '10';
});
$sd_heat = new Schedule\DailyScheduler('electric_heat.scheduler', 'energy.schedules', 'electric_heat', $sched_heat);
$conf['devices'][] = $sd_heat;

$ir1state = 'ir1-htr-temp-st';

// Set the initial heater state to 17, if the context field isn't set already
// This seems unreliable? maybe get is broken?
//if(count($s = $ctx->get($ir1state)) < 1) {
//    $ctx->update($ir1state, 17);
//}

/*
// Configure the heater itself
$conf['devices'][] = $ir1 = new Device\IR\NettaHeater("ir1-heater", $bridge, "ir1", 'global.context', $ir1state);

// And add a driver to control the temperature
$conf['devices'][]  = $ir1driver = new Schedule\Driver($ir1, function($device, $temp) {
 $device->setTemperature($temp);
}, new Device\ContextPointer('energy.schedules', 'electric_heat'));
*/

/**
 * Immersion
 * TODO: Use the immersion when there is spare solar generation
 */
$conf['devices'][] = $isched = new Schedule\QuickSchedulerDevice('immersionscheduler');
$isched->setContext( 'energy.schedules', 'immersionschedule');

// Triggered by the tariff device
$tariffdevice->setCallback(function($tariff) use ($isched) {

    echo "Immersion scheduler received tariff\n";
    $baseTariff = new Schedule\TariffSchedule($tariff);

    // Don't reschedule after 11pm because we'll overwrite with TOMORROW night's schedule!
    if(date('H') >= 23 || date('H') < 8) {
        $isched->log("Rescheduling won't occur between 2300 and 0800");
        return;
    }

    // 3.5p/kwh ~= 3.03p/kwh / 90% efficiency
    $immersion = $baseTariff->between('23:00', '06:00')->lessThan(3.5)->cheapest(120)->getOnSchedule();

    // For immersion as primary hot water source, use the below
    //$immersion1_t = $baseTariff->between('23:00', '08:00')->cheapest(180);
    //$immersion1 = $immersion1_t->getOnSchedule();
    //$isched->log("Night period\n".$immersion1_t->prettyPrint()."\n".$immersion1->prettyPrint());

    //$immersion2_t = $baseTariff->between('19:00', '22:00')->cheapest(30);
    //$immersion2 = $immersion2_t->getOnSchedule();
    //$isched->log("Evening period\n".$immersion2_t->prettyPrint()."\n".$immersion2->prettyPrint());

    //$immersion = $immersion1->or($immersion2);

    $isched->log("Generated immersion schedule\n".$immersion->prettyPrint());
    $isched->setSchedule($immersion);
});

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("immersion", $bridge, new Device\ContextPointer('energy.schedules', 'immersionschedule'), "immersion");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-immersion');


/**
 * Pond pump
 */
$pondsched = new Schedule\Schedule();
$pondsched->setPoint('00:00:00', 'OFF');

for($i = 0; $i < 24; $i++) {
    $pondsched->setPeriod(sprintf('%02d:00:00', $i), sprintf('%02d:15:00', $i), 'ON');
}

$sch_pondsched = $conf['devices'][] = new Schedule\DailyScheduler('pondpump.scheduler', 'energy.schedules', 'pondpump_schedule', $pondsched);

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("pondpump", $bridge, new Device\ContextPointer('energy.schedules', 'pondpump_schedule'), "socket13");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-pondpump');
