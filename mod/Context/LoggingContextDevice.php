<?php

/**
 * Extend the basic context device by logging all updates to a database using
 * PDO statements
 */
namespace Ensemble\Device;

class LoggingContextDevice extends ContextDevice {

    private $pdo;

    /**
     * $statements is an array of PDO statements to execute on each update
     */
    public function __construct($name, $statements) {
        parent::__construct($name);

        $this->statements = $statements;
    }

    /**
     * A supercontext will receive a copy of all our updates
     */
    public function addSuperContext($devicename) {
        $this->supers[] = $devicename;
    }

    /**
     * Update a field
     */
    public function update($field, $value, $time=false, $source='') {
        parent::update($field, $value, $time, $source);

        foreach($this->statements as $s) {
            $s->bindValue(':source', $source);
            $s->bindValue(':field', $field);
            $s->bindValue(':value', $value);
            $s->bindValue(':time', $time);

            $res = $s->execute();

            if(!$res) {
                $err = $s->errorInfo();
                throw new \Exception("SQL Error [{$err[0]}]: {$err[2]}");
            }
        }

    }

}
