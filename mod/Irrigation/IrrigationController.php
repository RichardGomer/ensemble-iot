<?php

namespace Ensemble\Device\Irrigation;
use Ensemble\GPIO\Relay;


/**
 * This is the main irrigation controller
 */
class IrrigationController extends \Ensemble\Device\BasicDevice {

    public function __construct($name, Relay $pump, FlowMeter $flow) {
        $this->pump = $pump;
        $this->flow = $flow;
        $this->name = $name;

        $this->registerAction('water', $this, 'action_water');
    }

    /**
     * Add a named channel for pumping
     */
    public function addChannel($name, Relay $valve) {
        $this->channels[$name] = $valve;
    }


    /**
     * Queue an irrigation command
     */
    private $queue = array();
    protected function action_water(\Ensemble\Command $cmd) {

        $this->currentCmd = new IrrigationCmd($cmd->getArg('channel'), $cmd->getArg('ml'));
        $this->currentEnsembleCmd = $cmd;

        try {
            $this->beginPump($this->currentCmd);
        } catch (\Exception $e) {
            $this->currentCmd = false;
            echo "Can't pump: ".$e->getMessage()."\n";
        }
    }

    /**
     * Check if commands are in progress
     */
    public function isBusy() {
        return $this->currentCmd != false;
    }


    /**
     * Check the status of the current command, start/stop relays etc.
     * This method needs to be called regularly in order to start/stop pumping etc!
     */
    private $currentCmd = false;

    public function poll(\Ensemble\CommandBroker $b) {
        if($this->isBusy())
        {
            if(($flow = $this->checkEndPump($this->currentCmd)) !== false) {
                // Reply with the actual flow
                $b->send($this->currentEnsembleCmd->reply(array('flow'=>$this->currentCmd->getFlow().'ml', 'time'=>$this->currentCmd->getTime())));
                $this->currentCmd = false;
            }
        }
    }

    public function getPollInterval() {
        return 5;
    }

    private $startTime;
    protected function beginPump(IrrigationCmd $cmd) {

        $channel = $cmd->getChannel();
        if(!array_key_exists($channel, $this->channels)) {
            throw new BadChannelException("Channel '$channel' does not exist");
        }

        $valve = $this->channels[$channel];
        $this->flow->reset(); // Reset the flow meter

        $this->startTime = time();

        echo "Begin pumping on channel $channel\n";

        // Open the valve
        $valve->on();
        usleep(100000); // Wait for valve to open and let power supply settle down

        // Start the pump
        $this->pump->on();
        sleep(3); // 3 seconds should be enough for something to happen!

        // Check that there's flow, otherwise abort
        $min = 10;
        if($this->flow->measure() < $min) {
            $this->pump->off();
            $valve->off();
            throw new LowFlowException("Detected less than {$min}ml flow in 3 seconds on channel '$channel' - aborted");
        }
    }

    /**
     * Check pumping status and (if finished) end pumping
     */
    protected function checkEndPump(IrrigationCmd $cmd) {

        $flow = $this->flow->measure();
        $target = $cmd->getMl();

        if($flow < $target) {
            return false;
        }

        $this->pump->off();
        usleep(100000); //100msec pause

        $channel = $cmd->getChannel();
        $valve = $this->channels[$channel];
        $valve->off();

        $cmd->setFlow($flow);
        $cmd->setTime(time() - $this->startTime);

        return true;
    }

}


/**
 * Represents an irrigation command; a command is just a channel name plus the
 * number of millilitres to discharge
 */
class IrrigationCmd {

    public function __construct($channel, $ml) {
        $this->ml = $ml;
        $this->channel = $channel;
    }

    public function getChannel() {
        return $this->channel;
    }

    public function getMl() {
        return $this->ml;
    }


    // Reporting

    private $flow = false;
    public function setFlow($flow) {
        $this->flow = $flow;
    }

    public function getFlow() {
        return $this->flow;
    }

    private $time = false;
    public function setTime($t) {
        $this->time = $t;
    }

    public function getTime() {
        return $this->time;
    }
}


class LowFlowException extends \Exception {}
class BadChannelException extends \Exception {}
