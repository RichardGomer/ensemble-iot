<?php

/**
 * Lighting configuration
 *
 */

namespace Ensemble;
use Ensemble\Schedule;
use Ensemble\Device\Light as Light;
use Ensemble\Device\ContextPointer as ContextPointer;
use Ensemble\Device\Light\MultiLight;
use Ensemble\Device\Socket\Socket;
use Ensemble\Schedule\Driver;

require 'home_common.inc.php';

$conf['devices'][] = $lsctx = new Device\ContextDevice('lighting.schedules');
$conf['devices'][] = $lctx = new Device\ContextDevice('lighting.context');

$lctx->update('scheme', '___default', 100); // 100 is the default priority

/**
 * Smart lights
 */
$lsched = new Schedule\Schedule();
$lsched->setPoint('00:00:00', 'auto 30%');
$lsched->setPoint('05:00:00', 'auto 30%');
$lsched->setPoint('06:00:00', 'auto 100%');
$lsched->setPoint('20:00:00', 'auto 100%');
$lsched->setPoint('23:00:00', 'auto 30%');

$sd_lights = new Schedule\DailyScheduler('light.scheduler', 'lighting.schedules', 'daylightschedule', $lsched);
$conf['devices'][] = $sd_lights;

// Create a socket to be controlled and bind it to the schedule in the broker
$conf['devices'][] = $socket = new Light\TasmotaRGBWCT("light1", $bridge, "light1"); // Rear hallway
$conf['devices'][] = new Light\RGBWCTDriver($socket, new ContextPointer('lighting.schedules', 'daylightschedule'));

$conf['devices'][] = $socket = new Light\TasmotaRGBWCT("light2", $bridge, "light2"); // Front hallway
$conf['devices'][] = new Light\RGBWCTDriver($socket, new ContextPointer('lighting.schedules', 'daylightschedule'));

$conf['devices'][] = $socket = new Light\TasmotaRGBWCT("light3", $bridge, "light3"); // Landing
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

$conf['devices'][] = $kitchendriver = new Light\RGBWCTDriver($kml, new ContextPointer('lighting.schedules', 'daylightschedule'));



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

$conf['devices'][] = $diningdriver = new Light\RGBWCTDriver($dml, new ContextPointer('lighting.schedules', 'daylightschedule'));

// Embedded LEDs
$clouds = <<<END
{"on":true,"bri":255,"transition":7,"mainseg":0,"seg":[{"id":0,"start":0,"stop":244,"grp":1,"spc":0,"of":0,"on":true,"bri":255,"cct":127,"col":[[255,160,0],[0,0,0],[0,0,0]],"fx":9,"sx":2,"ix":129,"pal":7,"sel":true,"rev":false,"mi":false},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0}]}
END;

$flicker = <<<END
{"on":true,"bri":110,"transition":7,"mainseg":0,"seg":[{"id":0,"start":0,"stop":244,"grp":1,"spc":0,"of":0,"on":true,"bri":255,"cct":127,"col":[[255,185,87],[107,70,35],[0,0,0]],"fx":109,"sx":106,"ix":230,"pal":3,"sel":true,"rev":false,"mi":false},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0},{"stop":0}]}
END;

$dlsched = new Schedule\Schedule();
$dlsched->setPoint('00:00:00', $flicker);
$dlsched->setPoint('05:00:00',  $clouds);
$dlsched->setPoint('20:30:00', $flicker);

$sd_dleds = new Schedule\DailyScheduler('diningled.scheduler', 'lighting.schedules', 'diningledsschedule', $dlsched);
$conf['devices'][] = $sd_dleds;


$conf['devices'][] = $leds = new Light\WLED('diningleds', '10.0.107.212');
$conf['devices'][] = $dleddriver = new Driver($leds, function($target, $current, $currentStart, $next, $nextStart) { 
    $target->setScheme($current);
}, new ContextPointer('lighting.schedules', 'diningledsschedule'));


/**
 * Some alternative schemes
 */
$drivers = array($kitchendriver, $diningdriver);
$schemes = array();

// Rainbow Scheme
$conf['devices'][] = $sd_lights = new Light\RainbowScheduler('rainbow.scheduler', 'lighting.schedules', 'rainbowschedule', 60); // Full cycle = 60 seconds
$schemes['rainbow'] = 'rainbowschedule';

// Cosy scheme
$lsched = new Schedule\Schedule();
$lsched->setPoint('00:00:00', '450 60'); // temperature, brightness percentage
$lsched->setPoint('23:59:00', '450 60'); // temperature, brightness percentage
$conf['devices'][] = $sd_lights = new Schedule\DailyScheduler('cosy.scheduler', 'lighting.schedules', 'cosyschedule', $lsched);
$schemes['cosy'] = 'cosyschedule';

// TODO: Also set up schemes for WLED here; same names, fallback to default if there isn't one

// Add alternative schemes to the ScheduleDriver
foreach($schemes as $sname=>$field) {
    foreach($drivers as $d) {
        $d->addScheme($sname, new ContextPointer('lighting.schedules', $field));
    }
}

/**
 * Create an action controller to switch schemes
 */
$conf['devices'][] = $actions = new \Ensemble\Actions\Controller("lighting.actions");

// Set up an action to switch to each scheme
foreach($schemes as $s=>$x) {
    (function($s) use ($actions, $drivers) { // Trap s in a closure
        $actions->expose("set_{$s}", function() use ($drivers, $s) {
            echo "*** Setting scheme on drivers to $s\n";
            foreach($drivers as $d) {
                $d->setScheme($s);
            }
        }, (new \Ensemble\Actions\Documentation())->notes("Enable the {$s} lighting scheme"));
    })($s);
}

// And an action to restore the default scheme
$actions->expose("set_default", function() use ($drivers) {
    foreach($drivers as $d) {
        $d->setScheme(false);
    }
}, (new \Ensemble\Actions\Documentation())->notes("Reset the {$s} lighting scheme to default"));

//echo json_encode($actions->getDocs());


/**
 * Bathroom
 */
$conf['devices'][] = $bled = new Light\WLED("bathroom-wled", "10.0.107.211");
$conf['devices'][] = $bledsw = new Light\LightSwitch("bathroom-led-pwr", $bridge, "bathroom", "2"); // Channel 2 on the bathroom 4CH
$bledsw->on(); // Bathroom LEDs default to on
$bled->on();
$conf['devices'][] = new Light\Rebooter($bledsw); // Reboot the bathroom LEDs once per day
$conf['devices'][] = new Light\RGBWCTDriver($bled, new ContextPointer('lighting.schedules', 'daylightschedule')); // Set colour based on daylight schedule

// Ceiling lights
$conf['devices'][] = $bsw = new Light\LightSwitch("bathroom-switch", $bridge, "lightswitch3");
$conf['devices'][] = $blp = new Light\LightSwitch("bathroom-light-pwr", $bridge, "bathroom", "2"); // Channel 1 on the bathroom 4CH
$conf['devices'][] = $bl1 = new Light\MultiLight('bathroomlights'); // We use a multilight to sync the wall switch with the 4ch
$bl1->addSwitch($bsw);
$bl1->addSwitch($blp);