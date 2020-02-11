<?php

namespace Ensemble;

interface Module {

    /**
     * Get this device's self-declared name
     */
    public function getDeviceName();

    /**
     * Indicate whether this device should be announced to remote nodes
     */
    public function announce();

    /**
     * Action the given command.  Modules may assume that the command given here
     * is really for them, ie there's no need to check that the target is correct
     * and a reference to the originating command broker is passed in.
     */
    public function action(Command $command, CommandBroker $broker);

    /**
     * If the device handles commands asynchronously then it needs to indicate
     * that it's busy to avoid being given additional commands; this function
     * should return true if the device is busy and can't accept more commands.
     */
    public function isBusy();

    /**
     * Get the interval, in seconds, that this module would like to polled
     * Intervals aren't guaranteed, modules could be polled more or less
     * often
     */
    public function getPollInterval();

    /**
     * Trigger routine actions
     */
    public function poll(CommandBroker $broker);
}
