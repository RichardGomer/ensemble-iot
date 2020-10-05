<?php

/**
 * A basic device module that provides some helper functions
 */
namespace Ensemble\Device;

abstract class BasicDevice implements \Ensemble\Module {

    use DeviceLogging;

    protected $name = false;

    public function announce() {
        return true;
    }

    public function poll(\Ensemble\CommandBroker $b) {

    }

    public function getPollInterval() {
        return false;
    }

    final public function getDeviceName() {
        if($this->name == false) {
            throw new \Exception("Device name is not set in instance of ".get_class($this));
        }

        return $this->name;
    }

    /**
     * To avoid writing routing code in action(), just use registerAction
     * to attach object methods to different actions
     */
    private $actions = array();
    public function registerAction($action, $object, $method) {
        $this->actions[$action] = array($object, $method);
    }

    final public function action(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
        $action = $c->getAction();
        if(!array_key_exists($action, $this->actions)) {
            return false;
        }

        call_user_func($this->actions[$action], $c, $b);
    }

    public function isBusy() {
        return false;
    }

    // Maintain a list of handlers for replies
    private $replies = array();

    /**
     * Register a handler for when (if..) we receive a reply to the given command
     * Reply handlers are only kept for the period specified by timeout; to avoid
     * resource exhaustion. Only a single reply is accepted for each command.
     */
    public function onReply(\Ensemble\Command $cmd, $object, $method, $timeout=60) {
        $this->registerAction('_reply', $this, 'action_reply');
        $this->replies[$cmd->getID()] = array('handler' => array($object, $method), 'timeout'=>time() + $timeout);

        // Clean up other expired handlers
        foreach($this->replies as $i=>$r) {
            if($r['timeout'] < time()) {
                unset($this->replies[$i]);
            }
        }
    }

    protected function action_reply(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
        $follows = $cmd->getFollows(); // This should be the ID of the original command
        if(array_key_exists($follows, $this->replies)) {
            call_user_func($this->replies[$follows]['handler'], $c, $b);
            unset($this->replies[$follows]);
        }
    }
}
