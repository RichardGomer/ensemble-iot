<?php

namespace Ensemble\Device\Irrigation;
use Ensemble\GPIO\Relay;


/**
 * This is the main irrigation controller
 */
class IrrigationController extends \Ensemble\Device\BasicDevice {

    public function __construct($name, FlowMeter $flow) {
        $this->flow = $flow;
        $this->name = $name;

        $this->registerAction('water', $this, 'action_water');
    }

    /**
     * Add a named channel for pumping
     */
    public function addChannel($name, Relay $valve, Relay $pump) {
        $this->channels[$name] = array('valve'=>$valve, 'pump'=>$pump);
    }

    /**
     * Add a context device
     */
    private $destination = false;
    public function setDestination($cd) {
        $this->destination = $cd;
    }

    private $broker = null;
    protected function logContext($channel, $flow) {
        if($this->destination === false)
            return;

        if(!is_object($this->broker)) {
            trigger_error("Can't logContext(), broker isn't set", E_USER_WARNING);
            return;
        }

        $cmd = \Ensemble\Command::create($this, $this->destination, 'updateContext');
        $cmd->setArg('time', time());
        $cmd->setArg('value', $flow);
        $cmd->setArg('field', "channel-".$channel);
        $this->broker->send($cmd);
    }

    /**
     * Queue an irrigation command
     */
    private $queue = array();
    protected function action_water(\Ensemble\Command $cmd, $broker) {

        $this->broker = $broker;

        $this->currentCmd = new IrrigationCmd($cmd->getArg('channel'), $cmd->getArg('ml'));
        $this->currentEnsembleCmd = $cmd;

        try {
            $this->beginPump($this->currentCmd);
        } catch (\Exception $e) {
            $this->currentCmd = false;
            $this->log("Can't pump: ".$e->getMessage());
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

        $valve = $this->channels[$channel]['valve'];
        $pump = $this->channels[$channel]['pump'];
        $this->flow->reset(); // Reset the flow meter

        $this->startTime = time();

        $this->log("Begin pumping on channel $channel");
        $this->logContext($channel, 0);

        // Open the valve
        $valve->on();
        usleep(200000); // Wait for valve to open and let power supply settle down

        // Start the pump
        $pump->on();
        sleep(3); // should be enough for something to happen!

        // Check that there's flow, otherwise abort
        $min = 6;
        if(($flow = $this->flow->measure()) < $min) {
            $pump->off();
            usleep(500000);
            $valve->off();
            $this->logContext($channel, $flow);
            throw new LowFlowException("Detected flow {$flow}ml is less than {$min}ml flow on channel '$channel' - aborted");
        }
    }

    /**
     * Check pumping status and (if finished) end pumping
     */
    protected function checkEndPump(IrrigationCmd $cmd) {

        $flow = $this->flow->measure();
        $target = $cmd->getMl();

        $channel = $cmd->getChannel();
        $this->logContext($channel, $flow);

        $ptime = time() - $this->startTime;

        $this->log("Pumping in progress. {$flow} of {$target} ml in {$ptime}s on channel $channel");

	    if($ptime > 10 * 60) {
    	    // Maximum pumping time of 10 mins exceeded
            $this->log("Maximum pump time exceeded");
    	}
    	elseif($flow < $target) {
            $this->log("Target not reached, continue");
            return false;
        }

        $this->log("Target met. Stopping");

        $valve = $this->channels[$channel]['valve'];
        $pump = $this->channels[$channel]['pump'];

        $pump->off();
        usleep(1500000); // 1.5sec - time for softstop
        $valve->off();

        $cmd->setFlow($flow);
        $cmd->setTime(time() - $this->startTime);

        $this->logContext($channel, 0);

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
