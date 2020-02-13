<?php

// This is the config that's common between the API and daemon

namespace Ensemble;

// Constant for easy access to the /var/ directory
define('_VAR', dirname(__DIR__).'/var/');

// Libs
$wd = getcwd();
chdir(__DIR__);

require '../vendor/autoload.php';


$conf['queue_in'] = new JsonQueue('q_in.json'); // Messages received by the API are stored in this queue
$conf['queue_remote'] = new JsonQueue('q_remote.json'); // Messages to be delivered remotely are stored in this queue
$conf['device_map'] = new Remote\DeviceMap(new Storage\JsonStore('device_map.json')); // A map of remote devices is maintained in here

chdir($wd);
