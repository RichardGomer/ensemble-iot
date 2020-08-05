<?php

/**
 * A logger device stores received messages in a log file
 *
 */
namespace Ensemble\Device;

class LoggerDevice extends BasicDevice {
    public function __construct($name, \Ensemble\Log\TextLog $log) {
        $this->log = $log;
        $this->name = $name;

        $this->registerAction('log', $this, 'a_log');
    }

    public function a_log(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
        $this->log->log("[{$c->getSource()}] ".$c->getArgs()['message']);
    }
}
