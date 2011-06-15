<?php

class Worker_Proxy_Background extends Worker_Proxy{
    /**
     * magic function, transform all calls into jobs and forward to slave
     * 
     * @param string $name
     * @param array  $args
     * @return Worker_Job
     * @uses Worker_Slave::putJob()
     */
    public function __call($name,$args){
        $job = new Worker_Job($name,$args);
        $this->jobs[] = $job;
        $this->slave->putJob($job,$this);
        return $job;
    }
}
