<?php

/**
 * Irrigation controller
 */
use \Ensemble\Device\Irrigation as Ir;
use \Ensemble\GPIO\Pin;
use \Ensemble\GPIO\Relay;

require __DIR__.'/home.php';

$pump = new Relay(Pin::BCM(23, Pin::OUT));
$flow = new Ir\FlowMeter(26);
$dosepump = new Relay(Pin::BCM(6, Pin::OUT));

$ps = new Ir\PressureSensor('irrigation.pressure', $pump);
$ps->addDestination('global.context', 'buttpressure');

$ic = new IR\IrrigationController('irrigation.controller', $pump, $flow);
$ic->setDestination('global.context'); // Send flow information to context broker

$do = new IR\IrrigationDoser('irrigation.doser', $dosepump, $flow, 4/3); // doser discharges 4/3ml per second


$ic->addChannel(1, new Relay(Pin::BCM(17, Pin::OUT)));
$ic->addChannel(2, new Relay(Pin::BCM(18, Pin::OUT)));
$ic->addChannel(3, new Relay(Pin::BCM(27, Pin::OUT)));
$ic->addChannel(4, new Relay(Pin::BCM(22, Pin::OUT)));

$conf['devices'][] = $ic;
$conf['devices'][] = $ps;
$conf['devices'][] = $do;
