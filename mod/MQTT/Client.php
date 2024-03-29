<?php

/**
 * An MQTT client that wraps the mosquitto command line tools
 * Main benefit is that the PHP thread can do other things in between
 * processing MQTT messages, but the client is still listening
 */

namespace Ensemble\MQTT;

use Ensemble\System\Thread;

class Client {

    private $host, $port;

    public function __construct($host, $port) {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Create a subscription. A new background process is spawned, and an MQTTSubscription
     * is returned.
     */
    public function subscribe($topic) {
        return new MQTTSubscription($this->host, $this->port, $topic);
    }

    /**
     * Publish a message to a topic
     */
    public function publish($topic, $message, $retain=false) {
        $args = array(
            'h' => $this->host,
            'p' => $this->port,
            't' => $topic,
            'm' => $message,
            'q' => 1
        );

        if($retain) {
            $args['r'] = null;
        }

        $t = new Thread("mosquitto_pub", $args);

        $t->waitForExit();
    }
}

class MQTTSubscription {
    private $host, $port, $topic;
    public function __construct($host, $port, $topic) {

        $this->host = $host;
        $this->port = $port;
        $this->topic = $topic;

        $this->start();
    }

    private $thread;
    protected function start() {
        usleep(5000);
        $this->thread = new Thread("stdbuf -i0 -o0 -e0 mosquitto_sub -R ", array(
            'h' => $this->host,
            'p' => $this->port,
            'F' => '%j',
            't' => $this->topic
        ));
    }

    public function getMessages() {
        if(!$this->thread->isRunning()) {
            $c = $this->thread->getExitCode();
            echo "MQTT thread for '{$this->topic}'@{$this->host}:{$this->port} has stopped with code $c.\nSTDERR:\n";
            echo "   ".implode("\n   ", $this->thread->error());
            echo "\nSTDOUT:\n";
            $this->thread->read();
            echo "   ".implode("\n   ", $this->thread->getBuffer());
            $this->start();
        }

        $lines = $this->thread->read();

        $messages = array();
        foreach($lines as $l) {
            // There's a bug in mosquitto_sub that encodes quotation marks in the payload as \u0034 instead of \u0022?!
            $l = str_replace('\u0034', '\u0022', $l);
            $l = json_decode($l, true);

            $messages[] = array('topic'=>$l['topic'], 'message'=>$l['payload']);
        }

        return $messages;
    }

    public function close() {
        $this->thread->close();
    }
}
