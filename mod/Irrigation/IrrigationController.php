<?php

namespace Ensemble\Device\Irrigation;

/**
 * This is the main irrigation controller
 */
class IrrigationController {

    public function __construct(Relay $pump, FlowMeter $flow, \PiLog\TextLog $log) {
        $this->pump = $pump;
        $this->flow = $flow;
        $this->log = $log;
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
    public function queue(IrrigationCmd $cmd) {
        $this->queue[] = $cmd;
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

    public function run() {
        if(!$this->isBusy()) {
            if(count($this->queue) > 0) {
                $this->currentCmd = array_shift($this->queue);
                try {
                    $this->beginPump($this->currentCmd);
                } catch (\Exception $e) {
                    $this->currentCmd = false;
                    $this->log->log($e->getMessage());
                }
            }
        }
        else
        {
            if($this->checkEndPump($this->currentCmd)) {
                $this->currentCmd = false;
            }
        }
    }

    protected function beginPump(IrrigationCmd $cmd) {

        $channel = $cmd->getChannel();
        if(!array_key_exists($channel, $this->channels)) {
            throw new BadChannelException("Channel '$channel' does not exist");
        }

        $valve = $this->channels[$channel];
        $this->flow->reset(); // Reset the flow meter

        // Open the valve
        $this->log->log("Open channel '$channel'");
        $valve->on();
        sleep(1); // Wait for valve to open and let power supply settle down

        // Start the pump
        $this->log->log("Start pump");
        $this->pump->on();
        sleep(3); // 3 seconds should be enough for something to happen!

        // Check that there's flow, otherwise abort
        if($this->flow->measure() < 10) {
            $this->pump->off();
            $valve->off();
            throw new LowFlowException("Detected less than 10ml flow in 3 seconds on channel '$channel' - aborted");
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

        $this->log->log("Pumped {$flow}ml of {$target}ml to {$cmd->getChannel()}");

        $this->log->log("Stop pump");
        $this->pump->off();
        sleep(1);

        $channel = $cmd->getChannel();
        $valve = $thic->channels[$channel];
        $this->log->log("Close channel '$channel'");
        $valve->off();
    }

}

class LowFlowException extends \Exception {}
class BadChannelException extends \Exception {}
