<?php

/**
 * Extend the basic context device by logging all updates to a database using
 * PDO statements
 */
namespace Ensemble\Device;

class LoggingContextDevice extends ContextDevice {

    private $db = false;

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
    public function update($field, $value, $priority, $time=false, $source='', $expires=false, $store=true) {
        parent::update($field, $value, $priority, $time, $source, $expires);

        if($value === "" || $value===false)
            return; // Don't save blank values!

        if($time == false) {
            $time = time();
        }

        if(!$store) {
            return;
        }

        echo "     Store $field = $value @ ".date('Y-m-d H:i:s', $time)."  ";
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
                        echo "[FAIL]\nPersistent SQL error: [{$err[0]}]: {$err[2]}\n";
                        $done = true;
                    } else {
                        echo "[FAIL]\n";
                        sleep(1);
                        $this->connect();
                    }
                } else {
                    echo "[ OK ]\n";
                    $done = true;
                }
            } while(!$done);
        }

    }

    protected function connect() {
        $this->db = new \PDO($this->connstr, $this->dbuser, $this->dbpass);

        $st = array();
        $st[] = $this->db->prepare("DELETE FROM context WHERE `source`=:source AND `field`=:field AND `time`=FROM_UNIXTIME(:time) AND (ISNULL(:value) OR NOT ISNULL(:value))");
        $st[] = $this->db->prepare("INSERT INTO context(`source`, `field`, `value`, `time`) VALUES (:source, :field, :value, FROM_UNIXTIME(:time))");

        $this->statements = $st;
    }

    /**
     * Repopulate context from the database
     *
     * SuperContexts ARE informed of all the "changes" as they're imported (if any are defined)
     * so repopulation can cascade upwards. BUT, that will generate a lot of commands, so call this
     * before adding supers unless that behaviour is intentional
     */
    public function repopulate() {
        $limit = $this->valuetimelimit; // This is the configured expiry time for values

        $st = $this->db->prepare("SELECT `field`,`value`,UNIX_TIMESTAMP(`time`) as `time`,`source` FROM context WHERE `time`>=FROM_UNIXTIME(:time)");
        $st->bindValue(':time', (int) time() - $limit);
        $res = $st->execute();

        if(!$res) {
            $err = $st->errorInfo();
            echo "Context could not be restored. SQL error: [{$err[0]}]: {$err[2]}\n";
            throw new \Exception("SQL Error [{$err[0]}]: {$err[2]}");
        }

        $tot = $st->rowCount(); $n = 0;
        //echo "Found {$tot} context values to restore\n";

        while(($row = $st->fetch()) !== false) {
            $n++;
            //echo "$n / $tot   SET {$row['field']} = {$row['value']}\n";
            $this->update($row['field'], $row['value'], 100, $row['time'], $row['source'], false, false);
        }

    }
}
