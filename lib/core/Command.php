<?php

namespace Ensemble;

class Command {
    protected $id;
    protected $source;
    protected $target;
    protected $action;
    protected $args;
    protected $expires;
    protected $follows;

    protected function __construct($source, $target, $action) {
        $this->target = $target;
        $this->action = $action;
        $this->source = $source;
    }

    public static function create(Module $source, $target, $action, $args = array()) {
        $cmd = new Command($source->getDeviceName(), $target, $action);
        $cmd->setArgs($args);
        $cmd->id = uniqid("", true).'@'.$source->getDeviceName();
        return $cmd;
    }

    // Copy this command and set a new target
    public function copyTo($target) {
        $cmd = clone $this;
        $cmd->target = $target;
    }

    // Get a reply command
    // $args can be an Exception, in which case an exception response is sent
    public function reply($args) {
        if($args instanceof \Exception) {
            $action = "_exception";
            $args = array('message' => get_class($e).": ".$e->getMessage());
        } else {
            $action = "_reply";
        }

        $cmd = new Command($this->getTarget(), $this->getSource(), $action);
        $cmd->Args = $args;
        $cmd->follows = $this->getID();

        return $cmd;
    }


    public function __toString() {
        return "({$this->getID()}) {$this->getSource()} -> {$this->getTarget()}::{$this->getAction()}() ";
    }

    /**
     * Restore a command from a JSON string or array
     */
    public static function fromJSON($json) {
        if(is_string($json)) {
                $json = json_decode($json, true);
        }

        if(!is_array($json))
        {
            var_dump($json);
            throw new BadCommandException("Command string is not valid JSON");
        }

        if(
            !array_key_exists('source', $json) ||
            !array_key_exists('target', $json) ||
            !array_key_exists('action', $json) ||
            !array_key_exists('args', $json) ||
            !array_key_exists('id', $json) ||
            !array_key_exists('expires', $json) ||
            !array_key_exists('follows', $json)
        ) {
            throw new BadCommandException("Command string is missing required field(s)");
        }

        $cmd = new Command($json['source'], $json['target'], $json['action']);
        $cmd->args = $json['args'];
        $cmd->id = $json['id'];
        $cmd->expires = $json['expires'];
        $cmd->follows = $json['follows'];

        return $cmd;
    }

    // Convert the command to a JSON string
    public function toJSON() {
        return json_encode(array(
            'id'=>$this->id,
            'source'=>$this->source,
            'target'=>$this->target,
            'action'=>$this->action,
            'args'=>$this->args,
            'expires'=>$this->expires,
            'follows'=>$this->follows
        ));
    }


    public function getID() {
        return $this->id;
    }

    public function getTarget() {
        return $this->target;
    }

    public function getAction() {
        return $this->action;
    }

    public function getSource() {
        return $this->source;
    }

    public function getExpires() {
        return $this->expires;
    }

    public function setExpires($expires) {
        $tihs->expires = $expires;
    }

    public function isExpired() {
        return $this->expires == false ? false : $this->expires > time();
    }

    public function setArgs($args) {
        $this->args = $args;
    }

    public function setArg($name, $value) {
        $this->args[strtolower($name)] = $value;
    }

    // Get all args; if $check contains values, check that all those keys are
    // defined else throw an ArgNotSetException
    public function getArgs($check=array()) {

        foreach($check as $k) {
            $this->getArg($k);
        }

        return $this->args;
    }

    public function getArg($name) {
        $name = strtolower($name);
        if(!array_key_exists($name, $this->args)) {
            throw new ArgNotSetException("'$name' argument is not set");
        } else {
            return $this->args[$name];
        }
    }

    /**
     * If arg with $name is set, return it, otherwise return the specified value
     */
    public function getArgOrValue($name, $value) {
        try {
            return $this->getArg($name);
        } catch (ArgNotSetException $e) {
            return $value;
        }
    }

    public function getFollows() {
        return $this->follows;
    }

    public function setFollows($id) {
        if($id instanceof Command) {
            $id = $id->getID();
        }

        $this->follows = $id;
    }
}

class BadCommandException extends \Exception {};
class ArgNotSetException extends \Exception {};
