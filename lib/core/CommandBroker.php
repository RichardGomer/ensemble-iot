<?php

namespace Ensemble;

/**
 * A CommandBroker looks after a set of devices and does local routing of commands
 * it receives commands via an incoming command queue, and dispatches commands
 * for remote devices via a remote delivery queue
 */
class CommandBroker {

    public function addDevice(Module $device) {
        $this->devices[] = $device;

        // Add any child devices
        $cds = $device->getChildDevices();
        if(is_array($cds)) {
            foreach($cds as $cd) {
                if($cd instanceof Module) {
                    echo "Add child device ".$cd->getDeviceName()."\n";
                    $this->addDevice($cd);
                }
            }
        }
    }

    public function removeDevice(Module $device) {
        foreach($this->devices as $k=>$d) {
            if($d === $device) {
                unset($this->devices[$k]); // Remove the device from the list
                unset($this->polls[$device->getName()]); // Remove scheduled polls
            }
        }
    }

    public function setInputQueue(Queue $queue) {
        $this->input = $queue;
    }

    public function setRemoteQueue(Queue $queue) {
        $this->remote = $queue;
    }

    private $disabledirectlocal = false;
    public function disableDirectLocalDelivery() {
        $this->disabledirectlocal = true;
    }

    /**
     * Send a command, either by pushing it into the local queue or sending it
     * remotely
     */
    public function send(Command $command) {
        try {
            $device = $this->getTargetDevice($command);
            $local = true;
        } catch (DeviceNotFoundException $e) {
            $local = false;
        }

        if($local && !$this->disabledirectlocal) {
            echo date('[Y-m-d H:i:s] ')."TX ".$command." [QUEUE LOCAL]\n";
            $this->input->push($command);
        } else {
            echo date('[Y-m-d H:i:s] ')."TX ".$command." [QUEUE REMOTE]\n";
            $this->remote->push($command);
        }
    }

    /**
     * Handle a command by giving it to a local device or sending it remotely
     * send() is similar to handle(), except that handle() executes local commands
     * immediately, and send() pushes them onto the local queue
     */
    protected function handle(Command $command) {
        try {

            // Don't execute the command if it has expired
            if($command->isExpired()) {
                echo date('[Y-m-d H:i:s] ')."RX ".$command." [EXPIRED]\n";
                throw new ExpiredException("Command expired before action");
            }

            $device = $this->getTargetDevice($command);

            if($device->isBusy()) {
                echo date('[Y-m-d H:i:s] ')."RX ".$command." [QUEUE LOCAL, DEVICE BUSY]\n";
                throw new DeviceBusyException("Device is busy");
            }

            echo date('[Y-m-d H:i:s] ')."RX ".$command." [EXECUTE]\n";
            $device->action($command, $this);
        } catch(DeviceBusyException $e) { // If the device is busy, return the command to the queue with a threshold
            $this->input->push($command, time() + 60);
        } catch(DeviceNotFoundException $e) {
            $this->remote->push($command); // Route commands for unknown devices via the remote queue
        } catch(\Exception $e) {
            $t = get_class($e);
            echo date('[Y-m-d H:i:s] ')."Exception during execution: [{$t}] {$e->getMessage()}\n";
            $this->send($command->reply($e)); // If there's an exception, reply with it
        }

    }

    /**
     * Check for a local device that matches the given command's target
     */
    protected function getTargetDevice(Command $cmd) {
        return $this->getDevice($cmd->getTarget());
    }

    protected function getDevice($name) {
        $name = (String) $name;
        foreach($this->devices as $d) {
            if($d->getDeviceName() === $name) {
                return $d;
            }
        }

        throw new DeviceNotFoundException("Device $name was not matched locally");
    }

    // Get a list of local device names
    public function getLocalDevices() {
        $out = array();
        foreach($this->devices as $d) {
            $out[] = $d->getDeviceName();
        }
        return $out;
    }

    protected function poll($name) {
        try {
            echo date('[Y-m-d H:i:s] ')."POLL $name\n";
            $this->getDevice($name)->poll($this);
        } catch(\Exception $e) {
            echo date('[Y-m-d H:i:s] ')."Exception during device poll:\n  ".get_class($e)." ".$e->getMessage()."\n";
        }
    }

    /**
     * Run the broker; this is effectively a main() loop
     *
     * Module polling:
     *    Poll modules based on their declared poll interval
     *
     * Incoming commands:
     *    Take each received command and route it to the destination device
     */
    public function run() {

        $this->addDevice(new StatusReportDevice($this->input));

        // Set up initial poll times for each module
        // Poll times are staggered to try and avoid lumpy performance
        $polls = array();
        $n = 0;
        foreach($this->devices as $k=>$d) {
            $name = $d->getDeviceName();
            $ptime = $d->getPollInterval();
            if($ptime > 0) {
                $polls[$name] = $n * 1 + time(); // Stagger offset + time now
                $n++;
                // We don't use the poll interval to begin with, because all devices get polled at startup
            }
        }

        $lastpoll = 0;
        while(true) {

            // Check poll timers every 1 second for polling due
            if(microtime(true) - $lastpoll >= 1) {
                $now = microtime(true);
                $lastpoll = $now;

                foreach($polls as $name=>$time) {
                    if($time <= $now) {
                        $this->poll($name);
                        $polls[$name] = time() + $this->getDevice($name)->getPollInterval();
                    }
                }
            }

            if($this->input->isEmpty()) {
                usleep(100000);
                continue;
            }
            else {
                /**
                 * We don't want to block forever consuming commands, because some devices won't actually
                 * consume them until the next poll; but we do want to make decent headway each time. This loop
                 * will consume up to 100 commands, before checking whether polling is due
                 */
                $n = 0;
                do {
                    $next = $this->input->shift();
                    $this->handle($next);
                    $n++;
                } while(!$this->input->isEmpty() && $n < 100);
            }
        }
    }

}

class DeviceNotFoundException extends \Exception {}
class DeviceBusyException extends \Exception {}
class ExpiredException extends \Exception {}

class StatusReportDevice extends Device\BasicDevice {
    public function __construct(JsonQueue $inq) {
        $this->name = "_QSTATUS";
        $this->in = $inq;
    }

    public function poll(\Ensemble\CommandBroker $b) {
        echo date('[Y-m-d H:i:s] ')."QUEUE STATUS: {$this->in->count()} due of {$this->in->countAll()} queued commands\n";
    }

    public function announce() {
        return false;
    }

    public function getPollInterval() {
        return 120;
    }
}
