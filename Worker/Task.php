<?php

/**
 * worker tasks implementing timed events (event driven programming)
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Task{
    /**
     * return a task that will be executed at the given timestamp
     * 
     * @param float $max
     * @param mixed $callback
     * @return Worker_Task
     */
    public static function factoryOnce($max,$callback){
        return new Worker_Task(NULL,$max,$callback);
    }
    
    /**
     * return a new task with no timeout but ready to be executed
     * 
     * @param mixed $callback
     * @return Worker_Task
     */
    public static function factoryAsap($callback){
        $task = new Worker_Task(NULL,NULL,$callback);
        $task->state = self::STATE_HIT;
        return $task;
    }
    
    /**
     * return a new task instance
     * 
     * @param float|NULL $min
     * @param float|NULL $max
     * @param mixed $callback
     * @return Worker_Task
     */
    public static function factory($min,$max,$callback){
        return new Worker_Task($min,$max,$callback);
    }
    
    ////////////////////////////////////////////////////////////////////////////
    
    /**
     * idle default state
     * 
     * @var int
     */
    const STATE_IDLE = 0;
    
    /**
     * task was 'hit', i.e. going to be executed when timeMin is reached
     * 
     * @var int
     */
    const STATE_HIT = 1;
    
    /**
     * task has been executed
     * 
     * @var int
     */
    const STATE_DONE = 2;
    
    ////////////////////////////////////////////////////////////////////////////
    
    /**
     * callback function
     * 
     * @var callback
     */
    protected $callback;
    
    /**
     * earliest possible time of execution (requires hit())
     * 
     * @var float
     * @see Worker_Task::hit()
     */
    protected $timeMin;
    
    /**
     * upper limit of when task will be executed automatically
     * 
     * @var float
     */
    protected $timeMax;
    
    /**
     * time offset
     * 
     * @var float|NULL
     */
    protected $timeBase;
    
    /**
     * current task state
     * 
     * @var int
     */
    protected $state;
    
    /**
     * instanciate new task
     * 
     * @param float|NULL $min earliest possible time of execution (requires hit)
     * @param float|NULL $max latest time of execution (ignores hit)
     */
    protected function __construct($min,$max,$callback){
        $this->callback = $callback;
        
        $this->timeMin = $min;
        $this->timeMax = $max;
        // $this->setTimeBase();
        
        $this->state   = self::STATE_IDLE;
    }
    
    /**
     * set base time
     * 
     * @param float|NULL $now (optional) now, defaults to current timestamp
     * @return Worker_Task this (chainable)
     */
    public function setTimeBase($now=NULL){
        if($now === NULL){
            $now = microtime(true);
        }
        if($this->timeMin !== NULL){
            $this->timeMin += $now;
            if($this->timeBase !== NULL){
                $this->timeMin -= $this->timeBase;
            }
        }
        if($this->timeMax !== NULL){
            $this->timeMax += $now;
            if($this->timeBase !== NULL){
                $this->timeMax -= $this->timeBase;
            }
        }
        $this->timeBase = $now;
        return $this;
    }
    
    /**
     * we now wish to act on this task (making sure minimum time is obeyed)
     * 
     * @return Worker_Task this (chainable)
     * @uses Worker_Task::isExpired()
     * @uses Worker_Task::act()
     */
    public function hit(){
        if($this->state === self::STATE_IDLE){
            $this->state = self::STATE_HIT;
            
            if($this->isExpired()){
                $this->act();
            }
        }
        return $this;
    }
    
    /**
     * activate task
     * 
     * @return Worker_Task this (chainable)
     */
    public function act(){
        if($this->timeMin !== NULL || $this->timeMax !== NULL){
            $this->state = self::STATE_DONE;
        }
        
        $fn = $this->callback;
        if(is_string($fn) || is_callable(array($fn,'__invoke'))){
            $fn($this);
        }else{
            call_user_func($fn,$this);
        }
        
        return $this;
    }
    
    /**
     * check whether task is still to be executed
     * 
     * @return boolean
     */
    public function isActive(){
        return ($this->state !== self::STATE_DONE);
    }
    
    /**
     * check whether task is expied (to be executed)
     * 
     * @return boolean
     */
    public function isExpired(){
        $now = microtime(true);
        
        if($this->timeMin === NULL && $this->timeMax === NULL){                 // special 'Asap' instance is always expired
            return true;
        }
        
        if($this->timeMax !== NULL && $now > $this->timeMax){                   // maximum limit hit?
            return true;
        }
        return ($this->state === self::STATE_HIT && ($this->timeMin !== NULL && $now > $this->timeMin)); // minimum limit hit?
    }
    
    /**
     * get time when this task is to be executed
     * 
     * @return float|NULL
     */
    public function getTimeout(){
        if($this->state === self::STATE_HIT && $this->timeMin !== NULL){
            return $this->timeMin;
        }
        return $this->timeMax;
    }
}
