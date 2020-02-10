<?php


/**
 * Client for contacting remote ensemble nodes
 *
 * Currently implements HTTP only; could be extended to other protocols
 */

namespace Ensemble\Remote;

use GuzzleHttp as http;

class ClientFactory {
    /**
     * Get a client of an appropriate type for the given endpoint
     * @throws InvalidEndpointException if no suitable client library is available
     */
    public static function factory($endpoint) {
        switch (true) {
            case preg_match('@^https?://@i', $endpoint):
                return new HttpClient($endpoint);
            default:
                throw new InvalidEndpointException("Endpoint '$endpoint' is invalid - unknown protocol");
        }
    }
}

interface RemoteClient {
    /**
     * @throws RequestException
     */
    public function sendCommand(\Ensemble\Command $c);

    /**
     * @throws RequestException
     */
    public function registerDevices($deviceNames, $endpoint);
}

class HttpClient implements RemoteClient {
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
        return $this->makeRequest(array(
            'register'=>json_encode($deviceNames),
            'endpoint'=>$endpoint
        ));
    }

    protected function makeRequest($args) {
        $res = $this->client->request('POST', $this->endpoint, array('query'=>$args));
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
