<?php

namespace Ensemble\Device\EnergyPlan;

interface SolarEnergyPredictor {

    /**
     * Predict the amount of stored energy for the next period.
     * This can be any period; but probably needs to be a few hours, at least.
     * @return EnergySchedule 
     */
    public function getSolarPrediction() : EnergySchedule;
}