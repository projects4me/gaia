<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * This class is used as an execution clock. Once the class is initialized
 * it records the current time.
 *
 * In order to record the execution time since the start you can call
 * the function tillnow
 *
 * In order to the execution time between intervals you can call diff
 * function which will tell you the time elapsed since the last call
 * of the diff function
 *
 * @class executiontime
 * @package Gaia\Libraries\Utils
 */
class executiontime
{
    protected $starttime;
    protected $lasttime;

    public function __construct()
    {
        $this->starttime = $this->gettime();
        $this->lasttime = $this->gettime();
    }

    public function tillnow()
    {
        return ($this->gettime() - $this->starttime);
    }
    public function diff()
    {
        $lasttime = ($this->gettime() - $this->lasttime);
        $this->lasttime = $this->gettime();
        return $lasttime;
    }

    private function gettime()
    {
        $mtime = explode(" ",microtime());
        return $mtime[1] + $mtime[0];
    }
}
