<?php

namespace Ensemble;

/**
 * A CommandBroker looks after a set of devices and does local routing of commands
 * it receives commands via an incoming command queue, and dispatches commands
 * for remote devices via a remote delivery queue
 */
class CommandBroker {

    private mixed $devices = [];
    private bool $newdevices = false;
    private mixed $polls = [];

    private Queue $input;
    private Queue $remote;

    public function addDevice(Module $device) {

        foreach($this->devices as $d) {
            if($d === $device) { // Skip the device if it has already been added
                return;
            }
            
            if($d->getDeviceName() == $device->getDeviceName()) {
                throw new DuplicateDeviceNameException("Device name '{$d->getDeviceName()}' is already in use locally");
            }
        }

        $this->devices[] = $device;
        $this->newdevices = true; // Flag that there are new devices for the next step

        // Set up initial polling
        $name = $device->getDeviceName();
        $ptime = $device->getPollInterval();
        if($ptime > 0) {
            $this->polls[$name] = rand(0, count($this->getPollingDue())) * 1 + time(); // Stagger first poll
        }

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
                unset($this->polls[$device->getDeviceName()]); // Remove scheduled polls
            }
        }
    }

    public function setInputQueue(Queue $queue) {
        $this->input = $queue;
    }

    public function setRemoteQueue(Queue $queue) {
        $this->remote = $queue;
    }

    /**
     * Disable direct local delivery, i.e. send all commands via a network endpoint.
     * This is mostly useful for testing the endpoints using a single broker talking
     * to itself.
     * @var false
     */
    private $disabledirectlocal = false;
    public function disableDirectLocalDelivery() {
        $this->disabledirectlocal = true;
    }

    /**
     * Send a command, either by pushing it into the local queue or sending it
     * remotely.
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
    protected function getTargetDevice(Command $cmd) : Module {
        return $this->getDevice($cmd->getTarget());
    }

    public function getDevice($name) : Module {
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
            if(substr($name, 0, 1) !== '_') { // Poll special devices quietly!
                echo date('[Y-m-d H:i:s] ')."POLL $name\n";
            }

            $this->getDevice($name)->poll($this);
        } catch(\Exception $e) {
            echo date('[Y-m-d H:i:s] ')."Exception during device poll:\n  ".get_class($e)." ".$e->getMessage()."\n";
            echo $e->getTraceAsString()."\n";
        }
    }

    /**
     * Bootstrap the main loop, if required
     */
    private $bootstrapped = false;
    protected function bootstrap() {
        if($this->bootstrapped)
            return;

        $this->bootstrapped = true;

        $this->addDevice(new StatusReportDevice($this->input));
    }

    /**
     * Get devices that are due to be polled
     */
    protected function getPollingDue() : mixed {
        $out = [];
        $now = microtime(true);
        foreach($this->polls as $name=>$time) {
            if($time <= $now) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * Check for devices that are due to be polled, poll them, then handle some messages
     * i.e. step once through the main loop
     * 
     * Module polling:
     *    Poll modules based on their declared poll interval
     *
     * Incoming commands:
     *    Take each received command and route it to the destination device
     * 
     */
    private $lastpoll = 0;
    public function step() {
        $this->bootstrap();

        $now = microtime(true);

        // If there are new devices, announce them immediately
        if($this->newdevices) {
            $this->getDevice('_Announcer')->poll($this);
            $this->newdevices = false;
        }
        
        // Check poll timers every 1 seconds for polling due
        if($now - $this->lastpoll >= 1) {
            $this->lastpoll = $now;

            array_map(function($name) {
                $this->poll($name);
                $this->polls[$name] = time() + $this->getDevice($name)->getPollInterval();
            }, $this->getPollingDue());
        }

        if(!$this->input->isEmpty()) {
            /**
             * We don't want to block forever consuming commands, because some devices won't actually
             * consume them until the next poll; but we do want to make decent headway each time. This loop
             * will consume up to 100 commands, before checking whether polling is due
             */
            $n = 0;
            do {
                $next = $this->input->shift();
                if($next !== null)
                    $this->handle($next);
                $n++;
            } while(!$this->input->isEmpty() && $n < 100);
        }
    }

    /**
     * Run the broker indefinitely; this is effectively a main() loop
     */
    public function run() {

        $lastpoll = 0;
        while(true) {
            $this->step();
    
            if($this->input->isEmpty()) {
                usleep(1000);
            }
        }
    }

}

class DuplicateDeviceNameException extends \Exception {}
class DeviceNotFoundException extends \Exception {}
class DeviceBusyException extends \Exception {}
class ExpiredException extends \Exception {}

class StatusReportDevice extends Device\BasicDevice {
    private JsonQueue $in;

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
