<?php

/**
 * A CLI script to send commands to an endpoint
 */

namespace Ensemble;
use Garden\Cli\Cli;

require dirname(__DIR__).'/vendor/autoload.php';

$cli = new Cli();
$cli->description("Send a command to an ensemble-iot device")
->opt('endpoint:e', 'The URL of the endpoint; if no device map file is available', false)
->opt('devicemap:m', 'The path to a device map file to lookup endpoint URLs based on device name', false)
->opt('device:d', 'The target device name', true, 'string')
->opt('action:a', 'The action to run', true, 'string')
->opt('expires:X', 'An expiry time (in seconds) for the command (defaults to 15 mins)', false, 
'integer')
->opt('args:x', 'Arguments for the command in the format key=value', false, 'string[]');
$args = $cli->parse($argv);

$ep = $args->getOpt('endpoint', false);
$dm = $args->getOpt('devicemap', false);
$exp = $args->getOpt('exp', 15 * 60);

if($ep === false && $dm === false) {
    echo "You must specify either an endpoint to use for delivery, or a devicemap file\n";
    exit;
}

$devicename = $args->getOpt('device');

if($ep !== false) {
    $endpoint = $ep;
} else {
    $path = explode('/', $dm);
    $fn = array_pop($path);
    $dn = implode('/', $path).'/';
    if(!file_exists($dn.$fn)) {
        echo "Devicemap file {$dn}{$fn} does not exist\n";
        exit;
    }
    $devicemap = new Remote\DeviceMap(new Storage\JsonStore($fn, $dn));

    if(!$devicemap->contains($devicename)) {
        echo "Couldn't find $devicename in {$dn}{$fn}\n";
        exit;
    }

    $endpoint = $devicemap->getEndpoint($devicename);
}

$action = $args->getOpt('action');

$sargs = $args->getOpt('args');
$pargs = array();
if($sargs !== null) {
    foreach($sargs as $s) {
        list($name, $value) = explode('=', $s, 2);
        $pargs[$name] = $value;
    }
}

class DummySource implements Module {
    public function getDeviceName() { return '_CLI_'; }
    public function announce() { return false; }
    public function action(Command $command, CommandBroker $broker) { }
    public function isBusy() { return false; }
    public function getPollInterval() { return 0; }
    public function poll(CommandBroker $broker) { }
    public function getChildDevices(){ return false; }
}

$pas = '';
foreach($pargs as $n=>$v) {
    $pas .= "     - $n=$v\n";
}

echo "ACTION: $action\n";
echo "ARGS: \n$pas\n";
echo "TARGET: $devicename via $endpoint\n";

$command = Command::create(new DummySource(), $devicename, $action, $pargs);
$command->setExpires(time() + $exp); 

$client = Remote\ClientFactory::factory($endpoint);

echo $command->toJSON();

echo "Sending... ";
$client->sendCommand($command);
echo "Done!\n";
