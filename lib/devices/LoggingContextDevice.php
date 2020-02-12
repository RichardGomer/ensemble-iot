<?php

/**
 * Extend the basic context device by logging all updates to a database using
 * a PDO statement
 */
namespace Ensemble\Device;

class LoggingContextDevice extends ContextDevice {

    private $pdo;

    public function __construct($name, \PDOStatement $insertStatement) {
        parent::__construct($name);

        $this->logger = $insertStatement;
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

        $this->logger->bindValue(':source', $src = $cmd->getSource());
        $this->logger->bindValue(':field', $f = $cmd->getArg('field'));
        $this->logger->bindValue(':value', $v = $cmd->getArg('value'));
        $this->logger->bindValue(':time', $t = $cmd->getArg('time'));

        $this->logger->execute();
    }

}
