<?php

namespace Ensemble\Device\EnergyPlan;

use Ensemble\Schedule\TariffSchedule;

interface TariffSource {

    /**
     * Get electricity tariff data
     */
    public function getTariff() : TariffSchedule;

}
