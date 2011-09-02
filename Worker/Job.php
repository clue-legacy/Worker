<?php

/**
 * class representing external worker jobs
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Job{
    /**
     * function/method name
     * 
     * @var string
     */
    protected $method;
    
    /**
     * function arguments
     * 
     * @var array
     */
    protected $arguments;
    
    /**
     * internal job handle
     * 
     * @var int
     */
    protected $handle;
    
    /**
     * function output (echo)
     * 
     * @var string
     */
    protected $output = '';
    
    /**
     * function return code
     * 
     * @var mixed
     */
    protected $return = NULL;
    
    /**
     * exception thrown during execution of function (if any)
     * 
     * @var NULL|Exception
     */
    protected $exception = NULL;
    
    /**
     * time job was created on
     * 
     * @var float
     */
    protected $timeCreate;
    
    /**
     * time job was started on
     * 
     * @var float|NULL
     */
    protected $timeStart = NULL;
    
    /**
     * time job was ended on
     * 
     * @var float|NULL
     */
    protected $timeEnd = NULL;
    
    /**
     * instanciate new exception
     * 
     * @param string $method
     * @param array  $arguments
     */
    public function __construct($method,$arguments){
        $this->method    = $method;
        $this->arguments = $arguments;
        
        $this->handle = mt_rand();
       
        $this->timeCreate = microtime(true);
    }
    
    /**
     * perform current job on given methods object
     * 
     * @param Worker_Methods $methods methods object to perform actual call on
     * @uses Output_Mirror to mirror output to job output variable
     * @uses Worker_Methods::call() to actually call the method
     * @uses ReflectionObject to remove stack trace from exceptions
     */
    public function call($methods){
        if(PHP_VERSION >= 5.3 && class_exists('Output_Mirror',true)){
            $r = new Output_Mirror($this->output); // mirror all output to $this->output (keep reference to $r to trigger garbage collection)
        }else{
            ob_start();
        }
            
        $this->timeStart = microtime(true);
        try{
            $this->return = $methods->call($this->method,$this->arguments);
        }
        catch(Exception $e){
            $r = new ReflectionObject($e);                                      // hide exception trace
            do{
                try{
                    $p = $r->getProperty('trace');                              // do not reveal to much local information
                    $p->setAccessible(true);                                    // also, trace may include unserializable data (closure arguments, etc.)
                    $p->setValue($e,array());
                }
                catch(Exception $ignore){ }                                     // property may not be available, continue with parent class
                $r = $r->getParentClass();
            }while($r);
            
            $this->exception = $e;
        }
        $this->timeEnd = microtime(true);
        
        if(!isset($r)){
            $this->output = ob_get_contents();
            ob_end_flush();
        }
    }
    
    /**
     * get method argument
     * 
     * @return array
     */
    public function getArgs(){
        return $this->arguments;
    }
    
    /**
     * get method name
     * 
     * @return string
     */
    public function getMethod(){
        return $this->method;
    }
    
    /**
     * get exception thrown (if any)
     * 
     * @return Exception|NULL
     */
    public function getException(){
        return $this->exception;
    }
    
    /**
     * get job output (echo / print)
     * 
     * @return string
     */
    public function getOutput(){
        return $this->return;
    }
    
    /**
     * get return value
     * 
     * @return mixed
     */
    public function getReturn(){
        return $this->return;
    }
    
    /**
     * get internal job handle
     * 
     * @return mixed
     */
    public function getHandle(){
        return $this->handle;
    }
    
    /**
     * get time job was created on
     * 
     * @return float
     */
    public function getTimeCreate(){
        return $this->timeCreate;
    }
    
    /**
     * get time job was ended on
     * 
     * @return float|NULL
     */
    public function getTimeEnd(){
        return $this->timeEnd;
    }
    
    /**
     * get time job was ended on
     * 
     * @return float|NULL
     */
    public function getTimeStart(){
        return $this->timeStart;
    }
    
    /**
     * get execution time (between starting and finishing job)
     * 
     * @return float
     */
    public function getTimeExecution(){
        return ($this->timeEnd-$this->timeStart);
    }
    
    /**
     * get total time (between creating and finishing job)
     * 
     * @return float
     */
    public function getTimeTotal(){
        return ($this->timeEnd-$this->timeCreate);
    }
    
    /**
     * get total time wasted (i.e. not spend executing job)
     * 
     * @return float
     */
    public function getTimeOverhead(){
        return (microtime(true)-$this->timeEnd+$this->timeStart-$this->timeCreate);
    }
    
    /**
     * returns whether the job has already been started
     * 
     * @return boolean
     */
    public function isStarted(){
        return ($this->timeStart !== NULL);
    }
    
    /**
     * returns whether the job is finished (i.e. result is available)
     * 
     * @return boolean
     */
    public function isFinished(){
        return ($this->timeEnd !== NULL);
    }
    
    /**
     * get return value or re-throw exception
     * 
     * @return mixed
     * @throws Exception
     */
    public function ret(){
        if($this->timeEnd === NULL){
            throw new Worker_Exception('Job has yet to be finished');
        }
        if($this->output !== ''){
            echo $this->output;
        }
        if($this->exception !== NULL){
            throw $this->exception;
        }
        return $this->return;
    }
    
    /**
     * get return value after waiting for job results
     * 
     * @param Worker_Methodify $slave
     * @param NULL|float   $timeout (optional) timeout in seconds
     * @return mixed
     * @throws Exception
     * @uses Worker_Job::waitFinish()
     * @uses Worker_Job::ret()
     */
    public function retWait($slave,$timeoutAt=NULL){
        return $this->waitFinish($slave,$timeoutAt)->ret();
    }
    
    /**
     * wait for the job to finish
     * 
     * @param Worker_Methodify $slave
     * @param NULL|timeout     $timeoutAt (optional) timeout in seconds
     * @return Worker_Job $this (chainable)
     * @uses Worker_Methodify::waitJob()
     */
    public function waitFinish($slave,$timeoutAt=NULL){
        if($this->timeEnd === NULL){
            $new = $slave->waitJob($this,$timeoutAt);
            $this->output    = $new->getOutput();
            $this->return    = $new->getReturn();
            $this->exception = $new->getException();
            $this->timeStart = $new->getTimeStart();
            $this->timeEnd   = $new->getTimeEnd();
        }
        return $this;
    }
}
