<?php

/**
 * 10.0.107.1 - Heating & Hot Water
 */

namespace Ensemble\Device\Heating;
use Ensemble\GPIO as GPIO;

require __DIR__.'/home.php';

$heatsched  = new Schedule($heatstore = new \Ensemble\Storage\JsonStore('heating'));
$hwsched = new Schedule($hwstore = new \Ensemble\Storage\JsonStore('hwater'));

$heatstore->clear();
$hwstore->clear();

$heatpin = GPIO\Pin::phys(18, GPIO\Pin::OUT);
$heat = new HeatingDevice("heating.heat", new GPIO\Relay($heatpin), $heatsched);

$hwpin = GPIO\Pin::phys(16, GPIO\Pin::OUT);
$hw = new HeatingDevice("heating.water", new GPIO\Relay($hwpin), $hwsched);

// Queue registration with command broker
$conf['devices'][] = $hw;
$conf['devices'][] = $heat;


// Set basic schedule
$heatsched->addDaily('07:00', '09:00', 'ON');
$heatsched->addDaily('17:30', '19:30', 'ON');
$heatsched->addDaily('21:30', '22:30', 'ON');
$heatsched->addDaily('11:00', '13:00', 'ON', array(6,7)); // Come on at lunch on sat/sun

$hwsched->addDaily('06:30', '10:30', 'ON');
$hwsched->addDaily('17:30', '19:00', 'ON');
$hwsched->addDaily('21:30', '22:00', 'ON');
