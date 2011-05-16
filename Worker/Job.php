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
    protected $callee;
    
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
    protected $output;
    
    /**
     * function return code
     * 
     * @var mixed
     */
    protected $return;
    
    /**
     * exception thrown during execution of function
     * 
     * @var NULL|Exception
     */
    protected $exception;
    
    /**
     * time job was created on
     * 
     * @var float
     */
    protected $timeCreate;
    
    /**
     * time job was started on
     * 
     * @var float
     */
    protected $timeStart;
    
    /**
     * time job was ended on
     * 
     * @var float
     */
    protected $timeEnd;
    
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
     * perform current job on given methods object
     * 
     * @param Worker_Methods $methods methods object to perform actual call on
     */
    public function call($methods){
        if(class_exists('Output_Mirror',true)){
            $r = new Output_Mirror($this->output); // mirror all output to $this->output (keep reference to $r to trigger garbage collection)
        }else{
            ob_start();
        }
            
        $this->timeStart = microtime(true);
        try{
            $this->return = $methods->call($this->callee,$this->arguments);
        }
        catch(Exception $e){
            $this->exception = $e;
        }
        $this->timeEnd = microtime(true);
        
        if(!isset($r)){
            $this->output = ob_get_contents();
            ob_end_flush();
        }
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
    
    public function isStarted(){
        return ($this->timeStart !== NULL);
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
