<?php

namespace Ensemble\Device\EnergyPlan;

use Ensemble\Schedule\OnSchedule;
use Ensemble\Schedule\DailyProjector;

class EnergyPlan {

    public function __construct() {

        $this->storageCap = 0;
        $this->storageEff = 0;

        $this->dischargeSchedule = new OnSchedule();
        $this->dischargeSchedule->setPoint(0, 'ON');
    }

    /**
     * Set storage capacity
     */
    public function setStorage($capacity, $efficiency=0.95) {
        $this->storageCap = $capacity;
        $this->storageEff = $efficiency;
    }

    /**
     * Set battery discharge schedule, controls whether discharge is allowed in
     * a period. ON = discharge allowed, OFF = store only
     *
     * __This schedule will be projected__
     */
    public function setDischarge(OnSchedule $s) {
        $this->dischargeSchedule = $s;
    }

    /**
     * Set battery grid charging schedule, controls battery charging from the grid
     * if ON, the battery will draw grid power. Rate is the charge rate in kw
     * in terms of DRAW; will be multiplied by the efficiency factor
     *
     * __This schedule will be projected__
     */
    public function setGridCharge(OnSchedule $s, $rate=1.3) {
        $this->chargeSchedule = $s;
        $this->gridChargeRate = $rate;
    }

    /**
     * Set current stored energy
     */
    private $stored = 0;
    public function setStored($kwh) {
        $this->stored = min($kwh, $this->storageCap);
    }

    /**
     * Add scheduled generation - power that's available
     * If a name is re-used, the original is overwritten
     */
    public function addGeneration($name, EnergySchedule $gen) {
        $this->generation[$name] = $gen;
    }

    /**
     * Add scheduled consumption - power that's required
     * If a name is re-used, the original is overwritten
     * Consumption should be represented as NEGATIVE values!
     */
    public function addConsumption($name, EnergySchedule $gen) {

        foreach($gen->getAllPeriods() as $p) {
            if($p['status'] > 0) {
                throw new \Exception("Consumption schedules should contain only negative values");
            }
        }

        $this->consumption[$name] = $gen;
    }

    // Round a float to a sensible precision
    protected function round($fl) {
        return round($fl, 2);
    }

    /**
     * Get an up to date plan; an array of named schedules
     *     stored => Estimated stored power
     *     import => Estimated import (from grid)
     *     export => Estimated export (to grid)
     */
    public function getPlan() {

        // Find total generation schedule
        $sgen = new EnergySchedule();
        foreach($this->generation as $g) {
            $sgen = $sgen->add($g);
        }

        // Find total consumption schedule
        $scon = new EnergySchedule();
        foreach($this->consumption as $c) {
            $scon = $scon->add($c);
        }

        // Get net transfer
        $snet = $sgen->add($scon);


        // Apply net transfer to storage and import
        $stored = $this->stored;
        $sstored = new EnergySchedule();
        $simport = new EnergySchedule();
        $sexport = new EnergySchedule();


        // Project the configuration schedules forward
        $end = end($void = $snet->getAllPeriods())['start']; // Assignment to stop error about passing by ref
        $disSched = (new DailyProjector($this->dischargeSchedule))->project(time(), $end); // Battery discharge schedule
        $gcSched = (new DailyProjector($this->chargeSchedule))->project(time(), $end); // Grid charge schedule

        foreach($snet->getAllPeriods() as $p) {

            if($p['end'] < time()) // Skip periods that are in the past
                continue;

            $change = $this->round($p['status']);

            if($change > 0) { // Storage mode

                $battchange = $this->storageEff * $change; // Apply storage inefficiency to the change
                $stored = $stored + $battchange; // Update the storage

                if($stored > $this->storageCap) { // Export surplus
                    $export = $change - (($stored - $this->storageCap) / $this->storageEff);
                    $stored = $this->storageCap;
                } else {
                    $export = 0;
                }

                $export = $this->round($export);

                $sexport->setPeriod($p['start'], $p['end'], $export * -1);
                $simport->setPeriod($p['start'], $p['end'], 0);

            } elseif($disSched->getAt($p['start']) == 'ON') { // Discharge mode
                $stored = $stored + $change;

                if($stored < 0) {
                    $import = $stored;
                    $stored = 0;
                } else {
                    $import = 0;
                }

                $sexport->setPeriod($p['start'], $p['end'], 0);
                $simport->setPeriod($p['start'], $p['end'], $this->round($import));
            } else { // Grid-only import mode

                // Simulate grid charging of the battery
                if($stored < $this->storageCap && $gcSched->getAt($p['start']) == 'ON') {
                    $capacity = $this->storageCap - $stored;
                    $max = ($p['end'] - $p['start']) / 3600 * $this->gridChargeRate; // Max possible charge
                    $charge = min($max, $capacity); // Actual charge amount is the maximum draw, limited by capacity
                    $stored += $charge;
                    $change -= $charge / $this->storageEff; // Allow extra draw for waste
                }

                $sexport->setPeriod($p['start'], $p['end'], 0);
                $simport->setPeriod($p['start'], $p['end'], $this->round($change));
            }

            $sstored->setPeriod($p['start'], $p['end'], $this->round($stored));
        }

        return [
            'load' => $scon,
            'generation' => $sgen,
            'net' => $snet,
            'import' => $simport,
            'export' => $sexport,
            'soc' => $sstored,
            'gridCharge' => $gcSched,
            'discharge' => $disSched
        ];

    }

}
