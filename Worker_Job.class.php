<?php
/**
 * external worker jobs
 * 
 * @package Worker
 */

/**
 * class representing external worker jobs
 * 
 * @author mE
 * @package Worker
 */
class Worker_Job{
    /**
     * function/method name
     * 
     * @var string
     */
    private $callee;
    
    /**
     * function arguments
     * 
     * @var array
     */
    private $arguments;
    
    /**
     * internal job handle
     * 
     * @var int
     */
    private $handle;
    
    /**
     * function output (echo)
     * 
     * @var string
     */
    private $output;
    
    /**
     * function return code
     * 
     * @var mixed
     */
    private $return;
    
    /**
     * exception thrown during exceution of function
     * 
     * @var NULL|Exception
     */
    private $exception;
    
    /**
     * time job was created on
     * 
     * @var float
     */
    private $timeCreate;
    
    /**
     * time job was started on
     * 
     * @var float
     */
    private $timeStart;
    
    /**
     * time job was ended on
     * 
     * @var float
     */
    private $timeEnd;
    
    /**
     * instanciate new exception
     * 
     * @param string $callee
     * @param array  $arguments
     */
    public function __construct($callee,$arguments){
        $this->callee    = $callee;
        $this->arguments = $arguments;
        
        $this->handle = mt_rand();
        
        $this->output    = '';
        $this->return    = NULL;
        $this->exception = NULL;
        
        $this->timeCreate = microtime(true);
        $this->timeStart  = NULL;
        $this->timeEnd    = NULL;
    }
    
    /**
     * perform current job on given object/instance
     * 
     * @param mixed $on instance or object to call methods on
     */
    public function call($on){
        $r = new Output_Mirror($this->output); // mirror all output to $this->output (keep reference to $r to trigger garbage collection)
        
        $this->timeStart = microtime(true);
        try{
            if(!is_callable(array($on,$this->callee))){
                throw new Worker_Exception('Given method not callable');
            }
            $this->return = call_user_func_array(array($on,$this->callee),$this->arguments);
        }
        catch(Exception $e){
            $this->exception = $e;
        }
        $this->timeEnd = microtime(true);
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
     * get return value or re-throw exception
     * 
     * @return mixed
     * @throws Exception
     */
    public function ret(){
        if($this->output !== ''){
            echo $this->output;
        }
        if($this->exception !== NULL){
            throw new $this->exception;
        }
        return $this->return;
    }
}
