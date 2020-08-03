<?php

namespace Ensemble\Schedule;

class OctopusTariff {

    public function __construct($url) {
        $this->url = $url;
    }

    // Get the Agile tariff data from Octopus as a Schedule object
    // The schedule will contain all available tariff data - usually 48 hours
    public function getTariffSchedule() {
        $this->client = new http\Client();

        $res = $this->client->request('GET', $this->url, array());
        if(($s = $res->getStatusCode()) != '200') {
            throw new RequestException("HTTP Request returned status $s");
        }

        $body = json_decode($res->getBody(), true);
        if($body === false) {
            throw new RequestException("Couldn't parse response");
        }

        $s = new Schedule();

        foreach($body['results'] as $segment) {
            $s->setPeriod($segment['valid_from'], $segment['valid_to'], $segment['value_inc_vat']);
        }

        return $s;
}




class RequestException extends \Exception {}
