<?php

/**
 * A little class for pointing to a context value; makes some other function
 * signatures a bit tidier, and presents some convenience methods
 */
namespace Ensemble\Device;
use Ensemble\Device\FetchContextRoutine as FetchRoutine;
use Ensemble\Async as Async;

class ContextPointer {

    public function __construct($device, $field) {
        $this->device = $device;
        $this->field = $field;
    }

    public function getDeviceName() {
        return $this->device;
    }

    public function getFieldName() {
        return $this->field;
    }

    /**
     * Get a fetch routine using the given async device
     */
    public function getFetchRoutine(Async\Device $requester) {
        return new FetchRoutine($requester, $this->device, $this->field);
    }

    /**
     * Set the value of the field by sending a command to the context
     */
     public function setContextSeries($value, Device $requester) {
         $cmd = \Ensemble\Command::create($requester, $this->contextdevice, 'updateContext');
         $cmd->setArg('field', $this->field);
         $cmd->setArg('series', $value);
         $requester->getBroker()->send($cmd);
     }
     

    public function toString() {
        return "[{$this->device}/{$this->field}]";
    }
}
