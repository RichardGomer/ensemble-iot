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

// Create the tariff scheduler
$conf['devices'][] = new Schedule\OctopusTariffDevice('tariffscheduler', 'store.context', 'electariff', $oct);

class ForecastTestDevice extends Async\Device {

    public function __construct($client) {
        $this->name = '*octopustest';
        $this->client = $client;
    }

    public function getRoutine() {
    $device = $this;
        return new Async\Lambda(function() use ($device) {
            $prices = $device->client->getTariffSchedule();
            $baseTariff = new Schedule\TariffSchedule($prices);
            yield;

            echo "Prices\n";
            echo $prices->prettyPrint();
            echo $baseTariff->prettyPrint();

            var_dump($baseTariff->getAllPeriods());

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

        });
    }
}

$conf['devices'][] = new ForecastTestDevice($oct);
