<?php

/**
 * Worker slave representation for master communicating via process pipes
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Slave_Process extends Worker_Slave{
    /**
     * process instance
     * 
     * @var Process
     */
    private $process;
    
    /**
     * instanciate new process
     * 
     * @param string $cmd command to execute
     */
    public function __construct($cmd){
        $this->process = new Process($cmd);
        
        parent::__construct();
    }
    
    public function close(){
        $this->process->kill(true)->close(true);
        unset($this->process);
    }
    
    public function getStreamRead(){
        return $this->process->getStreamRead();
    }
    
    public function getStreamWrite(){
        return $this->hasOutgoing() ? $this->process->getStreamWrite() : NULL;
    }
}
