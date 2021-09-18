<?php

namespace Ensemble\Device;

trait DeviceLogging {
    /**
     * Allow a logging device to be set, and used for logging
     */
    private $logger = false;
    public function setLogDevice($name, \Ensemble\CommandBroker $broker) {
        $this->logger = $name;
        $this->via = $broker;
    }

    // So that we can overload but still call this method, give it two names!
    protected function __log($message) {
        echo '['.date('Y-m-d H:i:s').'] ('.$this->getDeviceName().') '.trim($message)."\n";

        if(!$this->logger)
            return;

        $c = \Ensemble\Command::create($this, $this->logger, 'log', array('message'=>$message));
        $this->via->send($c);
    }

    public function log($message) {
        $this->__log($message);
    }
}
