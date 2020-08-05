<?php

/**
 * A context device maintains context data for access by other devices
 */
namespace Ensemble\Device;

// Need to be able to pass received messages up to a parent context; so we can
// have local contexts for locally-coupled devices and also remote contexts

class ContextDevice extends BasicDevice {

    private $valuetimelimit = 3600 * 2; // Values older than this may be discarded

    private $data = array();

    public function __construct($name) {
        $this->name = $name;

        $this->registerAction('updateContext', $this, 'action_update');
        $this->registerAction('getContext', $this, 'action_get');
    }

    /**
     * A supercontext will receive a copy of all our updates
     */
    private $supers = array();
    public function addSuperContext($devicename) {
        $this->supers[] = $devicename;
    }

    /**
     * Respond to context requests
     */
    public function action_get(\Ensemble\Command $cmd, \Ensemble\CommandBroker $b) {

        $args = $cmd->getArgs(array('field'));

        $args['num'] = $cmd->getArgOrValue('num', 1);

        if(array_key_exists($args['field'], $this->data)) {
            $data = $this->get($args['field'], $args['num']);
            $reply = $cmd->reply(array('values'=>$data));
        } else {
            $reply = $cmd->reply(new \Exception("Field {$args['field']} isn't set"));
        }

        $b->send($reply);
    }

    public function get($field, $num=1) {
        if(!array_key_exists($field, $this->data)) {
            return array();
        }

        $all = $this->data[$field];

        usort($all, function($a, $b) {
            return $b['time'] - $a['time'];
        });

        return array_slice($all, count($all) - $num, $num);
    }

    /**
     * Update a field
     */
    public function action_update(\Ensemble\Command $cmd, \Ensemble\CommandBroker $b) {

        $args = $cmd->getArgs(array('time', 'value', 'field'));

        $this->update($args['field'], $args['value'], $args['time'], $cmd->getSource());
    }

    public function update($field, $value, $time=false, $source='') {
        if($time === false) {
            $time = time();
        }

        if(!array_key_exists($field, $this->data)) {
            $this->data[$field] = array();
        }

        $data = &$this->data[$field];

        $data[] = array(
            'time'=>$time,
            'source'=>$source,
            'value'=>$value
        );

        // Clean up expired values
        foreach($data as $i=>$record) {
            if($record['time'] < time() - $this->valuetimelimit) {
                unset($data[$i]);
            }
        }

        // Copy the update to supercontexts
        foreach($this->supers as $s) {
            $scmd = $cmd->copyTo($s);
            $b->send($scmd);
        }
    }

}
