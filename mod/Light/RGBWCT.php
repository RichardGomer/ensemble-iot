<?php

namespace Ensemble\Device\Light;

interface RGBWCT {
    public function on();

    public function off();

    public function setRGB($r, $g, $b);

    public function setCT($ct);

    public function setBrightness($percent);
}
