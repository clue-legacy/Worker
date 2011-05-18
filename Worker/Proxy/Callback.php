<?php

/**
 * return immediately but fire callback when result is returned
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Proxy_Callback extends Worker_Proxy{
    private $callback;
    
    /**
     * array of jobs to process
     * 
     * @var Worker_Job
     */
    protected $jobs;
    
    /**
     * instanciate new callback proxy
     * 
     * @param Worker_Slave $slave
     * @param callback     $callback
     */
    public function __construct($slave,$callback){
        if(!is_callable($callback)){
            throw new Exception('Invalid callback function given');
        }
        parent::__construct($slave);
        $this->callback = $callback;
        
        $this->jobs  = array();
    }
    
    public function handleJob($job){
        if($this->popJob($job)){
            $fn = $this->callback;
            if(is_string($fn) || is_callable(array($fn,'__invoke'))){
                $fn($job,$this->slave);
            }else{
                call_user_func($fn,$job,$this->slave);
            }
            return true;
        }
        return false;
    }
    
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
    
    /**
     * check whether this proxy still has any jobs attached
     * 
     * @return boolean
     */
    public function hasJob(){
        return ($this->jobs ? true : false);
    }
    
    /**
     * (try to) select given job and remove from current list of jobs
     * 
     * @param Worker_Job $job
     * @return boolean whether the job was handled (i.e. true=done, false=check other proxies)
     * @uses Worker_Job::getHandle()
     */
    public function popJob($job){
        $key = NULL;
        $handle = $job->getHandle();
        foreach($this->jobs as $i=>$j){
            if($j->getHandle() === $handle){
                unset($this->queue[$i]);
                return true;
            }
        }
        return false;
    }
}
