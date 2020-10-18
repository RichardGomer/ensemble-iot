<?php

namespace Ensemble\Device\Irrigation;
use Ensemble\GPIO\Relay;


/**
 * The doser pumps fertiliser etc into the flow
 */
class IrrigationDoser extends \Ensemble\Device\BasicDevice {

    public function __construct($name, Relay $pump, FlowMeter $flow, $mlpersec) {
        $this->pump = $pump;
        $this->flow = $flow;
        $this->name = $name;
        $this->mlpersec = $mlpersec;

        $this->registerAction('setdose', $this, 'action_setdose');
    }

    /**
     * Add a context device
     */
    private $destination = false;
    public function setDestination($cd) {
        $this->destination = $cd;
    }

    private $broker = null;
    protected function logContext($dose) {
        if($this->destination === false)
            return;

        if(!is_object($this->broker)) {
            trigger_error("Can't logContext(), broker isn't set", E_USER_WARNING);
            return;
        }

        $cmd = \Ensemble\Command::create($this, $this->destination, 'updateContext');
        $cmd->setArg('time', time());
        $cmd->setArg('value', $dose);
        $cmd->setArg('field', "dose");
        $this->broker->send($cmd);
    }


    private $queue = array();
    private $startflow = 0;
    private $lastflow = 0;
    private $mlperl = 0;
    private $dosed = 0;
    protected function action_setdose(\Ensemble\Command $cmd, $broker) {

        $this->broker = $broker;

        $this->mlperlitre = $cmd->getArg('mlperl');
        $this->startflow = $this->flow->getFlow();
        $this->lastflow = 0;
        $this->dosed = 0;

        echo "Dose set to {$this->mlperlitre}ml/l\n";
    }

    protected function dose($ml) {
        $sec = $ml / $this->mlpersec;
        $this->pump->on();
        usleep($sec * 1000000);
        $this->pump->off();
        sleep(1);
    }

    /**
     * Check the status of the current command, start/stop relays etc.
     * This method needs to be called regularly in order to start/stop pumping etc!
     */
    private $currentCmd = false;

    public function poll(\Ensemble\CommandBroker $b) {

        $totalFlow = $this->flow->getFlow() - $this->startflow; // Get flow volume since the dose was set

        // Don't dose if the flow has stopped!
        if($totalFlow == $this->lastflow) {
            return;
        }

        $this->lastflow = $totalFlow; // So we can check flow is still going

        $reqdDose = $this->mlperlitre * ($totalFlow / 1000); // Calculate the required dose for the flow volume, in ml
        $nextDose = min(10, $reqdDose - $this->dosed); // Never dose more than 10ml at a time, to avoid blocking the thread too long

        if($nextDose < 2) { // Skip until the dose is a reasonable size
            return;
        }

        echo "Flow is $totalFlow, dose required is {$reqdDose}ml, dose +{$nextDose}ml now\n";

        $this->dosed += $nextDose; // Update total dose
        $this->logContext($this->dosed); // and log it
        $this->dose($nextDose); // Actually add the dose to the flow
    }

    public function getPollInterval() {
        return 10;
    }


}