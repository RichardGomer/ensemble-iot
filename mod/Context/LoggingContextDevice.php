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
    public function __construct($name, $connstr, $dbuser, $dbpass) {
        parent::__construct($name);

        $this->connstr = $connstr;
        $this->dbuser = $dbuser;
        $this->dbpass = $dbpass;

        $this->connect();
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
            $tries = 0;
            $done = false;
            do {
                $tries++;
                $s->bindValue(':source', $source);
                $s->bindValue(':field', $field);
                $s->bindValue(':value', $value);
                $s->bindValue(':time', $time);

                $res = $s->execute();

                if(!$res) { // On failure, try reconnecting up to 3 times
                    if($tries > 3) {
                        $err = $s->errorInfo();
                        echo "Persistent SQL error: [{$err[0]}]: {$err[2]}\n";
                        throw new \Exception("SQL Error [{$err[0]}]: {$err[2]}");
                    } else {
                        sleep(1);
                        $this->connect();
                    }
                } else {
                    $done = true;
                }
            } while(!$done);
        }

    }

    protected function connect() {
        $this->db = new \PDO($this->connstr, $this->dbuser, $this->dbpass);

        $st = array();
        $st[] = $this->db->prepare("DELETE FROM context WHERE `source`=:source AND `field`=:field AND `time`=:time AND (ISNULL(:value) OR NOT ISNULL(:value))");
        $st[] = $this->db->prepare("INSERT INTO context(`source`, `field`, `value`, `time`) VALUES (:source, :field, :value, :time)");

        $this->statements = $st;
    }
}
