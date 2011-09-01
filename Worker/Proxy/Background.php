<?php

class Worker_Proxy_Background extends Worker_Proxy{
    /**
     * magic function, transform all calls into jobs and forward to slave
     * 
     * @param string $name
     * @param array  $args
     * @return Worker_Job
     * @uses Worker_Slave::callBackground()
     */
    public function __call($name,$args){
        return $this->slave->callBackground(new Worker_Job($name,$args));
    }
}
