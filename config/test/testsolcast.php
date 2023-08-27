<?php

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Schedule as Schedule;


// Create an Octopus API client
require '../dbcreds.php'; // Keep account info out of git!

// Create a context device
$conf['devices'][] = $ctx = new Device\ContextDevice('test.context');
$conf['devices'][] = $d = new Device\EnergyPlan\SolcastDevice('sctest', $solcast_key, $solcast_site);
$d->setContext('test.context', 'solcast');
