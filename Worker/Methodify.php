<?php

/**
 * decorator class holding slave methods
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Methodify extends Worker_Slave{
        
    /**
     * proxies waiting for job results
     * 
     * @var array[Worker_Proxy]
     */
    private $proxies = array();
    
    /**
     * keeps track of methods offered to the other side
     * 
     * @var Worker_Methods
     */
    protected $methods;
    
    /**
     * keeps track of remote methods callable
     * 
     * @var array
     */
    protected $methodsRemote = array();
    
    /**
     * instanciate new method interface for given slave
     * 
     * @param Worker_Slave $slave
     * @uses Worker_Slave::getCommunicator() to share communicator with decorated slave
     * @uses Worker_Slave::getDebug()
     * @uses Worker_Slave::setDebug()
     */
    public function __construct(Worker_Slave $slave){
        parent::__construct($slave->getCommunicator());
        $this->setDebug($slave->getDebug());
        $this->methods = new Worker_Methods();
    }
    
    /**
     * why decorate again? return self
     * 
     * @return Worker_Methodify
     */
    public function decorateMethods(){
        return $this;
    }

    /**
     * get new callback proxy interface
     * 
     * @param callback $callback
     * @return Worker_Proxy_Callback
     * @see Worker_Proxy_Callback
     */
    public function proxyCallback($callback){
        return new Worker_Proxy_Callback($this,$callback);
    }
    
    /**
     * get new proxy interface ignoring method results
     * 
     * @return Worker_Proxy_Ignore
     * @see Worker_Proxy_Ignore
     */
    public function proxyIgnore(){
        return new Worker_Proxy_Ignore($this);
    }
    
    /**
     * get new proxy interface for background jobs
     * 
     * @return Worker_Proxy_Background
     * @see Worker_Proxy_Background
     */
    public function proxyBackground(){
        return new Worker_Proxy_Background($this);
    }
    
    /**
     * get new proxy interface waiting for methods to return (normal blocking method calls)
     * 
     * @return Worker_Proxy_Block
     * @see Worker_Proxy_Block
     */
    public function proxyBlock(){
        return new Worker_Proxy_Block($this);
    }
    
    /**
     * call given method (with optional arguments)
     * 
     * this is a blocking call and wait for the given job to be finished
     * 
     * @param string|Worker_Job $name
     * @return mixed return value as-is
     * @throws Exception exception as-is
     * @uses Worker_Slave::putPacket() to transmit send job packet
     * @uses Worker_Methodify::waitJob() to wait for job results
     * @uses Worker_Job::ret() to process return value
     */
    public function call($name){
        if($name instanceof Worker_Job){
            $job = $name;
        }else{
            $args = func_get_args();
            unset($args[0]);
            $job = new Worker_Job($name,$args); // create new job for given arguments
        }
        
        return $this->putPacket($job)->waitJob($job)->ret();
    }
    
    /**
     * call given method in the background (with optional arguments)
     * 
     * this is a non-blocking call which will put the job into the outgoing buffer and return the job instance
     * 
     * @param string|Worker_Job $name
     * @return Worker_Job
     * @uses Worker_Slave::putPacket() to transmit send job packet
     */
    public function callBackground($name){
        if($name instanceof Worker_Job){
            $job = $name;
        }else{
            $args = func_get_args();
            unset($args[0]);
            $job = new Worker_Job($name,$args); // create new job for given arguments
        }
        
        $this->putPacket($job);
        return $job;
    }
    
    /**
     * wait for the given (background) job to return
     * 
     * @param Worker_Job $job incomplete job
     * @return Worker_Job complete job including job results (NOT the same as input argument!)
     * @uses Worker_Slave::getPacketWait() to wait for result packet
     * @uses Worker_Protocol::putBacks() to put back invalid packets received while waiting
     */
    public function waitJob($job){
        $handle = $job->getHandle();
        
        $packets = array();                                                     // buffer of useless packets received in the meantime
        do{
            if($this->debug) Debug::notice('[Wait for next packet]');
            $packet = $this->getPacketWait();                                   // wait for new packet
            if($packet instanceof Worker_Job && $packet->getHandle() === $handle){ // correct packet received
                $job = $packet;
                if($this->debug) Debug::notice('[Correct '.Debug::param($job).' received]');
                break;
            }else{
                if($this->debug) Debug::notice('[Useless '.Debug::param($packet).' received');
                $packets[] = $packet;                                           // buffer incorrect packet
            }
        }while(true);
        
        if($packets){
            if($this->debug) Debug::notice('[Put back useless packets '.Debug::param($packets).']');
            $this->protocol->putBacks($packets);
        }
        
        return $job;                                                            // return job results
    }
    
    /**
     * add new job to outgoing queue (should not be called manually)
     * 
     * @param Worker_Job        $job
     * @param Worker_Proxy|NULL $proxy
     * @uses Worker_Slave::putPacket()
     */
    public function putJob($job,$proxy=NULL){
        if($this->debug) Debug::notice('[Outgoing '.Debug::param($job).']');
        $this->putPacket($job);
        
        if($proxy !== NULL && !in_array($proxy,$this->proxies,true)){           // only add proxy if it's not listed already
            $this->proxies[] = $proxy;
        }
    }
    
    /**
     * check whether slave still has any jobs
     * 
     * @return boolean
     */
    public function hasJob(){
        return ($this->proxies ? true : false);
    }
    
    /**
     * try to handle given job
     * 
     * @param Worker_Job $job
     * @return boolean true if given packet was a job that could be handled, false otherwise
     * @uses Worker_Job::isStarted() to check whether job has to be started
     * @uses Worker_Job::call() to actually invoke job
     * @uses Worker_Slave::putPacket() to send back job results
     * @uses Worker_Proxy::handleJob() to try to handle job on each proxy
     * @uses Worker_Proxy::hasJob() to remove proxy if it has no more jobs attached
     */
    protected function handleJob(Worker_Job $job){
        if($this->debug) Debug::notice('[Incoming job '.Debug::param($job).']');
        
        if(!$job->isStarted() /*$job->getSlaveId() === $this->id*/){
            $job->call($this->methods);
            if(!($job instanceof Worker_Job_Ignore)){
                $this->putPacket($job);
            }
            return true;
        }else{
            foreach($this->proxies as $id=>$proxy){
                if($proxy->handleJob($job)){
                    if(!$proxy->hasJob()){
                        unset($this->proxies[$id]);
                    }
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * called when new packet has been received
     * 
     * @param mixed $packet
     * @throws Worker_Exception_Communication if given packet is invalid
     * @uses Worker_Methodify::handleJob() to try to handle packet as job
     */
    protected function onPacket($packet){
        if($packet instanceof Worker_Job){
            if($this->handleJob($packet)){
                return;
            }
        }else if($packet instanceof Worker_Methods){
            $this->methodsRemote = $packet->getMethodNames();
            if($this->debug) Debug::dump('methodsRemote',$this->methodsRemote);
            return;
        }
        
        if($this->debug) Debug::notice('[Unknown incoming packet '.Debug::param($packet).']');
        
        throw new Worker_Exception_Communication('Unknown incoming packet');
    }
    
    /**
     * get array of method names
     * 
     * @return array
     * @uses Worker_Methods::getMethodNames()
     */
    public function getMethodNames(){
        return $this->methods->getMethodNames();
    }
    
    /**
     * check whether client offers the given method
     * 
     * @param string $name
     * @return boolean
     * @uses Worker_Methods::hasMethod()
     */
    public function hasMethod($name){
        return $this->methods->hasMethod($name);
    }
    
    /**
     * add new method to be offered to the other side
     * 
     * @param string        $name
     * @param callback|NULL $fn
     * @return Worker_Methodify $this (chainable)
     * @uses Worker_Methods::addMethod()
     * @uses Worker_Methods::toPacket()
     * @uses Worker_Slave::putPacket()
     */
    public function addMethod($name,$fn=NULL){
        $this->methods->addMethod($name,$fn);
        return $this->putPacket($this->methods->toPacket());
    }
    
    /**
     * add new methods to be offered to the other side
     * 
     * @param mixed $methods
     * @return Worker_Methodify $this (chainable)
     * @uses Worker_Methods::addMethods()
     * @uses Worker_Methods::toPacket()
     * @uses Worker_Slave::putPacket()
     */
    public function addMethods($methods){
        if($this->methods->addMethods($methods)){                               // only pack if something actually changed
            $this->putPacket($this->methods->toPacket());
        }
        return $this;
    }
    
    /**
     * checks whether this client known the given remote method
     * 
     * @param string $name
     * @return boolean
     */
    public function hasRemoteMethod($name){
        return in_array($name,$this->methodsRemote,true);
    }
    
    /**
     * get array of remote method names offered to this client
     * 
     * @return array
     */
    public function getRemoteMethods(){
        return $this->methodsRemote;
    }
}
