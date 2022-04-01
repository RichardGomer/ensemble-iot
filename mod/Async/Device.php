<?php

/**
 * This class provides basic functionality of an async device. It spawns an
 * async operation, but also provides an event handler so that replies etc.
 * can be waited on
 *
 */
namespace Ensemble\Async;

abstract class Device implements \Ensemble\Module {

    use \Ensemble\Device\DeviceLogging;

    /**
     * Basic module support
     */
     public function announce() {
         return true;
     }

    public function isBusy() {
        return false;
    }

    final public function getDeviceName() {
        if($this->name == false) {
            throw new \Exception("Device name is not set in instance of ".get_class($this));
        }

        return $this->name;
    }

    public function getChildDevices() {
        return false;
    }

    public function getPollInterval() {
        return 0.1; // We can request frequent polling because the broker prioritises incoming commands over polling anyway
    }

    /**
     * A CommandBroker is passed into each poll() and action(), so in theory one device
     * could be bound to multiple brokers. BUT that means passing brokers around all
     * over the place, so Async devices just keep a reference to the first CommandBroker
     * that they see; use getBroker() to get it
     */
    private $broker = false;
    protected function setBroker(\Ensemble\CommandBroker $b) {
        if(!$this->broker) {
            $this->broker = $b;
        }
    }

    final public function getBroker() {
        return $this->broker;
    }

    public function log($msg) {
        $this->__log($msg);
    }

    /**
     * Commands aren't handled immediately; they're put into a heap so that the
     * main routine can respond to them
     */
     protected $commands = array();
     public function action(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
         $this->setBroker($b); // It's (just) possible that the first command is received before the device is polled, so... this.

         $this->commands[] = $c;

         $this->poll($b); // Poll in case any ops were waiting for that command; we could be more efficient about this!
     }

     /**
      * Get all the commands that are in the heap
      */
     public function getCommands() {
         return $this->commands;
     }

     /**
      * Remove the given command from the heap
      */
     public function removeCommand(\Ensemble\Command $c) {
         foreach($this->commands as $i=>$cc) {
             if($cc === $c) {
                 unset($this->commands[$i]);
             }
         }
     }

     /**
      * Polling checks the status of outstanding operations
      */
     protected $op = false;
     public function poll(\Ensemble\CommandBroker $b) {
         $this->setBroker($b);

         if(!$this->op) {
             $this->op = new Controller($this->getRoutine());
         }

         $this->continue();
     }

     public function continue() {
         if(!$this->op) return;
         $this->op->continue(); // Continue the async operation
         if($this->op->isComplete()) {
             $this->op = false;
         }
     }

     /**
      * This must return an \Ensemble\Async\AsyncRoutine to be executed. When it completes, this
      * method will be called again for a new one.
      */
     abstract protected function getRoutine();
}

/**
 * Wait for an action of the specified type to be received by the device
 * The received action is returned.
 */
class WaitForCommand implements Routine {
    public function __construct(Device $device, $actionTypes) {
        $this->device = $device;

        if(!is_array($actionTypes)) {
            $actionTypes = array($actionTypes);
        }

        $this->actionTypes = $actionTypes;
    }

    public function execute(){
        while(true) {
            foreach($this->device->getCommands() as $c) {
                if(in_array($c->getAction(), $this->actionTypes)) {
                    $this->device->removeCommand($c);
                    return $c;
                }
            }

            yield;
        }
    }
}

/**
 * Waits for a reply or exception to be received in response to the given command
 * the command must already have been sent!
 */
class WaitForReply implements Routine {
    public function __construct(Device $device, \Ensemble\Command $c) {
        $this->device = $device;
        $this->command = $c;
    }

    public function execute(){
        while(true) {
            foreach($this->device->getCommands() as $c) {
                if($c->getAction() == '_reply' || $c->getAction() == '_exception') {
                    if($c->getFollows() == $this->command->getID()) {
                        $this->device->removeCommand($c);
                        return $c;
                    }
                }
            }

            yield;
        }
    }
}

/**
 * Wait until the given time
 */
class waitUntil implements Routine {
    public function __construct($time) {
        $this->end = $time;
    }

    public function execute() {
        while(time() < $this->end) {
            yield;
        }
    }
}

/**
 * Wait for an amount of time to elapse
 * (This is advisory, the actual delay could be longer!)
 */
class waitForDelay extends WaitUntil {
    public function __construct($delay) {
        parent::__construct(time() + $delay);
    }
}
