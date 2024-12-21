<?php

/**
 * A battery SOC predictor for GivEnergy inverter
 * Uses recent flow data to predict future demand
 */

namespace Ensemble\Device\EnergyPlan;

use DateTime;
use Ensemble\Device\EnergyPlan\EnergySchedule;
use Ensemble\Device\EnergyPlan\GivEnergyInverter;
use Ensemble\Util\Memoise;
use HiFolks\Statistics\Stat;

class GivEnergyPredictor implements DemandPredictor {

    public $days = 28; // The number of days history to process

    use Memoise;

    public function __construct(public GivEnergyInverter $inverter) {
        
    }

    public function getDemandPrediction($days=false): EnergySchedule {

        // TODO: Download some data and create a model from it
        $history = $this->getHistory();
        //$history = json_decode(file_get_contents('/home/richard/Synced/Documents-Projects/2024-tariff-linear-programming/usagedata.json'), true);

        if($days !== false) {
            $start = date('Y-m-d 00:00:00', time() - 24 * 3600 * $days);
            $end = date('Y-m-d 00:00:00', time());
            $summary = $this->summarise($history, $start, $end);
        } else {
            $summary = $this->summarise($history);
        }
        
        $pred = new EnergySchedule();

        /**
         * Run the prediction for two days, which should be plenty
         */
        foreach($summary as $slot=>$kwh) {
            $pred->setPeriod($s = strtotime("$slot"), $s+1800, $kwh);
        }

        foreach($summary as $slot=>$kwh) {
            $pred->setPeriod($s = strtotime("tomorrow $slot"), $s+1800, $kwh);
        }

        return $pred;
    }

    public function getHistory() {

        // TODO: Cache data locally in a file, only fetch what's missing

        return $this->memoise("history-{$this->days}", 24 * 3600, function() {
            return $this->inverter->getFlow(time() - $this->days * 3600 * 24);
        });
    }

    /**
     * Summarise a set of daily data using some sort of modelling function
     * 
     */
    protected function summarise($slots, $start=false, $end=false) {

        // Optionally, filter data by start and end dates
        if($start !== false || $end !== false) {
            $start = $start ? strtotime($start) : 0;
            $end = $end ? strtotime($end) : INF;

            $slots = array_filter($slots, function($entry) use ($start, $end) {
                $entryTime = strtotime($entry['start']);
                return $entryTime >= $start && $entryTime <= $end;
            });
        }

        // Summarise all the slots by time period
        $flowsBySlot = array_reduce($slots, function($carry, $data){

            $slot = substr($data['start'], 11, 5);

            if(!array_key_exists($slot, $carry)) {
                $carry[$slot] = [];
            }

            $carry[$slot][] = $data['data']['x_Home'];

            return $carry;
        }, array());


        $summary = [];

        // Estimate each slot
        foreach($flowsBySlot as $slot=>$values) {
            $summary[$slot] = round(Stat::quantiles($values, 10)[6] * 1.1, 2); // +10% for some headroom; 
        }

        return $summary;
    }

}
