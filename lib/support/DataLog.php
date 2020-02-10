<?php

namespace Ensemble\Log;

/**
 * This creates a data logger, based on JsonStore
 * Each Log has multiple channels and will store values at a specified resolution
 */
class DataLog
{
    protected $store;
    public function __construct(JSONStore $store, $resolution=300)
    {
        $this->store = $store;
        $this->resolution = $resolution; // Resolution of the log, in seconds (300 = one value recorded every 5 minutes)
    }

    public function getChannels()
    {
        return array_keys($this->store->readings);
    }

    public function getLastReadings($channel, $n=1)
    {
        $readings = $this->store->readings;

        if($n >= count($readings[$channel]))
        {
            return $readings[$channel];
        }

        return array_slice($readings[$channel], $n * -1, $n, true);
    }

    // Resolution can be overridden using the last argument (eg for using different resolutions on different channels)
    public function store($channel, $value, $resolution=false)
    {
        $this->store->lock();
        $readings = $this->store->readings;

        $res = $resolution === false ? $this->resolution : (int) $resolution;
        $time = ceil(time() / $res) * $res; // Ceil rather than floor, so resolution changes don't jump backwards in time
        $readings[$channel][$time] = $value;

        $this->store->readings = $readings; // sync with store
        $this->store->release();
    }
}
