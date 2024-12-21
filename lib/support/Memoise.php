<?php

namespace Ensemble\Util;

trait Memoise {

    protected $memoized = [];
    public function memoise(
      string $key, 
      int $lifetime,
      \Closure $callback
    ) {
        if (!isset($this->memoized[$key]) || $this->memoized[$key]['update'] < time() - $lifetime) {
            $value = $this->memoized[$key]['value'] = $callback();
            $this->memoized[$key]['update'] = time();
            return $value;
        }
        return $this->memoized[$key]['value'];
    }

}