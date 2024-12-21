<?php

namespace Ensemble\Device\EnergyPlan;

use Ensemble\Async;
use Ensemble\Schedule\Printer;

class InverterControlDevice extends Async\Device {

    private static $counter = 0;

    public function __construct(public EnergyPlanner $planner)
    {
        $this->name = "inverter-control-".static::$counter++;
    }

    public $planned = false;

    protected function getRoutine() {
        $planner = $this->planner;
        $self = $this;
        return new Async\Lambda(function() use ($planner, $self){

            if($self->planned) { // If already planned, find the epoch of the next 30 minute billing period
                $epoch = ceil(time() / 1800) * 1800;
                echo "InverterControlDevice is active and planned. Waiting until next epoch at $epoch\n";
                yield new Async\waitUntil($epoch);
            } else { // Or set things up immediately
                $epoch = floor(time() / 1800) * 1800;
                echo "InverterControlDevice entered setup phase. Plan immediately\n";
                $self->planned = true;
            }
            
            // First create a new plan; this automatically pulls current state of charge, solar forecast and rates etc.
            echo "Update energy plan\n";
            $plan = $planner->getPlan();
            $printer = new Printer();
            foreach($plan as $k=>$s) {
                $printer->addSchedule("$k", $s);
            }
            echo $printer->print();


            // Use that plan to configure the inverter for the next slot
            $charge = $plan['Charge']->getAt($epoch);
            $discharge = $plan['Discharge']->getAt($epoch);
            $import = $plan['Import']->getAt($epoch);
            $export = $plan['Export']->getAt($epoch);
            $pv = $plan['PV']->getAt($epoch);

            // Actively charge when the battery should contribute to import
            if($charge > 0 && $import > 0) {
                echo "Inverter mode is ACTIVE CHARGE $charge\n";
                $planner->inverter->enableCharge($epoch, $epoch+1800, $charge);
            } 
            // Actively discharge when the battery should contribute to export
            elseif($discharge > 0 && $export > 0) {
                echo "Inverter mode is ACTIVE DISCHARGE $discharge\n";
                $planner->inverter->enableDischarge($epoch, $epoch+1800, $discharge);
            } 
            // If the battery should not charge or discharge, and there is no PV, then disable it entirely
            // This basically only happens when battery losses are expected to outweigh marginal import costs
            elseif($discharge == 0 && $charge == 0 && $pv == 0) {
                echo "Inverter mode is NO BATT\n";
                $planner->inverter->disableBattery();
            } 
            // Otherwise we let the battery auto-balance
            // This case uses the inverters realtime behaviour to choose what to do 
            else {
                echo "Inverter mode is AUTO\n";
                $planner->inverter->enableAuto();
            }

            yield new Async\waitForDelay(15); // This is a bit safer when testing!
        });
    }

}