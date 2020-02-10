<?php

namespace Ensemble\Device\Heating;

class Schedule
{
    public function __construct(\Ensemble\Storage\JsonStore $storage)
    {
        $this->storage = $storage;

        if($this->storage->ranges === false)
        {
            $this->storage->ranges = array();
        }
    }

    protected function addRange($range)
    {
        $this->storage->lock();
        $ranges = $this->storage->ranges;
        $ranges[$id = uniqid()] = $range;
        $this->storage->ranges = $ranges;
        $this->storage->release();
    }

    protected function delRange($id)
    {
        $this->storage->lock();
        $ranges = $this->storage->ranges;
        unset($ranges[$id]);
        $this->storage->ranges = $ranges;
        $this->storage->release();
    }

    public function getRanges()
    {
        return $this->storage->ranges;
    }

    // Compare two times in hh:mm format
    // -1 => $a < $b
    // 0 => $a = $b
    // 1 => $a > $b
    protected function timeCmp($a, $b)
    {
        // Convert unix timestamps to hh:mm format
        if(is_int($a))
            $a = date('H:i', $a);

        if(is_int($b))
            $b = date('H:i', $b);

        $a = explode(':', $a);
        $b = explode(':', $b);


        // First compare hours
        if($a[0] > $b[0])
            return 1;

        if($a[0] < $b[0])
            return -1;

        // Else we have to look at minutes
        if($a[1] < $b[1])
            return -1;

        if($a[1] > $b[1])
            return 1;

        return 0;
    }

    public function addDaily($start, $end, $state, $days=false)
    {
        // TODO: If span covers multiple days (eg 20:00 - 05:00) split it into two
        // If day is specified, the second part needs to be the next day!

        $this->addRange(array('type'=>'daily', 'days'=>$days, 'start'=>$start, 'end'=>$end, 'state'=>$state));
    }

    public function addTemporary($duration, $state='ON')
    {
        $this->addRange(array('type'=>'temp', 'start'=>time(), 'end'=>time() + $duration * 60, 'state'=>$state));
    }

    // Return the state of the first active range that we find
    // Else return false
    public function getState()
    {
        $ranges = $this->storage->ranges;

        foreach($ranges as $rid=>$r)
        {
            if($this->isRangeActive($r, $rid))
            {
                //echo "Range $rid is active\n";
                //var_dump($r);echo "\n\n";
                return $r['state'];
            }
        }

        return false;
    }

    public function getActiveRanges()
    {
        $ranges = $this->storage->ranges;
        $out = array();

        foreach($ranges as $rid=>$r)
        {
            if($this->isRangeActive($r, $rid))
            {
                $out[] = $r;
            }
        }

        return $out;
    }

    // See if a single range definition is active
    public function isRangeActive($range, $id=false)
    {
        // Daily ranges
        if($range['type'] == 'daily')
        {
            $days = $range['days'];
            $start = $range['start'];
            $end = $range['end'];

            $now = date('H:i');
            if($days == false || in_array(date('N'), $days))
            {

                if($this->timeCmp($start, $now) <= 0) // has started
                {
                    if($this->timeCmp($end, $now) >= 0) // has not ended
                    {
                        return true;
                    }
                    else
                    {
                        return false;
                    }
                }
                else
                {
                    return false;
                }
            }
            else
            {
                return false;
            }
        }
        // Temporary ranges
        elseif($range['type'] == 'temp')
        {
            if($range['start'] <= time()) // Check if range has started
            {
                if($range['end'] <= time()) // Check if range has ended
                {
                    if($id !== false) $this->delRange($id); // Remove expired range
                    return false;
                }
                else
                {
                    return true;
                }
            }
            else
            {
                return false;
            }
        }
    }
}
