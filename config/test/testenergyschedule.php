<?php

/**
 * Test energy scheduling
 *
 */

namespace Ensemble;
use Ensemble\Schedule as Schedule;


// Create an Octopus API client
require 'dbcreds.php'; // Keep account info out of git!
$oct = new Schedule\Octopus($octo_key);
$oct->setTimeSpan(10); // Set the time period in days that the client will ask for
$oct->setTariff($octo_prodcode, $octo_trfcode);

// Test quick scheduler
$conf['devices'][] = $isched = new Schedule\QuickSchedulerDevice('immersionscheduler');
$isched->setContext('testschedule', 'test.schedules');

// Create the tariff scheduler
$conf['devices'][] = $td = new Schedule\OctopusTariffDevice('tariffscheduler', 'store.context', 'electariff', $oct);
$td->setCallback(function($prices) use ($isched) {
    //echo "GOT PRICES\n";
    $baseTariff = new Schedule\TariffSchedule($prices);

    echo "Prices\n";
    echo $prices->prettyPrint();
    echo $baseTariff->prettyPrint();

    echo "Night\n";
    $nightTariff = $baseTariff->between('23:00', '06:00');
    echo $nightTariff->prettyPrint();

    echo "Cheapest 2h\n";
    $cheap = $nightTariff->cheapest(120);
    echo $cheap->prettyPrint();

    echo "Cheapest + Cheaper than Gas\n";
    // 3.5p/kwh ~= 3.03p/kwh / 90% efficiency
    $immersion = $nightTariff->lessThan(3.5)->cheapest(120);
    echo $immersion->prettyPrint();

    $isched->setSchedule($immersion);
});
