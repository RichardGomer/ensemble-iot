<?php

// Mostly for testing

namespace Ensemble;

// Logging device
$conf['devices'][] = new Device\LoggerDevice('global.log', new Log\TextLog(_VAR.'global.log'));

$dbf = dirname(__DIR__).'/var/context.sqlite3';

$db = new \PDO('sqlite:'.$dbf);
$db->query("CREATE TABLE IF NOT EXISTS `context` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
	`source` TEXT,
	`field` TEXT,
	`value`,
	`time` INTEGER
); ");

$st = $db->prepare("INSERT INTO context(`source`, `field`, `value`, `time`) VALUES (:source, :field, :value, :time)");

$conf['devices'][] = new Device\LoggingContextDevice('global.context', $st);

// Create a test device that just generates log messages
class LogGenerator implements Module {
    public function action(Command $c, CommandBroker $b) {

    }

    public function isBusy() {
        return false;
    }

    public function getPollInterval() {
        return 5;
    }

    public function announce() {
        return false;
    }

    private $n = 0;
    public function poll(CommandBroker $b) {
        $b->send(Command::create($this, 'global.log', 'log', array('message'=>"Test log message ".($this->n++))));
        $b->send(Command::create($this, 'global.context', 'updateContext', array('time'=>time(), 'field'=>'randField', 'value'=>rand(1,99999))));
    }

    public function getDeviceName() {
        return 'test.LogGenerator';
    }
}

$conf['devices'][] = new LogGenerator();
