<?php

/**
 * Irrigation controller
 */
namespace Ensemble;

use \Ensemble\Device\Irrigation as Ir;
use \Ensemble\GPIO\Pin;
use \Ensemble\GPIO\Relay;

use Ensemble\Device\W1\TemperatureSensor;

date_default_timezone_set('Europe/London');
$conf['default_endpoint'] = 'http://10.0.0.8:3107/ensemble-iot/1.0/';


// We use a local context to reduce network dependency
$ctx = new Device\ContextDevice("greenhouse.context");
$ctx->addSuperContext("global.context");
$conf['devices'][] = $ctx;

// A local schedule context too
$conf['devices'][] = $sctx = new Device\ContextDevice('greenhouse.schedules');

/**
 * Bluetooth data logger
 */
$conf['devices'][] = $miflora = new Device\Miflora\MifloraDevice("sense1", "c4:7c:8d:6b:d3:48");
$conf['devices'][] = $s = $miflora->getSensor("sense1_temp", 'temperature');
$s->addDestination("greenhouse.context", "greenhouse-temp");
$conf['devices'][] = $s = $miflora->getSensor("sense1_light", 'light');
$s->addDestination("greenhouse.context", "greenhouse-light");
$conf['devices'][] = $s = $miflora->getSensor("sense1_batt", 'battery');
$s->addDestination("greenhouse.context", "sense1-batt");
$conf['devices'][] = $s = $miflora->getSensor("sense1_moisture", 'moisture');
$s->addDestination("greenhouse.context", "greenhouse-moisture");

$conf['devices'][] = $miflora = new Device\Miflora\MifloraDevice("sense2", "c4:7c:8d:6c:b2:c0");
$conf['devices'][] = $s = $miflora->getSensor("sense2_temp", 'temperature');
$s->addDestination("greenhouse.context", "coldframe-temp");
$conf['devices'][] = $s = $miflora->getSensor("sense2_light", 'light');
$s->addDestination("greenhouse.context", "coldframe-light");
$conf['devices'][] = $s = $miflora->getSensor("sense2_batt", 'battery');
$s->addDestination("greenhouse.context", "sense2-batt");
$conf['devices'][] = $s = $miflora->getSensor("sense2_moisture", 'moisture');
$s->addDestination("greenhouse.context", "coldframe-moisture");

$conf['devices'][] = $miflora = new Device\Miflora\MifloraDevice("sense3", "c4:7c:8d:6d:f7:b2");
$conf['devices'][] = $s = $miflora->getSensor("sense3_temp", 'temperature');
$s->addDestination("greenhouse.context", "raisedbed-temp");
$conf['devices'][] = $s = $miflora->getSensor("sense3_light", 'light');
$s->addDestination("greenhouse.context", "raisedbed-light");
$conf['devices'][] = $s = $miflora->getSensor("sense3_batt", 'battery');
$s->addDestination("greenhouse.context", "sense3-batt");
$conf['devices'][] = $s = $miflora->getSensor("sense3_moisture", 'moisture');
$s->addDestination("greenhouse.context", "raisedbed-moisture");


/**
 * Heating
 */
$conf['devices'][] = $bridge = new MQTT\Bridge('_greenhouse.mqttbridge', new MQTT\Client('10.0.0.8', 1883));

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-greenhouse", $bridge, new Device\ContextPointer('energy.schedules', 'offpeak'), "socket9");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('greenhouse.context', 'power-greenhouse');
$socket->getDriver()->setOverride('OFF', 365 * 24 * 3600); // Disable the heater until the driver can take over

$conf['devices'][] = $heatdriver = new Device\ContextDriver($socket, function($socket, $value) {
    echo "temp is $value\n";
    if($value > 5.2) { // When it's warm enough, disable the heater
        $socket->getDriver()->setOverride('OFF', 365 * 24 * 3600); // Disable for a long time; a little more failsafe?!
    } elseif ($value < 4.8) { // If it's too cool, allow the heater to come on (based on schedule)
        $socket->getDriver()->clearOverride(365*24*3600+100);
    }
}, new Device\ContextPointer("greenhouse.context", "greenhouse-temp"));


/**
 * Greenhouse Growlight
 */
// Use a custom schedule, 0600-1600 = 10hours of light per day
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'OFF');
$bsched->setPoint('06:00:00', 'ON');
$bsched->setPoint('16:00:00', 'OFF');
$sd_growlight = new Schedule\DailyScheduler('growlight1.scheduler', 'greenhouse.schedules', 'growlight', $bsched);
$conf['devices'][] = $sd_growlight;

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-growlight1", $bridge, new Device\ContextPointer('greenhouse.schedules', 'growlight'), "socket12");
($conf['devices'][] = $socket->getPowerMeter())->addDestination('greenhouse.context', 'power-growlight');


/**
 * Irrigation
 */
$pump = new Ir\SoftStart(Pin::BCM(21, Pin::OUT), 90); # full pwoer pumping, using soft starter
$pumpmed = new Ir\SoftStart(Pin::BCM(21, Pin::OUT), 60); # low power pumping mode
$pumplow = new Ir\SoftStart(Pin::BCM(21, Pin::OUT), 40); # v. low power pumping mode

$flow = new Ir\FlowMeter(26);
$dosepump = new Relay(Pin::BCM(6, Pin::OUT));

$ps = new Ir\PressureSensor('irrigation.pressure', $pump);
$ps->addDestination('greenhouse.context', 'buttpressure');

$ic = new IR\IrrigationController('irrigation.controller', $flow);
$ic->setDestination('greenhouse.context'); // Send flow information to context broker

$do = new IR\IrrigationDoser('irrigation.doser', $dosepump, $flow, 4/3); // doser discharges 4/3ml per second

$ic->addChannel(1, new Relay(Pin::BCM(17, Pin::OUT)), $pumpmed);
$ic->addChannel(2, new Relay(Pin::BCM(18, Pin::OUT)), $pumpmed);
$ic->addChannel(3, new Relay(Pin::BCM(27, Pin::OUT)), $pumplow);
$ic->addChannel(4, new Relay(Pin::BCM(22, Pin::OUT)), $pumpmed);

$ic->addChannel('h', new Relay(array($h1 = Pin::BCM(13, Pin::OUT), $h2 = Pin::BCM(5, Pin::OUT), $h3 = Pin::BCM(12, Pin::OUT))), $pumplow);
$ic->addChannel('h1', new Relay($h1), $pumplow);
$ic->addChannel('h2', new Relay($h2), $pumplow);
$ic->addChannel('h3', new Relay($h3), $pumplow);



//$ic->addChannel('h2', new Relay(Pin::BCM(5, Pin::OUT)), $pumplow);
//$ic->addChannel('h3', new Relay(Pin::BCM(12, Pin::OUT)), $pumplow);

$conf['devices'][] = $ic;
$conf['devices'][] = $ps;
$conf['devices'][] = $do;


// Onboard temperature sensors
$ts1 = new TemperatureSensor('greenhouse.w1temp-internal', '28-3c01d60775a5');
$ts1->addDestination('global.context', 'greenhouse.temperature');
$conf['devices'][] = $ts1;

$ts2 = new TemperatureSensor('greenhouse.w1temp-external', '28-3c01d60742ac');
$ts2->addDestination('global.context', 'outdoor.temperature');
$conf['devices'][] = $ts2;