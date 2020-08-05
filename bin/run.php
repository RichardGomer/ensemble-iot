<?php

namespace Ensemble;

ini_set('memory_limit', '256M');

require dirname(__DIR__).'/lib/setup.php';

// Record runtime status information
$status = new Storage\JsonStore('devicestatus');
$status->proc = getmypid();
$status->started = time();
$status->ips = $ips = System\IP::getIPs();

$inputQueue = $conf['queue_in'];
$remoteQueue = $conf['queue_remote'];
$deviceMap = $conf['device_map'];



$broker = new CommandBroker();
$broker->setRemoteQueue($remoteQueue);
$broker->setInputQueue($inputQueue);

// The announcement device tells other endpoints about our local devices
// so that they can route commands to them
$announce = new Device\AnnouncerDevice($conf['endpoint_url'], array(), $deviceMap);
$broker->addDevice($announce);

// If there's a default endpoint configured, we'll announce to it
if($conf['default_endpoint'] !== false) {
    $announce->addRemote($conf['default_endpoint']);
}

// ** DISABLE DIRECT LOCAL DELIVERY **
// Direct local delivery can be disabled, in which case the message broker
// will push all messages via the remote queue. We need to announce to ourself
// so that those messages can be routed into our own endpoint.
if($conf['disable-direct-local']) {
    $broker->disableDirectLocalDelivery();
    $announce->addRemote($conf['endpoint_url']);
}

// A remote delivery device is a special device that delivers outgoing commands
// to remote endpoints
$remote = new Device\RemoteDeliveryDevice($remoteQueue, $deviceMap);
$remote->setDefaultEndpoint($conf['default_endpoint']);
$broker->addDevice($remote); // Add the remote delivery device

// Register defined devices
foreach($conf['devices'] as $d) {
    echo "Added device ".$d->getDeviceName()."\n";
    $broker->addDevice($d);
}

$broker->run();
