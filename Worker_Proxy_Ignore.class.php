<?php

class Worker_Proxy_Ignore extends Worker_Proxy{
    public function __call($name,$args){
        $job = new Worker_Job_Ignore($name,$args);
        $this->slave->putJob($job);
        return $job;
    }
}
