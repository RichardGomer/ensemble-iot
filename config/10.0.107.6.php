<?php

/**
 * Mains irrigation controller
 */
namespace Ensemble;

use Ensemble\Async\Lambda;
use Ensemble\Async\WaitForCommand;
use Ensemble\Async\waitForDelay;
use \Ensemble\GPIO\Pin;
use \Ensemble\GPIO\Relay;

date_default_timezone_set('Europe/London');
$conf['default_endpoint'] = 'http://10.0.0.8:3107/ensemble-iot/1.0/';


// We use a local context to reduce network dependency
$ctx = new Device\ContextDevice("hose.context");
$ctx->addSuperContext("global.context");
$conf['devices'][] = $ctx;

class HoseControl extends \Ensemble\Async\Device {

    private Relay $relay;

    public function __construct($name, Relay $relay) {
        $this->relay = $relay;
        $this->name = $name;
    }

    public function getRoutine() {
        $device = $this;
        return new Lambda( function() use ($device) {
            // Wait for actions
           $action = yield new WaitForCommand($device, ['water']);

            $seconds = $action->getArg('seconds');

            $this->on();
            yield new waitForDelay($seconds);
            $this->off();
        });
    }

    public function on() {
        $this->relay->on();
    }

    public function off() {
        $this->relay->off();
    }

}

// Valve 1
$v1 = new HoseControl("hose1", $v1_r1 = new Relay([19])); // First relay (NC) is initialised and set to off
$v1_r1->off();
$v1_r2 = new Relay([16], 0); // Use the other relay to enable control
$v1_r2->on();

