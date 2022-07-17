<?php

namespace Ensemble\Actions;

use Ensemble\Async as Async;

class Documentation {

    public function arg($name, $optional, $desc) {
        $this->args[$name] = ["optional" => (bool) $optional, "description" => $desc];

        return $this;
    }

    public function notes($notes) {
        $this->notes = $notes;

        return $this;
    }

    public function getDocs() {
        return [ "args" => $this->args , "notes" => $this->notes  ];
    }

}
