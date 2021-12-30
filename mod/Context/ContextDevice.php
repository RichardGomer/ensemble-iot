<?php

/**
 * A context device maintains context data for access by other devices
 */
namespace Ensemble\Device;

// Need to be able to pass received messages up to a parent context; so we can
// have local contexts for locally-coupled devices and also remote contexts

class ContextDevice extends BasicDevice {

    protected $valuetimelimit = 3600 * 2; // Sets the default expiry time for values

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
        $args['time'] = $cmd->getArgOrValue('time', false);

        if(array_key_exists($args['field'], $this->data)) {
            $data = $this->get($args['field'], $args['num'], $args['time']);
            $reply = $cmd->reply(array('values'=>$data));
        } else {
            $reply = $cmd->reply(new \Exception("Field {$args['field']} isn't set"));
        }

        $b->send($reply);
    }

    public function get($field, $num=1, $time=false) {
        if(!array_key_exists($field, $this->data)) {
            return array();
        }

        $all = $this->data[$field];

        // Remove expired values
        foreach($all as $k=>$v) {
            if($v['expires'] <= time()) {
                unset($all[$k]);
            }
        }

        // Remove values older than $time
        if($time !== false) {
            foreach($all as $k=>$v) {
                if($v['time'] < $time) {
                    unset($all[$k]);
                }
            }
        }

        // Filter by priority, so that only entries with the greatest priority
        // are returned (lower numbers = higher priority)
        $minp = INF;
        foreach($all as $k=>$v) {
            $minp = min($minp, $v['priority']);
        }

        foreach($all as $k=>$v) {
            if($v['priority'] > $minp) {
                unset($all[$k]);
            }
        }

        // Now sort by time and return the requested number of results
        usort($all, function($a, $b) {
            return $a['time'] - $b['time'];
        });

        return array_slice($all, count($all) - $num, $num);
    }

    public function getAll($n=1, $time=false) {
        $out = array();
        foreach($this->data as $field=>$vals) {
            $out[$field] = $this->get($field, $n, $time);
        }
        return $out;
    }

    /**
     * Update a field
     */
    public function action_update(\Ensemble\Command $cmd, \Ensemble\CommandBroker $b) {

        $series = $cmd->getArgOrValue('series', false);

        $priority = $cmd->getArgOrValue('priority', 100); // Get a priority for the value; default to 100
        $expires = $cmd->getArgOrValue('expires', false); // Expiry time of the value

        if(!$series) { // Not a series, set a single value
            $args = $cmd->getArgs(array('time', 'value', 'field'));
            $this->update($args['field'], $args['value'], $priority, $args['time'], $cmd->getSource(), $expires);
        } else {
            $args = $cmd->getArgs(array('field', 'series'));
            foreach($series as $time=>$value) {
                $this->update($args['field'], $value, $priority, $time, $cmd->getSource(), $expires);
            }
        }

        // Copy the update to supercontexts
        foreach($this->supers as $s) {
            $scmd = $cmd->copyTo($s);
            $b->send($scmd);
        }
    }

    public function update($field, $value, $priority, $time=false, $source='', $expires=false) {
        if($time === false) {
            $time = time();
        }

        if($expires === false) {
            $expires = time() + $this->valuetimelimit;
        }

        if($expires < time()) {
            return;
        }

        if(!array_key_exists($field, $this->data)) {
            $this->data[$field] = array();
        }

        $data = &$this->data[$field];

        // Clean up expired values and values for the same timestamp
        foreach($data as $i=>$record) {
            if(($record['expires'] <= time()) || ($record['time'] == $time && $record['priority'] == $priority)) {
                unset($data[$i]);
            }
        }

        // Add the new record
        $data[] = array(
            'time'=>$time,
            'expires'=>$expires,
            'source'=>$source,
            'value'=>$value,
            'priority'=>$priority
        );

        //var_dump($data);

    }

    public function action_clearPriority(\Ensemble\Command $cmd, \Ensemble\CommandBroker $b) {
        $minp = $cmd->getArgOrValue('priority', 100);
        $field = $cmd->getArg('field');

        $this->clearPriority($field, $minp);

        // Copy the update to supercontexts
        foreach($this->supers as $s) {
            $scmd = $cmd->copyTo($s);
            $b->send($scmd);
        }
    }

    /**
     * Clear out values for the named field with a priority strictly less (i.e. higher) than $minPriority
     * Use to, for example, remove overrides that have been set manually
     */
    public function clearPriority($field, $minPriority=100) {
        if(!array_key_exists($field, $this->data)) {
            throw new \Exception("Field '$field' is not set so cannot be priority-cleared");
        }

        $data = &$this->data[$field];

        // Clean up expired values and values for the same timestamp
        foreach($data as $i=>$record) {
            if(($record['priority'] <= $minPriority)) {
                unset($data[$i]);
            }
        }
    }

}
