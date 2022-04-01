<?php

namespace Ensemble;

ini_set('memory_limit', '256M');

require dirname(__DIR__).'/lib/setup.php';

// Load device config, if any, based on IP address
$configured = false;
if(!$args['config']) {
    do { // Keep trying until IP auto-config works; this allows time for the network interface to come up
        $ips = getIPs();
        foreach($ips as $ip) {
            if(file_exists($cfn = dirname(__DIR__)."/config/{$ip}.php")) {
                echo "Loaded config {$cfn} based on IP address\n";
                include($cfn);
                $configured = true;
                break;
            }
        }

        if(!$configured) {
            echo "WARNING: No IP-based configuration was found for ".implode(" or ", $ips).". Will try again momentarily.\n";
            sleep(10);
        }
    } while(!$configured);
} else {
    $fn = $args['config'];
    if(file_exists($cfn = dirname(__DIR__)."/config/{$fn}.php")) {
        echo "Loaded specified configuration, {$cfn}\n";
        include($cfn);
    } else {
        echo "Config file '$cfn' does not exist\n";
        exit;
    }
}

/**
 * Allow multiple brokers to be started; each in a thread.
 *
 * If $conf[] is just an integer-indexed array, then treat each key as a
 * separate config array. For each one:
 * Set a config number; spawn a new thread; have the child thread kick off the
 * broker using the configured parameters.
 * The main thread will just wait until the others are done.
 * Each thread will have its own remote delivery device etc.
 *
 * We use the main thread as a router; and have the child threads do all routing
 * through it (i.e they have only a default endpoint, receive no announcements).
 * We can route to them because we receive their announcements.
 *
 * Remote devices CANNOT route to threads; so at the moment the main thread needs
 * to be a default route in order for commands to reach subthreads from outside.
 * TODO: Fix that ^^ - make HTTP endpoint aware of subthreads?
 */

// Record runtime status information
$status = new Storage\JsonStore('devicestatus');
$status->proc = getmypid();
$status->started = time();
$status->ips = $ips = System\IP::getIPs();


/**
 * Start a single broker using a configuration array, $conf
 *
 *
 */
function startBroker($conf) {

  /**
   * For the main thread, these are "real"; for subthreads they need to be
   * configured as JsonQueue instances:
   *     inputQueue: Should be a single queue for each thread
   *     remoteQueue: Should be the main thread's input queue
   */
  $inputQueue = $conf['queue_in'];
  $remoteQueue = $conf['queue_remote'];

  /**
   * For the main thread, this is a real devicemap; for subthreads it will be
   * blank so that all commands go via the default endpoint (main thread)
   */
  $deviceMap = $conf['device_map'];

  $broker = new CommandBroker();

  $broker->setRemoteQueue($remoteQueue);
  $broker->setInputQueue($inputQueue);

  // The announcement device tells other endpoints about our local devices
  // so that they can route commands to them
  $announce = new Device\AnnouncerDevice($conf['endpoint_url'], array(), $deviceMap);
  $broker->addDevice($announce);

  // If there's a default endpoint configured, we'll announce to it
  if($conf['default_endpoint'] !== false) {
      $announce->addRemote($conf['default_endpoint']);
  }

  // ** DISABLE DIRECT LOCAL DELIVERY **
  // Direct local delivery can be disabled, in which case the message broker
  // will push all messages via the remote queue. We need to announce to ourself
  // so that those messages can be routed into our own endpoint.
  if($conf['disable-direct-local']) {
      $broker->disableDirectLocalDelivery();
      $announce->addRemote($conf['endpoint_url']);
  }

  // A remote delivery device is a special device that delivers outgoing commands
  // to remote endpoints
  $remote = new Device\RemoteDeliveryDevice($remoteQueue, $deviceMap);
  $remote->setDefaultEndpoint($conf['default_endpoint']);
  $broker->addDevice($remote); // Add the remote delivery device

  // Register defined devices
  foreach($conf['devices'] as $d) {
      $dn = $d->getDeviceName();
      echo "Added device $dn\n";
      $broker->addDevice($d);
  }

  $broker->run();
}

function isAssoc(array $arr)
{
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

if(!isAssoc($conf)) { // Start multiple threads using the configurations

    echo "There are multiple threads defined. Will use child brokers.\n";

    $masterConfig = $conf[0];

    foreach($conf as $num => $subconf) {

        if($num == 0) { // Skip config 0, we'll run it in the master thread
            continue;
        }

        $cpid = \pcntl_fork(); // Fork
        $subthread = $cpid == 0; //... and determine if we're the child thread
        if($subthread) { // If so, start the thread
            // Tidy up the config; e.g. wire up the input/output queues
            echo "Start thread $num...\n";
            $c = &$conf[$num];
            $c['default_endpoint'] = "json:".($oq = "q_in.json"); // Commands all go via main thread
            $c['endpoint_url'] = "json:".($iq = "q_thread{$num}.json"); // Our endpoint is a JSON queue
            $c['queue_in'] = new JsonQueue($iq); // Create our input queue
            $c['queue_remote'] = new JsonQueue($oq); // Connect the remote queue
            $c['disable-direct-local'] = false;
            $c['device_map'] = new Remote\DeviceMap(new Storage\JsonStore("device_map_t{$num}.json"));

            startBroker($conf[$num]);
            echo "Thread $num has ended\n";
            exit;
        }
    }

    echo "Start master thread\n";
    startBroker($masterConfig);

} else { // Start a single broker thread
    echo "There are no threads defined. Will use a single broker.\n";
    startBroker($conf);
}
