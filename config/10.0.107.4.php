<?php

/**
 * Irrigation controller
 */
use Ensemble\Device\Irrigation as Ir;
use Ensemble\GPIO as GPIO;

$pump = new GPIO\Relay(23);
$flow = new Ir\FlowMeter(26);
$log = new Ensemble\Log\TextLog("irrigation.log");

$ic = $MOD['icontrol'] = new IR\Irrigation($pump, $flow, $log);

$ic->addChannel(1, new GPIO\Relay(GPIO\Pin::BCM(17)));
$ic->addChannel(2, new GPIO\Relay(GPIO\Pin::BCM(18)));
$ic->addChannel(3, new GPIO\Relay(GPIO\Pin::BCM(27)));
$ic->addChannel(4, new GPIO\Relay(GPIO\Pin::BCM(22)));
