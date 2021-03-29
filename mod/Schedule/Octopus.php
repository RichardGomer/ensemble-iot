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

    private $days = 15;
    /**
     * Set the number of days of history to request from the API (tariff and usage data)
     */
    public function setTimeSpan($days) {
        $this->days = (int) $days;
    }

    // Format unix timestamp as ISO9601 for API
    protected function dateFormat($time) {
        return date(DateTime::ISO8601, $time);
    }

    protected function request($path, $params=[]) {

        if(!preg_match('@^/@', $path)) { // Ensure path has a leading slash
            $path = '/'.$path;
        }

        $params['period_from'] = date('Y-m-d\T00:00:00\Z', strtotime("-{$this->days} days"));

        $url = $this->url.$path;
        echo "GET {$url}\n";

        $res = $this->client->request('GET', $url, ['query' => $params, 'auth' => [$this->key,'']]);

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
    public function getTariffSchedule($num=192) {

        $path = "/v1/products/{$this->productcode}/electricity-tariffs/$this->tariffcode/standard-unit-rates";
        $res = $this->request($path, array('page_size'=>$num));

        $s = new Schedule();

        //var_dump($res['results']);

        foreach($res['results'] as $segment) {
            $s->setPeriod($segment['valid_from'], $segment['valid_to'], $segment['value_inc_vat'], false);
        }

        //echo $s->prettyPrint();

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
        $res = $this->request($path, array('page_size'=>25000));

        $s = new Schedule();

        foreach($res['results'] as $segment) {
            $s->setPeriod($segment['interval_start'], $segment['interval_end'], $segment['consumption'], false);
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
