<?php

/**
 * Test case for async devices
 */

namespace Ensemble;
use \Ensemble\Async as Async;

/**
 * There are two simple devices that show how to create async devices. One sends
 * a ping, then waits for a reply. The other waits for a ping, and then sends a
 * reply.
 *
 * State becomes implicit, and so asynchronous programming is much much easier.
 * Send actions, wait for them to complete; or wait for actions, respond as they
 * arrive.
 */
class PingDevice extends Async\Device {
    public function __construct() {
        $this->name = "PING";
    }

    public function getRoutine() {
        $device = $this;
        // AsyncLambda is convenient here; but anything that implements AsyncRoutine could be returned
        return new Async\Lambda(function() use ($device) {
            echo "Send ping and wait\n";
            $c = Command::create($this, 'PONG', 'ping');
            $device->getBroker()->send($c);
            yield new Async\WaitForReply($device, $c);
            echo "Got a reply! <3\n";
            yield new Async\WaitForDelay(10);
        });
    }
}

class PongDevice extends Async\Device {
    public function __construct() {
        $this->name = "PONG";
    }

    public function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {
            echo "Wait for ping\n";
            $c = yield new Async\WaitForCommand($device, 'ping');
            echo "Got a ping - send reply\n";
            $device->getBroker()->send($c->reply(array()));
        });
    }
}

$conf['devices'][] = new PingDevice();
$conf['devices'][] = new PongDevice();
