<?php

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\MQTT\Bridge as MQTTBridge;
use Ensemble\Schedule as Schedule;
use Ensemble\Device\Light as Light;

date_default_timezone_set('Europe/London');

// Create a context device to broker schedules
$conf['devices'][] = $ctx = new Device\ContextDevice('test.schedules');

/**
 * Create a daily scheduler with a test period in just a moment...
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', '100');
$bsched->setPoint('08:45:00', '0'); // Reopen in the morning
$bsched->setPoint('12:00:00', 'auto'); // Afternoons, use the auto-closer based on sun
$bsched->setPoint('21:30:00', 'dusk'); // Close at night

$sd = new Schedule\DailyScheduler('daily.scheduler', 'test.schedules', 'testblindschedule', $bsched);
$conf['devices'][] = $sd;


// Create a socket to be controlled and bind it to the schedule in the broker
$client = new MQTTClient('10.0.0.8', 1883);
$conf['devices'][] = $bridge = new MQTTBridge('test-bridge', $client);
$conf['devices'][] = $socket = new Device\Blind\ScheduledBlind("blind1-test", $bridge, "blind1", new Device\ContextPointer('test.schedules', 'testblindschedule'));
