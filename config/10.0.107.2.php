<?php

/**
 * 10.0.107.2 - Temperature sensor receiver
 */

namespace Ensemble;

require __DIR__.'/home.php';

$sensors = new \Ensemble\Device\Temperature\OregonSensorSet("temperature.sensors");

$lounge = $sensors->getChannelSensor("temperature.lounge", 1);
$lounge->addDestination('global.context', 'temp-lounge');

$landing = $sensors->getChannelSensor("temperature.landing", 2);
$landing->addDestination('global.context', 'temp-landing');

$office = $sensors->getChannelSensor("temperature.office", 3);
$office->addDestination('global.context', 'temp-office');


$conf['devices'][] = $sensors;
$conf['devices'][] = $lounge;
$conf['devices'][] = $landing;
$conf['devices'][] = $office;
