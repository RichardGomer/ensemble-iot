<?php

namespace Ensemble\Device;
use Ensemble\Async as Async;

/**
 * An Async routine for fetching data from a context device
 */

class FetchContextRoutine implements Async\Routine {

    /**
     * $device: The device that owns this request; it will be used for sending/receiving commands
     * $context_device: The name of the context device to request the schedule from
     * $context_field: The name of the context field containing the request
     */
    public function __construct(Async\Device $device, $context_device, $context_field) {
        $this->device = $device;
        $this->context_device = $context_device;
        $this->context_field = $context_field;
    }

    public function execute() {
        $c = \Ensemble\Command::create($this->device, $this->context_device, 'getContext', array('field' => $this->context_field));
        $this->device->getBroker()->send($c);
        $rep = yield new Async\WaitForReply($this->device, $c);

        if($rep->isException()) {
            throw new FetchException("Couldn't fetch context: ".$rep->getArg('message'));
        }

        //var_dump($rep);

        return $rep->getArg('values')[0]['value'];
    }
}

class FetchException extends \Exception {}
