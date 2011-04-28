<?php
/**
 * proxy calls to slaves
 * 
 * @package Worker
 */

/**
 * class to proxy calls to slaves
 * 
 * @author mE
 * @package Worker
 */
abstract class Worker_Proxy{
    /**
     * slave to send calls to
     * 
     * @var Worker_Slave
     */
    protected $slave;
    
    /**
     * array of jobs to process
     * 
     * @var Worker_Job
     */
    protected $jobs;
    
    /**
     * instanciate new worker proxy
     * 
     * @param Worker_Slave $slave
     */
    public function __construct($slave){
        $this->slave = $slave;
        $this->jobs  = array();
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
     * (try to) handle given job
     * 
     * @param Worker_Job $job
     * @return boolean whether the job was handled (i.e. true=done, false=check other proxies)
     * @uses Worker_Job::getHandle()
     */
    public function handleJob($job){
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

/**
 * return immediately but fire callback when result is returned
 * 
 * @author mE
 */
class Worker_Proxy_Callback extends Worker_Proxy{
    private $callback;
    
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
    }
    
    public function handleJob($job){
        if(parent::handleJob($job)){
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
}

class Worker_Proxy_Ignore extends Worker_Proxy{
    public function __call($name,$args){
        $job = new Worker_Job_Ignore($name,$args);
        $this->slave->putJob($job,$this);
        return $job;
    }
}
