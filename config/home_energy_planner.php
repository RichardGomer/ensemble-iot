<?php


/**
 * Energy planner creates a power plan for the day, an estimate of how much power
 * will be available at different times, and schedules for devices.
 */

namespace Ensemble;

use Ensemble\Async;

use Ensemble\Device\EnergyPlan\EnergyPlan;
use Ensemble\Device\EnergyPlan\EnergySchedule;

use Ensemble\Schedule\OnSchedule;
use Ensemble\Schedule\DailyProjector;
use Ensemble\Schedule\Printer;

require 'dbcreds.php';

// Local context
$conf['devices'][] = $sctx = new Device\ContextDevice('energyplan.context');


/**
 * Solcast solar forecast
 */
$conf['devices'][] = $solcast = new Device\EnergyPlan\SolcastDevice('solcast', $solcast_key, $solcast_site);
$solcast->setContext('energyplan.context', 'solcast', Schedule\SchedulerDevice::MODE_JSON);


// Update the energy plan every 2 hours
$conf['devices'][] = new Async\Regularly(7200, function($device) {

    yield new Async\WaitForDelay(1);

    $plan = new EnergyPlan();

    // Set up the battery
    $plan->setStorage(8.2);

    // First we get some stuff from the context broker
    try {
        $solcast = yield new Async\TimeoutController((new Device\ContextPointer('energyplan.context', 'solcast'))->getFetchRoutine($device), 10);
    }
    catch(\Exception $e) {
        echo "No solar forecast available: {$e->getMessage()}\n";
        return;
    }

    $ssolcast = EnergySchedule::fromJson($solcast);
    $plan->addGeneration("solar", $ssolcast);

    // Then set up the daily discharging period - ie the times that the battery is available
    $dischargeSch = new OnSchedule();
    $dischargeSch->setTimezone('Europe/London');
    $dischargeSch->setPeriod('00:00', '00:30', 'ON');
    $dischargeSch->setPeriod('00:30', '04:30', 'OFF');
    $dischargeSch->setPeriod('04:30', '24:00', 'ON');
    $plan->setDischarge($dischargeSch);

    // And the daily grid charging period
    $chargeSch = new OnSchedule();
    $chargeSch->setTimezone('Europe/London');
    $chargeSch->setPeriod('00:00', '00:30', 'OFF');
    $chargeSch->setPeriod('00:30', '04:30', 'ON');
    $chargeSch->setPeriod('04:30', '24:00', 'OFF');
    echo "Base charge schedule:\n\n".$chargeSch->prettyPrint();
    $plan->setGridCharge($chargeSch, 3); // Max 3kw charge

    // Add some base load
    $base = new EnergySchedule();
    $base->setPeriods('00:00', '24:00', -0.350);
    $basep = new Schedule\DailyProjector($base);

    $plan->addConsumption('baseload', $basep->project(time(), time() + 3 * 24 * 3600 + 7200));

    // TODO: Set current storage from battery

    $out = $plan->getPlan();

    $pr = new Printer();
    foreach($out as $name=>$s) {
        $pr->addSchedule($name, $s);
    }

    echo $pr->print();
});
