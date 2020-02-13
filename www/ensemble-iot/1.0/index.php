<?php

/**
 * Ensemble-IOT HTTP API
 * This file provide an HTTP endpoint for inter-node communication in Ensemble.
 *
 * The www/ folder needs to be served up somehow
 */

namespace Ensemble\API\HTTP;
use QuickAPI as API;

require '../../../lib/setup-common.php'; // Load basic libs & config
require '../../../lib/quapi/api.lib.php'; // API framework
require 'auth.lib.php';

header('Access-Control-Allow-Origin: *');

header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header("Pragma: no-cache"); // HTTP/1.0
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");

$API = new API\API(array_merge($_GET, $_POST), 'op');

$auth = new IPAuth('10.0.0.0/16');
//$API->addAuth($auth, array());

class CommandHandler implements API\APIHandler
{
    public function __construct(\Ensemble\Queue $queue) {
        $this->queue = $queue;
    }

    public function handleCall($args) {
        $cmd = \Ensemble\Command::fromJSON($args['command']);
        $this->queue->push($cmd);
    }
}

class RegistrationHandler implements API\APIHandler
{
    public function __construct(\Ensemble\Remote\DeviceMap $map) {
        $this->map = $map;
    }

    public function handleCall($args) {
        $devices = $args['register']; // QUAPI does json decoding automatically
        $endpoint = $args['endpoint'];

        $regd = array();
        foreach($devices as $d) {
            $this->map->register($d, $endpoint);
            $regd[$d] = $endpoint;
        }
        return $regd;
    }
}


$API->addOperation(false, array('command'), new CommandHandler($conf['queue_in']));
$API->addOperation(false, array('register', 'endpoint'), new RegistrationHandler($conf['device_map']));

$API->handle();
