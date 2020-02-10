<?php

/**
 * Heating controller device
 */

namespace Ensemble\Device\Heating;

class HeatingDevice extends \Ensemble\Device\BasicDevice {

    public function __construct($name, Relay $relay, Schedule $schedule) {
        $this->schedule = $schedule;
        $this->relay = $relay;
        $this->name = $name;

        $this->registerAction('boost', $this, 'action_boost');
    }

    public function getSchedule(Schedule $s) {
        return $this->schedule;
    }

    public function action_boost(\Ensemble\Command $command) {
        $mins = $command->getArgOrValue('mins', 60);
        $this->schedule->addTemporary($mins);
        $this->poll(); // No point in waiting!
    }

    public function getPollInterval() {
        return 30;
    }

    public function poll(\Ensemble\CommandBroker $b) {

        $time = time();

        $state = $this->schedule->getState();

        if($state === 'ON') {
            $this->relay->on();
        } else {
            $this->relay->off();
        }
    }

}
