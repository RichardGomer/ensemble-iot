<?php

namespace Ensemble\Device\Socket;
use Ensemble\MQTT;
use Ensemble\Async;

/**
 * Timed sockets run for a period of time after being triggered by another
 * socket.
 * Set this up by attaching to the status data in anotherr socket, e.g.:
 *
 *   $controllerSocket->getStatus()->sub('SENSOR.ENERGY.POWER', array($TimedSocket, 'trigger'));
 *
 */
class TimedSocket extends Socket  {

    $confirmTime =

    /**
     * runTime = time to run, in seconds, after the last trigger
     */
    public function __construct($name, MQTT\Bridge $bridge, $deviceName, $powerNum="", $runTime=900) {
        parent::__construct($name, $bridge, $deviceName, $powerNum);

        $this->runTime = $runTime;
        $this->lastTrigger = 0;
        $this->firstTrigger = 0;
    }

    // Set this socket to only run when the controller switches OFF
    private $offOnly = false;
    public function setOffOnly($offonly = true) {
        $this->offOnly = $offonly;
    }

    public function trigger($field, $watts) {

        if($watts < 1) { // First handle cases where power is off
            if(is_infinite($this->lastTrigger)) { // Set trigger time when power is turned off
                $this->log("Power turned OFF ({$watts}W)");
                $this->lastTrigger = time();
                $this->firstTrigger = 0;
            } else { // Power already off; do nothing
                $this->log("Power is still OFF ({$watts}W)");
            }
        }
        else { // Power is ON
            if ($this->firstTrigger == 0) { // Power just came on
                $this->log("Power turned ON ({$watts}W)");
                $this->firstTrigger = time();
            } else { // Otherwise power is on, run
                if($this->firstTrigger > time() - 20) { // Don't trigger for 20 seconds, avoids false triggers
                    $this->log("Power has been on less than 20s, waiting to confirm");
                } else {
                    $this->log("Power is ON ({$watts}W)");
                    $this->lastTrigger = INF;
                }
            }
        }
    }

    public function getRoutine() {
        $dev = $this;
        return new Async\Lambda( function() use ($dev) {

            $t = is_infinite($this->lastTrigger) ? "NOW" : date('Y-m-d H:i:s', $this->lastTrigger);

            $this->log("Timed socket last triggered: $t");

            if((
                 is_infinite($this->lastTrigger) && !$this->offOnly) ||
                !is_infinite($this->lastTrigger) && $dev->lastTrigger + $dev->runTime > time()
            ) {
                $dev->on();
            } else {
                $dev->off();
                return;
            }

            yield new Async\WaitForDelay(20);

            // Check that a trigger hasn't happened since we went to sleep
            if(!is_infinite($this->lastTrigger) && $dev->lastTrigger + $dev->runTime <= time()) {
                $dev->off();
            }
        });
    }
}
