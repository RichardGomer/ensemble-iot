<?php


/**
 * Client for contacting remote ensemble nodes
 *
 * Currently implements HTTP only; could be extended to other protocols
 */

namespace Ensemble\Remote;

use Ensemble\RequestService;
use GuzzleHttp as http;
use GuzzleHttp\Client as GuzzleHttpClient;

class ClientFactory {
    /**
     * Get a client of an appropriate type for the given endpoint
     * @throws InvalidEndpointException if no suitable client library is available
     */
    public static function factory($endpoint) {
        switch (true) {
            case preg_match('@^https?://@i', $endpoint):
                return new HttpClient($endpoint);
            case preg_match('@^json:.*@', $endpoint):
                return new SharedQueueClient($endpoint);
            default:
                throw new InvalidEndpointException("Endpoint '$endpoint' is invalid - unknown protocol");
        }
    }
}

interface RemoteClient {
    /**
     * Send a command through the endpoint
     *
     * @throws RequestException
     */
    public function sendCommand(\Ensemble\Command $c);

    /**
     * Register device names and their corresponding endpoint
     *
     * @throws RequestException
     */
    public function registerDevices($deviceNames, $endpoint);
}

/**
 * This client sends commands to other local ensemble instances by writing the commands straight into
 * the other instance's incoming JSON command queue, like the HTTP API would
 * 
 * @package Ensemble\Remote
 */
class SharedQueueClient implements RemoteClient {

    private $queue;

    public function __construct($endpoint) {
        /**
         * Endpoint string format is:
         *        json:some/queue/name;arg1=foo,arg2=bar
         * but no args are implemented yet :)
         */
        if(!preg_match('/^json:(.+)(;.*)?$/', $endpoint, $matches)) {
            throw new \Exception("Endpoint '$endpoint' isn't accepted by SharedQueueClient - check the syntax?");
        }

        $fn = $matches[1];

        $this->queue = new \Ensemble\JSONQueue($fn);
    }

    public function sendCommand($c) {
        // Sending commands is easy; literally just put them into the queue!
        $this->queue->push($c);
    }

    public function registerDevices($names, $endpoint) {
        // We send a registration as a special command
        // The receiver for this command is defined in RemoteDeliveryDevice, which has a hardcoded name
        $c = \Ensemble\Command::createOrphan("_RemoteDelivery", "registerDevices", array('devices'=>$names, 'endpoint'=>$endpoint));
        $this->sendCommand($c);
    }
}

/**
 * This client sends commands to other instances by making HTTP requests to their public API
 * @package Ensemble\Remote
 */
class HttpClient implements RemoteClient {

    protected http\Client $client;
    protected string $endpoint;

    public function __construct($endpoint) {
            $this->client = new http\Client();
            $this->endpoint = $endpoint;
    }

    // TODO: Check that the remote API reports success

    public function sendCommand(\Ensemble\Command $c) {
        return $this->makeRequest(array(
            'command'=>$c->toJSON()
        ));
    }

    public function registerDevices($deviceNames, $endpoint) {
        $args = array(
            'register'=>json_encode($deviceNames),
            'endpoint'=>$endpoint
        );

        /*return $this->makeRequest($args);*/

        // Use the request service to make this request, because it's potentially blocky
        // and we don't care about the result
        $rs = RequestService::getInstance();
        $rs->request('POST', $this->endpoint, $args);
    }

    protected function makeRequest($args) {
        $res = $this->client->request('POST', $this->endpoint, array('form_params'=>$args, 'connect_timeout' => 2, 'timeout' => 5));
        if(($s = $res->getStatusCode()) != '200') {
            throw new RequestException("HTTP Request returned status $s");
        }

        $body = json_decode($res->getBody(), true);
        if($body === false) {
            throw new RequestException("Couldn't parse response");
        }

        if(!$body['success']) {
            throw new RequestException("Remote device reported an error: ".$body['message']);
        }

        return $res;
    }

}

class RequestException extends \Exception {}
class InvalidEndpointException extends \Exception {}
