<?php

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Schedule as Schedule;


// Create an Octopus API client
require 'dbcreds.php'; // Keep account info out of git!
$oct = new Schedule\Octopus($octo_key);
$oct->setTimeSpan(10); // Set the time period in days that the client will ask for
$oct->setElecMeter($octo_elec_meter_mpan, $octo_elec_meter_serial);
$oct->setGasMeter($octo_gas_meter_mprn, $octo_gas_meter_serial);
$oct->setTariff($octo_prodcode, $octo_trfcode);

// Create a logging context device - this logs to the real database!
//$conf['devices'][] = $ctx = new Device\LoggingContextDevice('store.context', "mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
$conf['devices'][] = $ctx = new Device\ContextDevice('store.context');


// Create the tariff scheduler
$conf['devices'][] = new Schedule\OctopusTariffDevice('tariffscheduler', 'store.context', 'electariff', $oct);

/*
// Create the usage schedulers
$conf['devices'][] = new Schedule\OctopusGasUsageDevice('gasusagescheduler', 'store.context', 'gasusage', $oct);
$conf['devices'][] = new Schedule\OctopusElecUsageDevice('elecusagescheduler', 'store.context', 'elecusage', $oct);

class ForecastTestDevice extends Async\Device {

    public function __construct($client) {
        $this->name = '*octopustest';
        $this->client = $client;
    }

    public function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {

            //var_dump($device->client->getGasUsage());
            //var_dump($device->client->getTariffSchedule());
            yield;

        });
    }
}

$conf['devices'][] = new ForecastTestDevice($oct);
*/
