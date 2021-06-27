<?php

require 'vendor/autoload.php';

$t = new Ensemble\System\Thread("python3 -u ".dirname(__FILE__)."/testsig.py");

echo "Begin";

for($i = 0; $i < 3; $i++) {
    sleep(1);
    var_dump($t->read());
}

$t->close(2); // 2 = SIGINT

while($t->isRunning()) {
    echo "Waiting...";
    usleep(250000);
}
