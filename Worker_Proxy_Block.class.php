<?php

/**
 * block and wait for the function to return
 * 
 * @author mE
 */
class Worker_Proxy_Block extends Worker_Proxy{
    /**
     * magic function, transform all calls into jobs and forward to slave
     * 
     * @param string $name
     * @param array  $args
     * @return mixed return value as-is
     * @uses Worker_Slave::on()
     */
    public function __call($name,$args){
        $job = new Worker_Job($name,$args);
        return $this->slave->on($job);
    }
}
