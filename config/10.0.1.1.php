<?php

// Mostly for testing

namespace Ensemble;

// Logging device
$conf['devices'][] = new Device\LoggerDevice('global.log', new Log\TextLog(_VAR.'global.log'));

// Create a test device that just generates log messages
class LogGenerator implements Module {
    public function action(Command $c, CommandBroker $b) {

    }

    public function isBusy() {
        return false;
    }

    public function getPollInterval() {
        return 5;
    }

    public function announce() {
        return false;
    }

    private $n = 0;
    public function poll(CommandBroker $b) {
        $b->send(Command::create($this, 'global.log', 'log', array('message'=>"Test log message ".($this->n++))));
        $b->send(Command::create($this, 'remote.log', 'log', array('message'=>"Test remote log message ".($this->n++))));

    }

    public function getDeviceName() {
        return 'test.LogGenerator';
    }
}

$conf['devices'][] = new LogGenerator();
