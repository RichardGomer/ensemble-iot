<?php

/**
 * Lighting configuration
 *
 */

namespace Ensemble;
use Ensemble\Schedule;
use Ensemble\Device\Light as Light;
use Ensemble\Device\ContextPointer as ContextPointer;

require 'home_common.inc.php';

$conf['devices'][] = $sctx = new Device\ContextDevice('lighting.schedules');

/**
 * Smart lights
 */
$lsched = new Schedule\Schedule();
$lsched->setPoint('00:00:00', 'auto 30%');
$lsched->setPoint('05:00:00', 'auto 30%');
$lsched->setPoint('06:00:00', 'auto 100%');
$lsched->setPoint('21:30:00', 'auto 100%');
$lsched->setPoint('23:00:00', 'auto 30%');

$sd_lights = new Schedule\DailyScheduler('light.scheduler', 'lighting.schedules', 'daylightschedule', $lsched);
$conf['devices'][] = $sd_lights;

// Create a socket to be controlled and bind it to the schedule in the broker
$conf['devices'][] = $socket = new Light\TasmotaRGBWCT("light1", $bridge, "light1");
$conf['devices'][] = new Light\RGBWCTDriver($socket, new ContextPointer('lighting.schedules', 'daylightschedule'));

$conf['devices'][] = $socket = new Light\TasmotaRGBWCT("light2", $bridge, "light2");
$conf['devices'][] = new Light\RGBWCTDriver($socket, new ContextPointer('lighting.schedules', 'daylightschedule'));

$conf['devices'][] = $socket = new Light\TasmotaRGBWCT("light3", $bridge, "light3");
$conf['devices'][] = new Light\RGBWCTDriver($socket, new ContextPointer('lighting.schedules', 'daylightschedule'));


/**
 * Kitchen
 */
$conf['devices'][] = $kml = new Light\MultiLight('kitchenlights');
$kml->addSwitch($conf['devices'][] = new Light\LightSwitch('kitchen-switch-1-3', $bridge, "kitchen-switch1", "3"));
$kml->addSwitch($conf['devices'][] = new Light\LightSwitch('kitchen-switch-2-2', $bridge, "kitchen-switch2", "2"));
$kml->addSwitch($conf['devices'][] = new Light\LightSwitch('kitchen-switch-3-2', $bridge, "kitchen-switch3", "2"));

for($i = 1; $i <= 6; $i++) {
    $kml->addLight($conf['devices'][] = new Light\TasmotaRGBWCT("kitchen-light-{$i}", $bridge, "kitchen-led{$i}"));
}

$kml->addLight($conf['devices'][] = new Light\WLED("kitchen-wled-sink", "10.0.107.213"));

$conf['devices'][] = new Light\RGBWCTDriver($kml, new ContextPointer('lighting.schedules', 'daylightschedule'));



/**
 * Dining Room
 */
$conf['devices'][] = $dml = new Light\MultiLight('dininglights');
$dml->addSwitch($conf['devices'][] = new Light\LightSwitch('kitchen-switch-1-2', $bridge, "kitchen-switch1", "2"));
$dml->addSwitch($conf['devices'][] = new Light\LightSwitch('kitchen-switch-2-1', $bridge, "kitchen-switch2", "1"));
$dml->addSwitch($conf['devices'][] = new Light\LightSwitch('kitchen-switch-3-1', $bridge, "kitchen-switch3", "1"));

for($i = 7; $i <= 9; $i++) {
    $dml->addLight($conf['devices'][] = new Light\TasmotaRGBWCT("kitchen-light-{$i}", $bridge, "kitchen-led{$i}"));
}

$conf['devices'][] = new Light\RGBWCTDriver($dml, new ContextPointer('lighting.schedules', 'daylightschedule'));
