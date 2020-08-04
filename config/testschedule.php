<?php

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Schedule as Schedule;

// Create a context device to broker schedules
$conf['devices'][] = $ctx = new Device\ContextDevice('global.schedules');

/**
 * Create a daily scheduler with a test period in just a moment...
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'OFF');
$bsched->setPoint(time() + 20, 'ON');
$bsched->setPoint(time() + 40, 'OPOFF');

$sd = new Schedule\DailyScheduler('daily.scheduler', 'global.schedules', 'testschedule', $bsched);
$conf['devices'][] = $sd;

// Create a socket to be controlled and bind it to the schedule in the broker
$client = new MQTTClient('10.0.0.8', 1883);
$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket8", $client, "socket8", 'global.schedules', 'testschedule');
($conf['devices'][] = $socket->getPowerMeter())->addDestination('global.context', 'power-test');
