<?php

/**
 * 
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Master{
    /**
     * timeout for establishing new connections
     * 
     * @var float
     */
    const TIMEOUT_CONNECTION = 30;
    
    /**
     * array of active tasks
     * 
     * @var array[Worker_Task]
     */
    protected $tasks = array();
    
    /**
     * whether we're supposed to stay in our main loop
     * 
     * @var boolean
     */
    protected $go = false;
    
    /**
     * debugging flag
     * 
     * @var boolean
     */
    protected $debug = false; // true;
    
    /**
     * stream handler
     * 
     * @var Stream_Master_Standalone
     */
    protected $stream;
    
    /**
     * event handler
     * 
     * @var EventEmitter
     */
    protected $events;
    
    /**
     * connect to master and return new slave instance
     * 
     * @param string|NULL $address address to connect to
     * @return Worker_Slave
     */
    public static function connect($address=NULL){
        if($address === NULL){
            return Worker_Slave::factoryStdio();
        }
        return Worker_Slave::factoryStream($address);
    }
    
    /**
     * instanciate new master
     */
    public function __construct(){
        $this->events = new EventEmitter();
        $this->events->addEvent('slaveConnect',array($this,'onSlaveConnectEcho'));
        $this->events->addEvent('slaveDisconnect',array($this,'onSlaveDisconnectEcho'));
        
        $this->stream = new Stream_Master_Standalone();
        $this->stream->addEvent('clientConnect',array($this,'onClientConnectForward'));
        $this->stream->addEvent('clientDisconnect',array($this,'onClientDisconnectForward'));
        $this->stream->addEvent('clientRead',array($this,'onClientReadForward'));
        $this->stream->addEvent('clientWrite',array($this,'onClientWriteForward'));
    }
    
    
    /**
     * destruct master (clean up all streams)
     * 
     * @uses Stream_Master_Standalone::startOnce() to send remaining packets
     * @uses Stream_Master_Standalone::close() to close all client streams
     */
    public function __destruct(){
        $this->stream->startOnce(0); // send remaining packets?
        $this->stream->close(); // close all client streams
        $this->stream = NULL;
    }
    
    public function setDebug($toggle){
        $this->debug = !!$toggle;
        return $this;
    }
    
    public function onSlaveConnectEcho(Worker_Slave $slave){
        echo "\r\nSlave connected: ";
        var_dump($slave);
        //echo NL.'SLAVE CONNECTED: '.NL.Debug::param($slave).NL;
    }
    
    public function onSlaveDisconnectEcho(Worker_Slave $slave){
        echo "\r\nSlave disconnected: ";
        var_dump($slave);
        //echo NL.'SLAVE DISCONNECTED:'.NL.Debug::param($slave).NL;
    }
    
    public function onClientConnectForward(Stream_Master_Client $client){
        //Debug::dump($client,'Connected');
        //throw new Stream_Master_Exception();
        
        $this->stream->removeClient($client);
        $this->addSlave(Worker_Slave::factoryStream($client->getStreamRead()));
    }
    public function onClientDisconnectForward(Stream_Master_Client $slave){
        if($slave instanceof Worker_Slave){
            $this->events->fireEvent('slaveDisconnect',$slave);
        }
    }
    public function onClientReadForward(Stream_Master_Client $slave){
        if($slave instanceof Worker_Slave){
            try{
                $slave->streamReceive();
            }
            catch(Worker_Exception_Disconnect $e){
                throw new Stream_Master_Exception();
            }
        }
    }
    public function onClientWriteForward(Stream_Master_Client $slave){
        if($slave instanceof Worker_Slave){
            try{
                $slave->streamSend();
            }
            catch(Worker_Exception_Disconnect $e){
                throw new Stream_Master_Exception();
            }
        }
    }
    
    /**
     * called when client has read new data, try to handle packets
     * 
     * @param Stream_Master_Client $slave
     * @uses Worker_Slave::handlePackets()
     */
    public function onClientReadPacket(Stream_Master_Client $slave){
        if($slave instanceof Worker_Slave){
            $slave->handlePackets();
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
            $slave = Worker_Slave::factoryProcess($slave);
        }
        $this->stream->addClient($slave);
        
        $this->events->fireEvent('slaveConnect',$slave);
        
        return $slave;
    }
    
    /**
     * return all worker slaves
     * 
     * @return array[Worker_Slave]
     */
    public function getSlaves(){
        $slaves = array();
        foreach($this->stream->getClients() as $id=>$slave){
            if($slave instanceof Worker_Slave){
                $slaves[$id] = $slave;
            }
        }
        return $slaves;
    }
    
    /**
     * get id of given slave
     * 
     * @param Worker_Slave $slave
     * @return int
     * @throws Worker_Exception on error
     */
    public function getSlaveId($slave){
        return $this->stream->getClientId($slave);
    }
    
    /**
     * add new port to listen on
     * 
     * @param int|string $port
     * @return Worker_Master this (chainable)
     */
    public function addPort($port){
        $this->stream->addPort($port);
        return $this;
    }
    
    public function addEvent($name,$fn){
        $this->events->addEvent($name,$fn);
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
     * @param float|NULL $timeout maximum time to wait as target timestamp (NULL=wait forever)
     * @return Worker_Master this (chainable)
     * @throws Worker_Exception when timeout is reached
     * @uses Worker_Master::waitData()
     */
    public function waitPacket($timeout=NULL){
        while(!$this->hasPacket()){
            $this->waitData($timeout);
            
            if($timeout !== NULL && microtime(true) > $timeout && !$this->hasPacket()){
                throw new Worker_Exception_Timeout('Waiting for packet timed out');
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
     * @uses Worker_Master::onClientReadPacket() via EventEmitter when data arrived
     * @uses Worker_Master::waitData() to actually wait for data
     */
    public function start(){
        $this->go = true;
        
        $this->events->addEvent('clientRead',array($this,'onClientReadPacket'));
        
        try{
            do{
                $this->waitData();
            }while($this->go);
        }
        catch(Exception $e){                                                    // an error occured:
            $this->go = false;                                                  // make sure to reset to previous state
            $this->events->removeEvent('clientRead',array($this,'onClientReadPacket'));
            throw $e;
        }
        $this->events->removeEvent('clientRead',array($this,'onClientReadPacket'));
        return $this;
    }
    
    /**
     * get timestamp of next task timeout
     * 
     * @param float|NULL $timeout maximum timeout
     * @return float|NULL
     */
    protected function getTaskTimeout($timeout=NULL){
        foreach($this->tasks as $task){                                         // cycle through tasks in order to determine timeout
            if($task->isActive()){
                $t = $task->getTimeout();
                if($t !== NULL && ($timeout === NULL || $t < $timeout)){
                    $timeout = $t;
                }
            }
        }
        return $timeout;
    }
    
    /**
     * wait for data (or timeout) once
     * 
     * @param float|NULL $timeout maximum timeout (timestamp!)
     */
    protected function waitData($timeout = NULL){
        $timeout = $this->getTaskTimeout($timeout);
        if($timeout !== NULL){                                                  // calculate timeout into ssleep/usleep
            $timeout = $timeout - microtime(true);
            if($timeout < 0){
                $timeout = 0;
            }
            if($this->debug) Debug::notice('[Wait for '.Debug::param($timeout).'s]');
        }else if($this->debug) Debug::notice('[Wait forever]');
        
        $this->stream->startOnce($timeout);
        
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
        foreach($this->getSlaves() as $id=>$slave){
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
        foreach($this->getSlaves() as $slave){
            if($slave->hasPacket()){
                return true;
            }
        }
        return false;
    }
}
