<?php

// Mostly for testing

namespace Ensemble;

$conf['default_endpoint'] = 'http://10.0.0.8:3107/ensemble-iot/1.0/';


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
        $b->send(Command::create($this, 'global.context', 'updateContext', array('time'=>time(), 'field'=>'randField', 'value'=>rand(1,99999))));
    }

    public function getDeviceName() {
        return 'test.LogGenerator';
    }
}

$conf['devices'][] = new LogGenerator();
