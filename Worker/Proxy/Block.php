<?php

/**
 * block and wait for the function to return
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Proxy_Block extends Worker_Proxy{
    /**
     * magic function, transform all calls into jobs and forward to slave
     * 
     * @param string $name
     * @param array  $args
     * @return mixed return value as-is
     * @uses Worker_Slave::call()
     */
    public function __call($name,$args){
        $job = new Worker_Job($name,$args);
        return $this->slave->call($job);
    }
}
