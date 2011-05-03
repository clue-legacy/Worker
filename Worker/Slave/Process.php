<?php

/**
 * Worker slave representation for master communicating via process pipes
 * 
 * @author mE
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
        $this->process->start();
        
        parent::__construct($this->process->getStreamReceive(),$this->process->getStreamSend());
    }
    
    public function close(){
        parent::close();
        $this->process->stop();
    }
}
