<?php

/**
 * A context device maintains context data for access by other devices
 */
namespace Ensemble\Device;

// Need to be able to pass received messages up to a parent context; so we can
// have local contexts for locally-coupled devices and also remote contexts

class ContextDevice extends BasicDevice {

    public function __construct($name) {
        $this->name = $name;

        $this->registerAction('updateContext', $this, 'action_update');
        $this->registerAction('getContext', $this, 'action_get');
    }

    /**
     * A supercontext will receive a copy of all our updates
     */
    public function addSuperContext($devicename) {
        $this->supers[] = $devicename;
    }

    /**
     * Respond to context requests
     */
    public function action_get(\Ensemble\Command $cmd, \Ensemble\CommandBroker $b) {
        if(array_key_exists($args['field'], $this->data)) {
            $reply = $cmd->reply($this->data[$args['field']]);
        } else {
            $reply = $cmd->reply(new \Exception("Field {$args['field']} isn't set"));
        }

        $b->send($reply);
    }

    /**
     * Update a field
     */
    public function action_update(\Ensemble\Command $cmd, \Ensemble\CommandBroker $b) {

        // If the field exists, only update it if this value is newer than the last
        if(array_key_exists($args['field'], $this->data)) {
            if($args['time'] <= $this->data[$args['field']]['mtime']) {
                return;
            }
        }

        $this->data[$args['field']] = array(
            'mtime'=>$args['time'],
            'source'=>$cmd->getSource(),
            'value'=>$args['value']
        );

        // Copy the update to supercontexts
        foreach($this->supers as $s) {
            $cmd->copyTo($s);
            $b->send($cmd);
        }
    }

}
