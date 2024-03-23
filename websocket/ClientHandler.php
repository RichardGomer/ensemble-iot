<?php

namespace Ensemble\WebSocket;

use Ensemble\Command;
use Ensemble\CommandBroker;
use Ensemble\Device\DeviceLogging;
use Ratchet\ConnectionInterface;

/**
 * Handle a single client
 * This class is an Ensemble Device, and is also called by the Ratchet handler (above) so it
 * can bridge the WebSocket system to the Ensemble mesh
 * @package Ensemble\WebSocket
 */
class ClientHandler implements \Ensemble\Module {

    use DeviceLogging;

    private $name; // The name of this ensemble device
    private ConnectionInterface $ws; // The websocket connection to the client
    private CommandBroker $broker; // The ensemble broker we send/receive commands through

    /**
     * Instantiate with an ensemble client that points to a friendly endpoint that will deliver
     * our messages for us
     * @param RemoteClient $client 
     * @return void 
     */
    public function __construct(ConnectionInterface $ws, CommandBroker $broker) {
        $this->name = "wsproxy-".uniqid();
        $this->ws = $ws;
        $this->broker = $broker;

        $this->log("Created handler for client $this->name");

        // Send some information to the client
        $this->action(Command::createOrphan('_js', 'status', [
            'handlerName' => $this->name
        ]), $broker);
    }

    public function __destruct()
    {
        $this->log("Destroyed handler for client $this->name");
    }

    /**
     * Get the web socket this handler is handling
     * @return ConnectionInterface 
     */
    public function getWS() : ConnectionInterface {
        return $this->ws;
    }

    /**
     * Transmit a command to our ensemble broker
     */
    public function sendCommand(string $json) {

        $array = json_decode($json, true);
    
        $this->log("Received command from WS client {$this->name}:\n".$json);

        // Patch the source so that replies come back to us!
        $array['source'] = $this->getDeviceName();

        // Send the command through the broker
        $command = Command::fromJSON($array);
        $this->broker->send($command);
    }

    /**
     * This is all Ensemble Device stuff
     */
    public function getDeviceName() { 
        return $this->name;
    }

    public function announce() {
        return true;
    }

    /**
     * Commands are sent straight to the WS client
     */
    public function action(Command $command, CommandBroker $broker) {
        echo "Proxy command to client\n";
        $json = $command->toJSON();
        echo $json."\n\n";
        $this->ws->send($json);
    }

    public function isBusy() {
        return false;
    }

    public function getPollInterval() { 
        return false;
    }

    public function poll(CommandBroker $broker) { 
        $this->broker = $broker;
    }

    public function getChildDevices() { 
        return [];
    }
}