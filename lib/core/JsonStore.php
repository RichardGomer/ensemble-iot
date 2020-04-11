<?php

namespace Ensemble\Storage;

/**
 * Really simple class to provide shared access to persistent json objects
 */
class JsonReader
{
    protected $datafn, $datalockfn, $datalockfh;

    public function __construct($name, $dir=false)
    {
        if($dir === false)
        {
            $dir = _VAR;
            if(!is_dir($dir))
            {
                if(!mkdir($dir))
                {
                    trigger_error("Cannot create persistent storage at $dir", E_USER_ERROR);
                }
            }
        }

        $this->datafn = $dir.$name.'.json';
    }

    public function getData()
    {
        $data = json_decode(file_get_contents($this->datafn), true);

        return is_array($data) ? $data : array();
    }

    public function __get($k)
    {
        $data = $this->getData();

        if(!array_key_exists($k, $data))
        {
            return false;
        }

        return $data[$k];
    }
}


class JsonStore extends JsonReader
{
   public function __construct($name, $dir=false)
   {
       parent::__construct($name, $dir);

       // Create a lock file so we can recreate the actual file without destroying locks etc.
       $this->datalockfn = $this->datafn.'.lock';

       $this->datalockfh = fopen($this->datalockfn, 'w+');
       @chmod($this->datalockfn, 0777); // So web server can edit!

       if(!file_exists($this->datafn))
       {
           $this->clear();
       }
   }

   private $locked = false;
   public function lock()
   {
       $this->locked++;

       if($this->locked > 1) // Needn't lock again
            return;

       flock($this->datalockfh, LOCK_EX);
   }

   public function getData()
   {
       $this->lock();
       $res = parent::getData();
       $this->release();
       return $res;
   }

   public function release()
   {
       $this->locked = max(0, $this->locked - 1);

       if($this->locked > 0)
           return;

       flock($this->datalockfh, LOCK_UN);
   }

   public function isLocked()
   {
       return $this->locked > 0;
   }

   public function clear()
   {
       $this->lock();

       file_put_contents($this->datafn, '[]');
       chmod($this->datafn, 0777); // So web server can edit!

       $this->release();
   }

   public function __set($k, $v)
   {
       $this->lock();

       $data = $this->getData();
       $data[$k] = $v;
       file_put_contents($this->datafn, json_encode($data));

       $this->release();
   }
}
