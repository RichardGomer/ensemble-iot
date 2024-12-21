<?php

namespace Ensemble;

use Ensemble\Device\EnergyPlan\EnergyPlanner;
use Ensemble\Device\EnergyPlan\GivEnergyPredictor;
use Ensemble\Device\EnergyPlan\InverterControlDevice;
use Ensemble\Device\EnergyPlan\SolcastPredictor;
use Ensemble\Schedule as Schedule;
use Ensemble\Schedule\Printer;
use Ensemble\Schedule\TariffSchedule;


require 'home_common.inc.php';

// Create a context device
$conf['devices'][] = $ctx = new Device\ContextDevice('test.context');
$ge = new Device\EnergyPlan\GivEnergyAccount($givenergy_key, $givenergy_username);

echo "
*******************************************************************************************
     ENERGY PLANNER
*******************************************************************************************

STARTING TESTS

";

// Get the inverter, and get some devices from that
$inverters = $ge->getInverters();
$inv = array_pop($inverters);

echo "Current load: {$inv->getCurrentLoad()}W\n";
echo "Solar power: {$inv->getSolarPower()}W\n";
echo "Battery SoC: {$inv->getBatterySOC()}KWh\n";

/**
 * Get a demand prediction
 */
$bp = new GivEnergyPredictor($inv);
echo "Demand Prediction:\n";

$printer = new Printer();
/* For comparing different estimation periods
foreach([3,7,14,30,365] as $days) {
    $printer->addSchedule("Dmnd[".$days."]", $bp->getDemandPrediction($days));
}
*/
$printer->addSchedule("Dmnd", $bp->getDemandPrediction(7));

/**
 * Solcast solar forecast
 */
$solcastsource = new SolcastPredictor($solcast_key, $solcast_site);
$solcast = $solcastsource->getSolarPrediction();

$printer->addSchedule("PV", $solcast);

/**
 * Get tariff information
 */
$oct = new Schedule\Octopus($octo_key);
$oct->setTimeSpan(10); // Set the time period in days that the client will ask for
$oct->setElecMeter($octo_elec_meter_mpan, $octo_elec_meter_serial);
$oct->setGasMeter($octo_gas_meter_mprn, $octo_gas_meter_serial);
$oct->setTariff($octo_prodcode, $octo_trfcode);

$imtariff = $oct->getTariff();


$extariff = new TariffSchedule();
$extariff->setPoint(0, 15);

$printer->addSchedule("Import", $imtariff);
$printer->addSchedule("Export", $extariff);

echo $printer->print();

// Create the planner
$planner = new EnergyPlanner($bp, $solcastsource, $oct, $extariff, $inv);

// Generate a test plan
$plan = $planner->getPlan();
$printer = new Printer();
foreach($plan as $k=>$s) {
    $printer->addSchedule("$k", $s);
}
echo $printer->print();

echo "

TESTS ARE COMPLETE.

";


/**
 * Use the optimiser to plan and execute energy management
 */


// Use the charge and discharge schedules to configure the inverter
$conf['devices'][] = $powermgr = new InverterControlDevice($planner);
