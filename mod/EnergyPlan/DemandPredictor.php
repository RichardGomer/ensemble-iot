<?php

namespace Ensemble\Device\EnergyPlan;

interface DemandPredictor {

    /**
     * Predict the amount of stored energy for the next period.
     * This can be any period; but probably needs to be a few hours, at least.
     * @return EnergySchedule 
     */
    public function getDemandPrediction() : EnergySchedule;
}