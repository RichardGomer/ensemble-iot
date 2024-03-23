<?php

namespace Ensemble\WebSocket;

use Ensemble\Device\AnnouncerDevice;
use Ensemble\Device\RemoteDeliveryDevice;
use Ensemble\Remote\DeviceMap;
use Ensemble\CommandBroker;
use Ensemble\Storage\JsonStore;
use Garden\Cli\Cli;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require __DIR__.'/../lib/setup-common.php';


$cli = new Cli();
$cli->description("Run the ensemble-iot websocket bridge")
->opt('default-ep:d', 'The URL of the default remote endpoint, this should be a broker we can send/receive commands via', true)
->opt('local-ep:l', 'Specify the URL of our local endpoint', true);

$args = $cli->parse($argv);

$defaultEndpoint = $args['default-ep'];
$localEndpoint = $args['local-ep'];

$inputQueue = $conf['queue_in'];
$remoteQueue = $conf['queue_remote'];
$deviceMap = $conf['device_map'];

/**
 * Set up a minimal ensemble broker that will send and receive commands 
 * to/from the ensemble mesh
 */

$broker = new CommandBroker();

$broker->setRemoteQueue($remoteQueue);
$broker->setInputQueue($inputQueue);

$deviceMap = new DeviceMap(new JsonStore("wsdevicemap.json"));

$announce = new AnnouncerDevice($localEndpoint, array(), $deviceMap);
$broker->addDevice($announce);
$announce->addRemote($defaultEndpoint);

$remote = new RemoteDeliveryDevice($remoteQueue, $deviceMap);
$remote->setDefaultEndpoint($defaultEndpoint);
$broker->addDevice($remote); // Add the remote delivery device

/**
 * Create a Ratchet websocket server
 */
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SocketHandler($broker)
        )
    ), 31075
);


/**
 * Add the Ensemble broker into the React event loop
 */
$server->loop->addPeriodicTimer(0.1, function() use ($broker) {
    $broker->step(); // Run the broker in non-blocking mode
});


/**
 * Run the WS server (and, implicitly, the React event loop that includes the ensemble broker)
 */
$server->run();


