<?php

namespace Ensemble\Device\EnergyPlan;

use Ensemble\Device\EnergyPlan\EnergySchedule;
use Ensemble\Device\EnergyPlan\GivEnergyInverter;
use Ensemble\Schedule\OnSchedule;
use Ensemble\Schedule\TariffSchedule;
use Ensemble\System\Thread;

/**
 * Optimise energy buying using battery storage
 * Runs some a linear programming 
 */


class EnergyPlanner {


    public function __construct(
        public DemandPredictor $demandPred,
        public SolcastPredictor $solarPred,
        public TariffSource $importTariffSource,
        public TariffSource $exportTariffSource,
        public GivEnergyInverter $inverter)
    {
        

    }


    /**
     * Use a storage prediction plus tariff schedule to identify the best times to charge/discharge
     */
    public function getPlan() : mixed {

        // Get raw output from the optimiser
        $raw = $this->runModel();

        // Convert it to some Schedules
        $schedules = [];

        foreach($raw as $period) {
            $ts = $period['Timestamp'];
            foreach($period as $k => $v) {
                if($k !== 'Timestamp') {

                    if(!array_key_exists($k, $schedules)) {
                        $schedules[$k] = new EnergySchedule();
                    }

                    $schedules[$k]->setPoint($ts, $v);
                }
            }
        }

        return $schedules;
    }

    protected function runModel() {

        $cmd = "python3 ".__DIR__."/HELPer/helper.py";

        $ts = $this->assembleTimeseries(    $this->solarPred->getSolarPrediction(), 
                                            $this->demandPred->getDemandPrediction(), 
                                            $this->importTariffSource->getTariff(), 
                                            $this->exportTariffSource->getTariff()
                                        );

        $opts = [
            'bsoc' => $this->inverter->getBatterySOC(),
            'bcap' => $this->inverter->getBatteryCapacity(),
            'bcr' => $this->inverter->getBatteryMaxChargeRate() / 2, // Need to convert for 30 minute slots!
            'bdr' => $this->inverter->getBatteryMaxDischargeRate() / 2,
            'bce' => 0.95,
            'bde' => 0.95,
            'bmin' => 0.04 * $this->inverter->getBatteryCapacity()
        ];

        $t = new Thread($cmd);
        $json = json_encode(['options' => $opts, 'data' => $ts], true);
        //echo $json;
        $t->tell($json, true); // Send the JSON and close the pipe
        $t->waitForExitAndPrint();
        $out = $t->getBuffer();

        if($t->getStatus()['exitcode'] == 0) {
            $plan = array_filter($out, function($line) {
                $ret = preg_match('/^{.+}$/', $line); // Filter lines to include only those that are JSON objects

                if($ret) {
                    return true;
                } else {
                    //echo $line;
                    return false;
                }

            });

            return array_map(function($l) {
                //echo "$l\n";
                return json_decode($l, true);
            }, $plan);

        } else {
            throw new PlanningException("HELP execution error:\n".implode("\n", $t->error()));
        }
    }

    protected function assembleTimeseries(EnergySchedule $pv, EnergySchedule $demand, TariffSchedule $import, TariffSchedule $export) {

        // Iterate through the schedules - include rows that are complete
        $out = [];
        foreach($demand->getChangePoints() as $time) {

            // Skip times in the past
            if($time < time() - 1800) {
                continue;
            }

            $row = ['Timestamp' => $time];

            $row['PV_KWh'] = $pv->getAt($time);
            $row['Demand_KWh'] = $demand->getAt($time);
            $row['ImTariff'] = $import->getAt($time);
            $row['ExTariff'] = $export->getAt($time);

            if(!in_array(false, $row, true)) {
                $out[] = $row;
            }
        }

        return $out;

    }


}

class PlanningException extends \Exception {}
