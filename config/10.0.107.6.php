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
	echo "Yield to wait for command\n";
            $action = yield new WaitForCommand($device, ['water', 'splash']);

            $seconds = $action->getArg('seconds');

	echo "Received {$action->getAction()}\n";

            // Normal watering mode
            if($action->getAction() == "water") {
                $this->on();
                yield new waitForDelay($seconds);
                $this->off();
            }
            // Random splash mode for Freddie <3
            elseif($action->getAction() == "splash") {
                $minInterval = 0.3; // Min non-squirting interval length
                $maxInterval = 1; // and max
                $minLength = 4; // Min squirt length in s
                $maxLength = 8; // Max squirt length in s

                $end = time() + $seconds;

		echo "Begin squirting\n";
		$this->on();
                do {
                    $pause = rand($minInterval * 1000, $maxInterval * 1000);
                    $length = rand($minLength * 1000, $maxLength * 1000) / 1000;

	            $length = min($length, $end - time()); // Cap time to the scheduled end of squirting
		    echo "Squirt $length\n";
                    yield new waitForDelay($length);

		    echo "Pause $pause\n";
                    $this->off();
                    usleep($pause * 1000);
                    $this->on();
                } while(time() < $end);
		$this->off();
            }
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
$v1 = new HoseControl("hose1", $v1_r1 = new Relay([Pin::bcm(19, Pin::OUT)])); // First relay (NC) is initialised and set to off
$v1_r1->off();
$v1_r2 = new Relay([Pin::BCM(16, Pin::OUT)], 0); // Use the other relay to enable control
$v1_r2->on();

$conf['devices'][] = $v1;


// Valve 2
$v2 = new HoseControl("hose2", $v2_r1 = new Relay([Pin::bcm(20, Pin::OUT)])); // First relay (NC) is initialised and set to off
$v2_r1->off();
$v2_r2 = new Relay([Pin::BCM(26, Pin::OUT)], 0); // Use the other relay to enable control
$v2_r2->on();

$conf['devices'][] = $v2;


