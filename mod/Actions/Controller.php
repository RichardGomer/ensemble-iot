<?php

/**
 * The Actions subsystem exposes arbitrary routines to the outside world. Making it easier to create complex behaviours
 * without lots of boilerplate or new device classes
 */

namespace Ensemble\Actions;

use Ensemble\Async as Async;
use Ensemble\Command as Command;

class Controller extends Async\Device {

    public function __construct($name) {

        $this->name = $name;

        // Expose our own documentation
        $this->expose('docs', array($this, 'getDocs'), (new Documentation())->notes("Get documentation about available actions"));

    }

    /**
     * Expose the given callable as a command on this device, optionally with some documentation
     */
    public function expose($name, callable $c, Documentation $docs = null) {

        $this->exposed[strtolower($name)] = ['name' => $name, 'call' => $c, 'docs' => $docs];

    }


    public function getDocs(Command $c = null) {

        $docs = [ 'name' => $this->name, 'actions' => [] ];

        foreach($this->exposed as $e) {
            $docs['actions'][$e['name']] = $e['docs'] == null ? false : $e['docs']->getDocs();
        }

        return $docs;
    }

    public function getRoutine() {
        return new Async\Lambda(function(){
            $c = yield new Async\WaitForAnyCommand($this);

            $act = strtolower($c->getAction());

            $this->log("Action Controller received command {$c->getAction()}");

            if(array_key_exists($act, $this->exposed)) {
                $res = call_user_func($this->exposed[$act]['call'], $c);
                $c->reply($res);
            } else {
                echo "*** No such action!\n";
                $c->reply(new \Exception("No such action as {$c->getAction()}"));
            }
        });
    }

}
