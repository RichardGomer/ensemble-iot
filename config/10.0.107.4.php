<?php

/**
 * Irrigation controller
 */
use Ensemble\Device\Irrigation as Ir;
use Ensemble\GPIO\Pin;
use Ensemble\GPIO\Relay;

require __DIR__.'/home.php';

$pump = new Relay(Pin::BCM(23, Pin::OUT));
$flow = new Ir\FlowMeter(26);

$ic = new IR\IrrigationController('irrigation.controller', $pump, $flow);

$ic->addChannel(1, new Relay(Pin::BCM(17, Pin::OUT)));
$ic->addChannel(2, new Relay(Pin::BCM(18, Pin::OUT)));
$ic->addChannel(3, new Relay(Pin::BCM(27, Pin::OUT)));
$ic->addChannel(4, new Relay(Pin::BCM(22, Pin::OUT)));

$conf['devices'][] = $ic;
