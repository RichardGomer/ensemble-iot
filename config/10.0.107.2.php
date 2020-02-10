<?php

/**
 * 10.0.107.2 - Temperature sensor receiver
 */

namespace Ensemble;

$sensors = new \Ensemble\Device\Temperature\OregonSensorSet("temperature.sensors");

$lounge = $sensors->getChannelSensor("temperature.sensors.lounge", 1);
$lounge->addDestination('home.context');

$landing = $sensors->getChannelSensor("temperature.sensors.landing", 2);
$landing->addDestination('home.context');

$office = $sensors->getChannelSensor("temperature.sensors.office", 3);
$office->addDestination('home.context');


$conf['devices'][] = $sensors;
$conf['devices'][] = $lounge;
$conf['devices'][] = $landing;
$conf['devices'][] = $office;
