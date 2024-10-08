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
$conf['devices'][] = $light1 = new Light\TasmotaRGBWCT("light1", $bridge, "light1"); // Rear hallway
$conf['devices'][] = $l1driver = new Light\RGBWCTDriver($light1, new ContextPointer('lighting.schedules', 'daylightschedule'));

$conf['devices'][] = $light2 = new Light\TasmotaRGBWCT("light2", $bridge, "light2"); // Front hallway
$conf['devices'][] = $l2driver = new Light\RGBWCTDriver($light2, new ContextPointer('lighting.schedules', 'daylightschedule'));

$conf['devices'][] = $light3 = new Light\TasmotaRGBWCT("light3", $bridge, "light3"); // Landing
$conf['devices'][] = $l3driver = new Light\RGBWCTDriver($light3, new ContextPointer('lighting.schedules', 'daylightschedule'));


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
 * Toilet light on schedule
 */
$conf['devices'][] = $sw_toilet = new Device\Light\LightSwitch("switch-toilet", $bridge, "lightswitch2");
$conf['devices'][] = $toiletswdriver = new Schedule\Driver($sw_toilet, function($sw, $status, $time){ if($status == 'ON') { $sw->on(); } elseif($status == 'OFF' && $time > time() - 15) { $sw->off(); } }, new Device\ContextPointer("energy.schedules", "shortdaytime"));



/**
 * Utility + Rear Hall
 */
$conf['devices'][] = $uml = new Light\MultiLight('utilitylights');

$uml->addLight($conf['devices'][] = new Light\WLED("utility-wled-sink", "10.0.107.216"));

$conf['devices'][] = $sw_utility = new Light\LightSwitch("utility-switch", $bridge, "utility-switch", "1"); // The channel is named 1, even though there's only one; OpenBeken...
$conf['devices'][] = $sw_rearhall = new Light\LightSwitch("rearhall-switch-2", $bridge, "rearhall-switch", "2"); // Channel 2 on the rearhall 2CH switch
$uml->addSwitch($sw_utility);
$uml->addSwitch($sw_rearhall);

$conf['devices'][] = $utilitydriver = new Light\RGBWCTDriver($uml, new ContextPointer('lighting.schedules', 'daylightschedule')); 


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
$dlsched->setPoint('05:00:00',  "@dawn ".$clouds);
$dlsched->setPoint('18:00:00', "@dusk ".$flicker);

$sd_dleds = new Schedule\DailyScheduler('diningled.scheduler', 'lighting.schedules', 'diningledsschedule', $dlsched, $LAT, $LNG);
$conf['devices'][] = $sd_dleds;


$conf['devices'][] = $leds = new Light\WLED('diningleds', '10.0.107.212');
$conf['devices'][] = $dleddriver = new Driver($leds, function($target, $current, $currentStart, $next, $nextStart) { 
    $target->setScheme($current);
}, new ContextPointer('lighting.schedules', 'diningledsschedule'));


/**
 * Some alternative schemes
 */
$drivers = array($kitchendriver, $utilitydriver, $diningdriver, $l1driver, $l2driver, $l3driver);
$schemes = array();

// Rainbow Scheme
$conf['devices'][] = $sd_lights = new Light\RainbowScheduler('rainbow.scheduler', 'lighting.schedules', 'rainbowschedule', 60); // Full cycle = 60 seconds
$schemes['rainbow'] = 'rainbowschedule';

// Cosy scheme
$lsched = new Schedule\Schedule();
$lsched->setPoint('00:00:00', '450 60'); // temperature, brightness percentage
$lsched->setPoint('23:59:59', '450 60'); // temperature, brightness percentage
$conf['devices'][] = $sd_lights = new Schedule\DailyScheduler('cosy.scheduler', 'lighting.schedules', 'cosyschedule', $lsched);
$schemes['cosy'] = 'cosyschedule';

// Violet scheme
$lsched = new Schedule\Schedule();
$lsched->setPoint('00:00:00', '210,94,255 50');
$lsched->setPoint('23:59:59', '210,94,255 50');
$conf['devices'][] = $sd_lights = new Schedule\DailyScheduler('violet.scheduler', 'lighting.schedules', 'violetschedule', $lsched);
$schemes['violet'] = 'violetschedule';

// Autumn scheme
$lsched = new Schedule\Schedule();
$lsched->setPoint('00:00:00', '209,126,48 50');
$lsched->setPoint('23:59:59', '209,126,48 50');
$conf['devices'][] = $sd_lights = new Schedule\DailyScheduler('autumn.scheduler', 'lighting.schedules', 'autumnschedule', $lsched);
$schemes['autumn'] = 'autumnschedule';


// TODO: Also set up schemes for WLED here; same names, fallback to default if there isn't one

// Add alternative schemes to the ScheduleDriver
foreach($schemes as $sname=>$field) {
    foreach($drivers as $d) {
        $d->addScheme($sname, new ContextPointer('lighting.schedules', $field));
    }
}


/**
 * Bathroom
 */
$conf['devices'][] = $bled = new Light\WLED("bathroom-wled", "10.0.107.211");
$conf['devices'][] = $bledsw = new Light\LightSwitch("bathroom-led-pwr", $bridge, "bathroom", "2"); // Channel 1 on the bathroom 4CH
$bledsw->on(); // Bathroom LEDs default to on
$bled->on();
$conf['devices'][] = new Light\Rebooter($bledsw); // Reboot the bathroom LEDs once per day
$conf['devices'][] = new Light\RGBWCTDriver($bled, new ContextPointer('lighting.schedules', 'daylightschedule')); // Set colour based on daylight schedule

// Ceiling lights
$conf['devices'][] = $bsw = new Light\LightSwitch("bathroom-switch", $bridge, "lightswitch3");
$conf['devices'][] = $blp = new Light\LightSwitch("bathroom-light-pwr", $bridge, "bathroom", "1"); // Channel 1 on the bathroom 4CH
$conf['devices'][] = $bl1 = new Light\MultiLight('bathroomlights'); // We use a multilight to sync the wall switch with the 4ch
$bl1->addSwitch($bsw);
$bl1->addSwitch($blp);


/**
 * Garden lights
 */
$conf['devices'][] = $glight = new Light\LightSwitch('gardenlights-socket', $bridge, 'socket15'); // Garden lights are just a socket
$conf['devices'][] = $gdns   = new Light\LightSwitch("rearhall-switch-1", $bridge, "rearhall-switch", "1"); // Channel 1 on the rearhall 2CH switch
$conf['devices'][] = $gdnml = new Light\MultiLight('gardenlights'); // We use a multilight to sync the wall switch with the socket
$gdnml->addSwitch($glight);
$gdnml->addSwitch($gdns);



/**
 * Create an action controller to do lighting things
 */
$conf['devices'][] = $actions = new \Ensemble\Actions\Controller("lighting.actions");

// Set up an action to switch to each indoor scheme
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
}, (new \Ensemble\Actions\Documentation())->notes("Reset the lighting scheme to default"));


// An action to turn all the (software-switched) lights off
$actions->expose("off", function() use ($kml, $dml, $uml, $leds, $bl1, $glight) {
    $kml->off();
    $dml->off();
    $uml->off();
    $leds->off();
    $bl1->off();
    $glight->off();

}, (new \Ensemble\Actions\Documentation())->notes("Turn off all the lights"));

//echo json_encode($actions->getDocs());
