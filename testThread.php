<?php

require 'vendor/autoload.php';

$t = new Ensemble\System\Thread("/home/pi/ensemble-iot/mod/Irrigation/YFS201/yfs201flow 26");

while(true) {
    sleep(3);
    var_dump($t->read());
}
