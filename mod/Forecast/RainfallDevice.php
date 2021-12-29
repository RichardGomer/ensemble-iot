<?php

/**
* Rainfall Device collects rainfall data from the environment agency and sends
* it to a context device
*/

namespace Ensemble\Device\Forecast;
use Ensemble\Async;
use GuzzleHttp\Client;

class RainfallDevice extends Async\Device {

    /**
     * $devicename - A name for this device
     * $easite - A rain gauge site number
     */
    public function __construct($devicename, $station, $contextdevice, $field) {
        $this->name = $devicename;

        $this->contextdevice = $contextdevice;
        $this->station = $station;
        $this->field = $field;

        $this->interval = 3600 * 1;

        $this->client = $client = new Client([
            'timeout' => 15.0
        ]);
    }

    protected function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {
            $start = time();
            try {
                $url = "https://environment.data.gov.uk/flood-monitoring/id/stations/{$this->station}/readings.json?_limit=96&_sorted&parameter=rainfall";
                echo "Rainfall GET $url\n";
                $res = $this->client->request('GET', $url);
                $json = $res->getBody();
                $log = json_decode($json, true);

                $obs = json_decode($res->getBody(), true);
                $values = array();
            	foreach($obs['items'] as $i) {
            		$time = strtotime($i['dateTime']);
            		$values[$time] = $i['value'];
            	}

                $broker = $device->getBroker();

                $cmd = \Ensemble\Command::create($device, $device->contextdevice, 'updateContext');
                $cmd->setArg('field', $device->field);
                $cmd->setArg('series', $values);
                $this->getBroker()->send($cmd);

                // Wait until the next reschedule is due
                yield new Async\waitUntil($start + $this->interval);

            } catch (Exception $e) {
                echo "Rainfall failed: ".$e->getMessage()."\n";
            }
        });
    }

}
