<?php

namespace Ensemble\API\HTTP;

class IPAuth implements \QuickAPI\APIAuth
{
    protected function cidr_match($ip, $cidr)
    {
        list($subnet, $mask) = explode('/', $cidr);

        $lip = ip2long($ip);
        $lsub = ip2long($subnet);
        $lmasked = $lip & ~((1 << (32 - $mask)) - 1);

        if ($lmasked == $lsub)
        {
            return true;
        }

        return false;
    }

    public function __construct($cidr)
    {
        $this->cidr = $cidr;
    }

    public function checkCredentials($args, \QuickAPI\APIHandler $handler)
    {
        return $this->cidr_match($_SERVER['REMOTE_ADDR'], $this->cidr);
    }
}
