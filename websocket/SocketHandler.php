<?php

namespace Ensemble\WebSocket;

use Ensemble\CommandBroker;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class SocketHandler implements MessageComponentInterface {

    private $handlers = [];
    private CommandBroker $broker;

    public function __construct(CommandBroker $broker) {
        $this->broker = $broker;
    }

    private function getHandlerFor(ConnectionInterface $conn) {
        foreach($this->handlers as $h) {
            if($h->getWS() == $conn) {
                return $h;
            }
        }
        return false;
    }

    public function onOpen(ConnectionInterface $conn) {
        $h = new ClientHandler($conn, $this->broker);
        $this->handlers[] = $h;
        $this->broker->addDevice($h); // Register the handler as a device so it can receive messages from the mesh
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $h = $this->getHandlerFor($from);
            $h->sendCommand($msg);
        }
        catch(\Exception $e) {
            trigger_error("Exception while handling message: ".$e->getMessage(), E_USER_WARNING);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $h = $this->getHandlerFor($conn);
        $this->broker->removeDevice($h);

        $this->handlers = array_filter($this->handlers, function ($e) use ($h) {
            return $e !== $h;
        });
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        trigger_error("WebSocket error: ".$e->getMessage(), E_USER_WARNING);
        $conn->close();
    }
}
