<?php

namespace Ensemble\Device;

trait DeviceLogging {
    /**
     * Allow a logging device to be set, and used for logging
     */
    private $logger = false;
    public function setLogDevice($name) {
        $this->logger = $name;
    }

    // So that we can overload but still call this method, give it two names!
    protected function __log($message,  \Ensemble\CommandBroker $via) {
        echo '['.$this->getDeviceName().'] LOG: '.trim($message)."\n";

        if(!$this->logger)
            return;

        $c = \Ensemble\Command::create($this, $this->logger, 'log', array('message'=>$message));
        $via->send($c);
    }

    public function log($message,  \Ensemble\CommandBroker $via) {
        $this->__log($message, $via);
    }
}
