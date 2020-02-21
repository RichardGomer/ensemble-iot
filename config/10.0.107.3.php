<?php

/**
 * SUMP, 10.0.107.3
 */
namespace Ensemble;

require __DIR__.'/home.php';

define('_NAME', 'sump');

// A context device will hold the depth data
$conf['devices'][] = $context = new Device\ContextDevice(_NAME.'.context');
$context->addSuperContext('global.context'); // Pass all measurements up to global supercontext

// Obviously, pump_off must be lower than pump_on; and there should be a reasonable range
// to avoid flip-flopping, and to ensure water is actually drained (instead of just going
// up and down hose, for instance...)

$hole_depth = 64; // Depth of hole (from sensor) in cm
$pump_on = 30; // Pump comes on when this level is reached (in cm)
$pump_on_interval = 3600;
$pump_off = 15; // And goes off when this level is reached (in cm)
$pump_force = 55; // Pump always operates when level goes above this

$depth = new Device\Sump\DepthSensor("sump.depth", 31, 33, $hole_depth);
$depth->addDestination(_NAME.'.context', 'sumpdepth'); // Push depth measurements to context
$conf['devices'][] = $depth;

// The pump is controlled by two relays (to provide double isolation)
// Controlled by pins 16 and 18
$r1 = GPIO\Pin::phys(16, GPIO\Pin::OUT);
$r2 = GPIO\Pin::phys(18, GPIO\Pin::OUT);
$relay = new Device\Sump\DblRelay($r1, $r2);
$relay->off();

$pumpdevice = new Device\Sump\PumpDevice(_NAME.'.pump', $relay, $depth);
$pumpdevice->setMinimumDepth($pump_off);
$pumpdevice->setAdvisoryPumping($pump_on, $pump_on_interval);
$pumpdevice->setMandatoryPumping($pump_force);

$conf['devices'][] = $pumpdevice;
