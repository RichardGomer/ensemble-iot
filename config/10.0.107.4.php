<?php

/**
 * Irrigation controller
 */
namespace Ensemble;

use \Ensemble\Device\Irrigation as Ir;
use \Ensemble\GPIO\Pin;
use \Ensemble\GPIO\Relay;

require __DIR__.'/home.php';

// We use a local context to reduce network dependency
$ctx = new Device\ContextDevice("greenhouse.context");
$ctx->addSuperContext("global.context");
$conf['devices'][] = $ctx;

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


/**
 * Heating
 */
$bridge = new MQTT\Bridge(new MQTT\Client('10.0.0.8', 1883););
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-greenhouse", $bridge, new Device\ContextPointer('global.schedules', 'offpeak'), "socket9");
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
 * Irrigation
 */
$pump = new Ir\SoftStart(Pin::BCM(21, Pin::OUT), 100); # full pwoer pumping, using soft starter
$pumplow = new Ir\SoftStart(Pin::BCM(21, Pin::OUT), 40); # low power pumping mode
$flow = new Ir\FlowMeter(26);
$dosepump = new Relay(Pin::BCM(6, Pin::OUT));

$ps = new Ir\PressureSensor('irrigation.pressure', $pump);
$ps->addDestination('greenhouse.context', 'buttpressure');

$ic = new IR\IrrigationController('irrigation.controller', $flow);
$ic->setDestination('greenhouse.context'); // Send flow information to context broker

$do = new IR\IrrigationDoser('irrigation.doser', $dosepump, $flow, 4/3); // doser discharges 4/3ml per second

$ic->addChannel(1, new Relay(Pin::BCM(17, Pin::OUT)), $pump);
$ic->addChannel(2, new Relay(Pin::BCM(18, Pin::OUT)), $pump);
$ic->addChannel(3, new Relay(Pin::BCM(27, Pin::OUT)), $pump);
$ic->addChannel(4, new Relay(Pin::BCM(22, Pin::OUT)), $pump);

$ic->addChannel('h1', new Relay(Pin::BCM(13, Pin::OUT)), $pumplow);
$ic->addChannel('h2', new Relay(Pin::BCM(5, Pin::OUT)), $pumplow);
$ic->addChannel('h3', new Relay(Pin::BCM(12, Pin::OUT)), $pumplow);

$conf['devices'][] = $ic;
$conf['devices'][] = $ps;
$conf['devices'][] = $do;
