<?php

/**
 * 10.0.107.2 - Temperature sensor receiver
 */

namespace Ensemble;

require __DIR__.'/home.php';

$sensors = new \Ensemble\Device\Temperature\OregonSensorSet("temperature.sensors");

$lounge = $sensors->getChannelSensor("temperature.lounge", 1);
$lounge->addDestination('global.context');

$landing = $sensors->getChannelSensor("temperature.landing", 2);
$landing->addDestination('global.context');

$office = $sensors->getChannelSensor("temperature.office", 3);
$office->addDestination('global.context');


$conf['devices'][] = $sensors;
$conf['devices'][] = $lounge;
$conf['devices'][] = $landing;
$conf['devices'][] = $office;
