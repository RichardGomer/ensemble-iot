<?php

namespace Ensemble\Schedule;
use Ensemble\Async;
use GuzzleHttp\Client;

/**
 * A client for the Octopus energy API
 *
 */
class Octopus {

    public function __construct($key) {
        $this->url = 'https://api.octopus.energy';
        $this->key = $key;
        $this->client = new Client();
    }

    // Format unix timestamp as ISO9601 for API
    protected function dateFormat($time) {
        return date(DateTime::ISO8601, $time);
    }

    protected function request($path) {

        if(!preg_match('@^/@', $path)) { // Ensure path has a leading slash
            $path = '/'.$path;
        }

        $res = $this->client->request('GET', $this->url.$path, ['auth' => [$this->key,'']]);

        if(($s = $res->getStatusCode()) != '200') {
            throw new RequestException("HTTP Request returned status $s");
        }

        $body = json_decode($res->getBody(), true);
        if($body === false) {
            throw new RequestException("Couldn't parse response");
        }

        return $body;
    }

    /**
     * Get the Agile tariff data from Octopus as a Schedule object
     * The schedule will contain all available tariff data - usually 24 or 48 hours
     */
    public function getTariffSchedule() {

        $path = "/v1/products/{$this->productcode}/electricity-tariffs/$this->tariffcode/standard-unit-rates";
        $res = $this->request($path);

        $s = new Schedule();

        foreach($res['results'] as $segment) {
            $s->setPeriod($segment['valid_from'], $segment['valid_to'], $segment['value_inc_vat']);
        }

        echo $s->prettyPrint();

        return $s;
    }

    public function setTariff($productcode, $tariffcode) {
        $this->productcode = $productcode;
        $this->tariffcode = $tariffcode;
    }

    // Get consumption data as a Schedule
    protected function getUsage($type, $mpn, $serial) {
        $utype = $type == 'gas' ? 'gas-meter-points' : 'electricity-meter-points';
        $path = "/v1/{$utype}/{$mpn}/meters/$serial/consumption/";
        $res = $this->request($path);

        $s = new Schedule();

        foreach($res['results'] as $segment) {
            $s->setPeriod($segment['interval_start'], $segment['interval_end'], $segment['consumption']);
        }

        return $s;
    }

    public function setGasMeter($mpn, $serial) {
        $this->gas_mpn = $mpn;
        $this->gas_serial = $serial;
    }

    public function setElecMeter($mpn, $serial) {
        $this->elec_mpn = $mpn;
        $this->elec_serial = $serial;
    }

    public function getGasUsage() {
        return $this->getUsage('gas', $this->gas_mpn, $this->gas_serial);
    }

    public function getElecUsage() {
        return $this->getUsage('elec', $this->elec_mpn, $this->elec_serial);
    }
}

class RequestException extends \Exception {}
