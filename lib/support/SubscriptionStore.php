<?php

namespace Ensemble\KeyValue;

/**
 * A key-value store that can trigger callbacks when updated.
 */
class SubscriptionStore {

    // There are different update types, which subscribers may wish to treat differently
    public const UPTYPE_SOFT = 1; // Soft updates are contextual. best-effort, status updates. They indicate passive changes in device status.
    public const UPTYPE_INTENT = 8; // Intentional updates represent a deliberate state changes, e.g. a button being toggled

    public function __set($key, $value) {
        $this->set($key, $value);
    }

    public function __get($key) {
        return $this->get($key);
    }

    /**
     * Set a value and trigger subscribed callbacks
     */
    private $data = array();
    public function set($key, $value, $type=self::UPTYPE_SOFT) {
        $key = strtoupper($key);
        $this->data[$key] = $value;

        // Notify subscribers
        if(array_key_exists($key, $this->subs)) {
            foreach($this->subs[$key] as $cb) {
                call_user_func($cb, $key, $value, $type);
            }
        }
    }

    /**
     * Get the value of a key
     * @throws KeyNotSetException
     */
    public function get($key) {
        $key = strtoupper($key);
        if(!array_key_exists($key, $this->data)) {
            throw new KeyNotSetException("'$key' is not set");
        }

        return $this->data[$key];
    }

    /**
     * Get all defined keys/values
     */
    public function getAll() {
        return $this->data;
    }

    /**
     * Subscribe a callback to a key
     */
    private $subs = array();
    public function sub($key, $cb) {
        $key = strtoupper($key);
        if(!array_key_exists($key, $this->subs))
            $this->subs[$key] = array();

        $this->subs[$key][] = $cb;
    }

    /**
     * Set keys from an array. Multi-dimensional data is flattened via keys like
     * parent.child.subchild
     */
    public function setArray($array, $path = array()) {

        foreach($array as $k=>$v) {
            if(is_array($v)) {
                $this->setArray($v, array_merge($path, array($k)));
            } else {
                $this->set(implode('.', array_merge($path, array($k))), (string) $v);
            }
        }

    }
}

class KeyNotSetException extends \Exception { }
