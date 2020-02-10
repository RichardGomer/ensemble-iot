<?php

/**
 * Extend the basic context device by logging all updates to a database
 */
namespace Ensemble\Device;

// Need to be able to pass received messages up to a parent context; so we can
// have local contexts for locally-coupled devices and also remote contexts

class LoggingContextDevice extends ContextDevice {

    private $pdo;

    public function __construct($name, \PDO $insertStatement) {
        parent::__construct($name);


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
    public function action_update(\Ensemble\Command $cmd, \Ensemble\CommandBroker $b) {
        parent::action_update($cmd, $b);

        $this->pdo->bindParam($cmd['field'], ':field');
        $this->pdo->bindParam($cmd['value'], ':field');
    }

}
