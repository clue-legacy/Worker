<?php

/**
 * return immediately and fire Worker_Job_Ignore jobs
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Proxy_Ignore extends Worker_Proxy_Background{
    public function __call($name,$args){
        $job = new Worker_Job_Ignore($name,$args);
        $this->slave->putJob($job);
        return $job;
    }
}
