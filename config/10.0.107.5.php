<?php

/**
 * 10.0.107.5 - Boiler monitor
 */

namespace Ensemble;

use Ensemble\Device\W1\Sensor;
use Ensemble\Device\W1\TemperatureSensor;

require __DIR__.'/home_common.inc.php';

Sensor::enable(26); // Enable 1wire on gpio pin

$temps = array(
    "boiler.temperature.flow" => "28-0835841e64ff",
    "boiler.temperature.return" => "28-3c01d6072df2",
    "boiler.temperature.mains"=> "28-dc3a841e64ff"
);

foreach($temps as $dn=>$id) {
    $conf['devices'][] = $d = new TemperatureSensor($dn, $id);
    $fn = "temperature.boiler-".array_pop(explode('.', $dn)); // Extract last part as field name in context, e.g. "temp_flow"
    $d->addDestination("global.context", $fn);
}
