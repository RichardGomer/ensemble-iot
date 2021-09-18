<?php

namespace Ensemble;
use Ensemble\MQTT as MQTT;
use Ensemble\Device\Light as Light;
use Ensemble\Schedule\Schedule as Schedule;

date_default_timezone_set('Europe/London');


$client = new \Ensemble\MQTT\Client('10.0.0.8', 1883);
$conf['devices'][] = $sw_toilet = new Device\Light\LightSwitch("switch-toilet", $client, "lightswitch2");

$sw_toilet->getStatus()->sub('STATE.POWER', function($key, $value) use ($sw_toilet) {
    $sw_toilet->log("Status set to $value");
});
