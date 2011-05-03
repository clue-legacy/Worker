<?php

class Worker_Master extends Stream_Master{
    /**
     * timeout for establishing new connections
     * 
     * @var float
     */
    const TIMEOUT_CONNECTION = 30;
    
    /**
     * array of worker slaves
     * 
     * @var array[Worker_Slave]
     */
    protected $slaves;
    
    /**
     * ports waiting for new connections
     * 
     * @var array[resource]
     */
    protected $ports;
    
    /**
     * array of active tasks
     * 
     * @var array[Worker_Task]
     */
    protected $tasks;
    
    /**
     * whether we're supposed to stay in our main loop
     * 
     * @var boolean
     */
    protected $go;
    
    /**
     * debugging flag
     * 
     * @var boolean
     */
    protected $debug;
    
    /**
     * connect to master and return new slave instance
     * 
     * @param string|NULL $address address to connect to
     * @return Worker_Slave
     */
    public static function connect($address=NULL){
        if($address === NULL){
            return new Worker_Slave_Std();
        }
        return new Worker_Slave_Stream($address);
    }
    
    /**
     * instanciate new master
     */
    public function __construct(){
        $this->slaves = array();
        $this->ports  = array();
        $this->tasks  = array();
        
        $this->go    = false;
        $this->debug = false; //true;
    }
    
    /**
     * destruct master (clean up all streams)
     */
    public function __destruct(){
        foreach($this->slaves as $slave){
            $slave->close();
        }
        $this->slaves = array();
        
        foreach($this->ports as $port){
            fclose($port);
        }
    }
    
    /**
     * spawn new worker process
     * 
     * @param string|Worker_Slave $slave
     * @return Worker_Slave
     */
    public function addSlave($slave){
        if(!($slave instanceof Worker_Slave)){
            $slave = new Worker_Slave_Process($slave);
        }
        $slave->setAutosend(false);
        $id = $this->slaves ? (max(array_keys($this->slaves)) + 1) : 1;
        $this->slaves[$id] = $slave;
        //$this->onSlaveConnect($slave);
        return $slave;
    }
    
    /**
     * return all worker slaves
     * 
     * @return array
     */
    public function getSlaves(){
        $ret = array();
        foreach($this->slaves as $id=>$slave){
            $ret[$id] = $slave;
        }
        return $ret;
    }
    
    /**
     * get id of given slave
     * 
     * @param Worker_Slave $slave
     * @return int
     * @throws Worker_Exception on error
     */
    public function getSlaveId($slave){
        foreach($this->slaves as $id=>$s){
            if($s === $slave){
                return $id;
            }
        }
        throw new Worker_Exception('Invalid slave object given');
    }
    
    /**
     * add new port to listen on
     * 
     * @param int|string $port
     * @return Worker_Master this (chainable)
     */
    public function addPort($port){
        $ip = '127.0.0.1';
        $address = 'tcp://'.$ip.':'.$port;
        $stream = stream_socket_server($address,$errno,$errstr);
        if($stream === false){
            throw new Worker_Exception('Unable to start server on '.Debug::param($address));
        }
        $this->ports[] = $stream;
        return $this;
    }
    
    /**
     * add new task
     * 
     * @param Worker_Task $task
     * @return Worker_Task
     * @throws Exception if task is already present
     */
    public function addTask($task){
        if(in_array($task,$this->tasks,true)){
            throw new Exception('Task already present');
        }
        $this->tasks[] = $task;
        return $task;
    }
    
    /**
     * remove given task
     * 
     * @param Worker_Task|int $task task instance or ID
     * @return Worker_Task
     * @throws Exception for invalid tasks
     */
    public function removeTask($task){
        if($task instanceof Worker_Task){
            $task = $this->getTaskId($task);
        }
        if(!isset($this->tasks[$task])){
            throw new Exception('Invalid task ID given');
        }
        $ret = $this->tasks[$task];
        unset($this->tasks[$task]);
        return $ret;
    }
    
    /**
     * get task ID for given task
     * 
     * @param Worker_task $task
     * @return int
     * @throws Exception when not found
     */
    public function getTaskId($task){
        $key = array_search($task,$this->tasks,true);
        if($key === false){
            throw new Exception('Invalid task given');
        }
        return $key;
    }
    
    /**
     * wait for new packets from worker slaves
     * 
     * will wait for a new event or timeout (Whichever comes first)
     * 
     * @param float|NULL $timeout maximum time to wait in seconds (NULL=wait forever)
     * @return Worker_Master this (chainable)
     * @throws Worker_Exception when timeout is reached
     * @uses Worker_Master::waitData()
     */
    public function waitPacket($timeout=NULL){
        if($timeout !== NULL){
            $timeout += microtime(true);
        }
        while(!$this->hasPacket()){
            $this->waitData($timeout);
            
            if($timeout !== NULL && microtime(true) > $timeout && !$this->hasPacket()){
                throw new Worker_Timeout_Exception('Timeout');
            }
        }
        return $this;
    }
    
    /**
     * stop event loop
     * 
     * @return Worker_Master this (chainable)
     */
    public function halt(){
        $this->go = false;
        return $this;
    }
    
    /**
     * start event loop
     * 
     * @return Worker_Master this (chainable)
     * @uses Worker_Master::waitData()
     */
    public function go(){
        $ignore = NULL;
        $this->go = true;
        
        do{
            $this->waitData();
        }while($this->go);
        return $this;
    }
    
    /**
     * wait for data (or timeout) once
     * 
     * @param float|NULL $timeout maximum timeout
     */
    protected function waitData($timeout = NULL){
        foreach($this->tasks as $task){                                         // cycle through tasks in order to determine timeout
            if($task->isActive()){
                $t = $task->getTimeout();
                if($t !== NULL && ($timeout === NULL || $t < $timeout)){
                    $timeout = $t;
                }
            }
        }
        if($timeout !== NULL){                                                  // calculate timeout into ssleep/usleep
            $timeout = $timeout - microtime(true);
            if($timeout < 0){
                $timeout = 0;
            }
            if($this->debug) Debug::notice('[Wait for '.Debug::param(max($ssleep,0)).'s]');
        }else if($this->debug) Debug::notice('[Wait forever]');
        
        $this->streamSelect($timeout);
        
        foreach($this->tasks as $key=>$task){                                   // perform all expired tasks
            if($task->isActive() && $task->isExpired()){
                $task->act();
                
                if(!$task->isActive()){
                    unset($this->tasks[$key]);
                }
            }
        }
    }
    
    /**
     * get all slaves that are save to read from
     * 
     * @return array[Worker_Slave]
     */
    public function getSlavesPacket(){
        $ret = array();
        foreach($this->slaves as $id=>$slave){
            if($slave->hasPacket()){
                $ret[$id] = $slave;
            }
        }
        return $ret;
    }
    
    /**
     * check whether there's any packet to read
     * 
     * @return boolean
     */
    public function hasPacket(){
        foreach($this->slaves as $slave){
            if($slave->hasPacket()){
                return true;
            }
        }
        return false;
    }
    
    /**
     * called when a new slave has connected
     * 
     * @param resource $socket
     */
    protected function streamClientConnect($socket,$unused){
        $slave = $this->addSlave(new Worker_Slave_Stream($socket));
        
        echo NL.'SLAVE CONNECTED:'.NL.Debug::param($slave).NL;
    }
    
    /**
     * called when a slave has been disconnected
     * 
     * @param Worker_Slave $slav
     */
    protected function streamClientDisconnect($slave){
        $key = array_search($slave,$this->slaves,true);
        unset($this->slaves[$key]);
        
        echo NL.'SLAVE DISCONNECTED:'.NL.Debug::param($slave).NL;
    }
    
    protected function streamClientSend($slave){
        try{
            $slave->streamSend();
        }
        catch(Worker_Disconnect_Exception $e){
            return false;
        }
    }
    
    protected function streamClientReceive($slave){
        try{
            $slave->streamReceive();
        }
        catch(Worker_Disconnect_Exception $e){
            return false;
        }
    }
    
    protected function getStreamClients(){
        return $this->slaves;
    }
    
    protected function getStreamPorts(){
        return $this->ports;
    }
}
