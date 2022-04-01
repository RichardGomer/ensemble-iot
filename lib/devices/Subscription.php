<?php

namespace Ensemble\Device;

use Ensemble\Command;
use Ensemble\CommandBroker;

/**
 * Subscriptions forward events from one device to another. Effectively, actions on
 * device A can be used like events to trigger things on device B (C,D,E...)
 *
 * Some minimal wiring is required in the device itself, so that events are passed
 * in the control logid defined in this trait.
 */

trait Subscription {

    private $subs = array();

    /**
     * Subscribe the named device to an action of the given type
     * The action will be copied to that device
     */
    public function subAction($type, $deviceOrDeviceName) {
        if(!array_key_exists($type, $this->subs)) {
            $this->subs[$type] = array();
        }

        if($deviceOrDeviceName instanceof \Ensemble\Module) {
            $dname = $deviceOrDeviceName->getDeviceName();
        } else {
            $dname = (String) $deviceOrDeviceName;
        }

        $this->subs[$type][] = $dname;
    }

    /*
     * Publish the action to any subscribers
     */
    protected function pubAction(Command $a, CommandBroker $broker) {
        if(array_key_exists($a->getAction(), $this->subs)) {
            $subs = $this->subs[$a->getAction()];
            foreach($subs as $target) {
                $ncmd = $a->copyTo($target);
                $broker->send($ncmd);
            }
        }
    }

}
