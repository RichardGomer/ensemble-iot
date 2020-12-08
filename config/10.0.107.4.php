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
$conf['devices'][] = $miflora->getSensor("sense1_temp", 'temperature');
$conf['devices'][] = $miflora->getSensor("sense1_light", 'light');
$conf['devices'][] = $miflora->getSensor("sense1_batt", 'battery');
$conf['devices'][] = $miflora->getSensor("sense1_moisture", 'moisture');


/**
 * Heating
 */
$client = new MQTT\Client('10.0.0.8', 1883);
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-greenhouse", $client, "socket9", 'global.schedules', 'offpeak');
($conf['devices'][] = $socket->getPowerMeter())->addDestination('greenhouse.context', 'power-greenhouse');
$socket->getDriver()->setOverride('OFF', 365 * 24 * 3600); // Disable the heater until the driver can take over

$conf['devices'] = $heatdriver = new Device\ContextDriver($socket, function($value) use ($socket) {
    if($value > 5.2) { // When it's warm enough, disable the heater
        $socket->getDriver()->setOverride('OFF', 365 * 24 * 3600); // Disable for a long time; a little more failsafe?!
    } elseif ($value < 4.8) { // If it's too cool, allow the heater to come on (based on schedule)
        $socket->getDriver()->clearOverride();
    }
}, "greenhouse.context", "sense1_temp");


/**
 * Irrigation
 */
$pump = new Relay(Pin::BCM(23, Pin::OUT));
$flow = new Ir\FlowMeter(26);
$dosepump = new Relay(Pin::BCM(6, Pin::OUT));

$ps = new Ir\PressureSensor('irrigation.pressure', $pump);
$ps->addDestination('greenhouse.context', 'buttpressure');

$ic = new IR\IrrigationController('irrigation.controller', $pump, $flow);
$ic->setDestination('greenhouse.context'); // Send flow information to context broker

$do = new IR\IrrigationDoser('irrigation.doser', $dosepump, $flow, 4/3); // doser discharges 4/3ml per second

$ic->addChannel(1, new Relay(Pin::BCM(17, Pin::OUT)));
$ic->addChannel(2, new Relay(Pin::BCM(18, Pin::OUT)));
$ic->addChannel(3, new Relay(Pin::BCM(27, Pin::OUT)));
$ic->addChannel(4, new Relay(Pin::BCM(22, Pin::OUT)));

$conf['devices'][] = $ic;
$conf['devices'][] = $ps;
$conf['devices'][] = $do;
