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
