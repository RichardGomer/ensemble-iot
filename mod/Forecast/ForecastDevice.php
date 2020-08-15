<?php

/**
 * Forecast Device collects a weather forecast from the Met Office and sends it
 * to a context device
 */

namespace Ensemble\Device\Forecast;
use Ensemble\Async;
use GuzzleHttp\Client;

class ForecastDevice extends Async\Device {

    /**
     * $devicename - A name for this device
     * $dpkey - A metoffice datapoint API key
     * $dploc - A metoffice datapoint location number
     */
    public function __construct($devicename, $dpkey, $dploc, $contextdevice, $fieldprefix) {
        $this->name = $devicename;

        $this->contextdevice = $contextdevice;
        $this->dpkey = $dpkey;
        $this->dploc = $dploc;
        $this->fieldprefix = $fieldprefix;

        $this->interval = 3600 * 3;

        $this->client = $client = new Client([
            'timeout' => 15.0
        ]);
    }

    protected function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {
            $start = time();
            try {
                $url = "http://datapoint.metoffice.gov.uk/public/data/val/wxfcs/all/json/{$this->dploc}?res=3hourly&key={$this->dpkey}";
                echo "Forecast GET $url\n";
            	$res = $this->client->request('GET', $url);
            	$json = $res->getBody();
            	$log = json_decode($json, true);

            	$periods = $log['SiteRep']['DV']['Location']['Period'];
            	$temps = array();

            	foreach($periods as $day) {
            		$date = $day['value'];

            		// Today's forecast normally contains less than 8 periods, because some are in the past!
            		// We need to compensate for that
            		$firstOffset = (8 - count($day['Rep'])) * 3600 * 3;

            		$tsbase = strtotime($date) + $firstOffset;

            		//echo "$date = $tsbase\n";

            		$time = $tsbase;
            		foreach($day['Rep'] as $step) {
                        echo "@ $time:\n";
                        foreach($step as $f=>$v) {
            			      $fields[$f][$time] = $v;
                              echo "   $f: $v\n";
                        }
            			$time += 3600 * 3;
            		}
            	}

                $broker = $device->getBroker();
                foreach($fields as $f=>$series) {
                    $cmd = \Ensemble\Command::create($device, $device->contextdevice, 'updateContext');
                    $cmd->setArg('field', $device->fieldprefix.$f);
                    $cmd->setArg('series', $series);
                    $this->getBroker()->send($cmd);
                }

                // Wait until the next reschedule is due
                yield new Async\waitUntil($start + $this->interval);

            } catch (Exception $e) {
                    echo "Forecast failed: ".$e->getMessage()."\n";
            }
        });
    }

}
