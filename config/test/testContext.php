<?php

// Mostly for testing

namespace Ensemble;

use \Ensemble\Async as Async;

//$conf['default_endpoint'] = 'http://10.0.0.8:3107/ensemble-iot/1.0/';

/**
 * global.context is intended as the master context device
 */
if(!file_exists(__DIR__.'/dbcreds.php')) {
        echo "Set \$dbhost, \$dbname, \$dbuser and \$dbpass in config/dbcreds.php\n";
        exit;
}

require 'dbcreds.php';

$db = new \PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);

$conf['devices'][] = new Device\ContextDevice('test.context');


// Create a test device that just generates log messages
class Test extends Async\Device {
    public function __construct() {
        $this->name = "testDevice";
    }

    public function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {
            $b = $device->getBroker();

            $b->send(Command::create($this, 'test.context', 'updateContext', array('time'=>time(), 'field'=>'testfield', 'value'=>"normal priority value")));
            $value = yield new Device\FetchContextRoutine($this, 'test.context', 'testfield');
            echo "Value is '$value'\n";

            echo "Set high priority value\n";
            $b->send(Command::create($this, 'test.context', 'updateContext', array('time'=>time(), 'field'=>'testfield', 'value'=>"high priority value", 'priority'=>50, 'expires'=>time() + 5)));
            $value = yield new Device\FetchContextRoutine($this, 'test.context', 'testfield');
            echo "Value is '$value'\n";

            echo "Wait for expiry...\n";
            sleep(5);

            $value = yield new Device\FetchContextRoutine($this, 'test.context', 'testfield');
            echo "Value is '$value'\n";

            echo "Set low priority value\n";
            $b->send(Command::create($this, 'test.context', 'updateContext', array('time'=>time(), 'field'=>'testfield', 'value'=>"low priority value", 'priority'=>200)));
            $value = yield new Device\FetchContextRoutine($this, 'test.context', 'testfield');
            echo "Value is '$value'\n";

            exit;
        });

    }
}

$conf['devices'][] = new Test();
