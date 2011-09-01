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
class Worker_Instance extends Worker_Master{
    
    /**
     * array of global methods to be sent to all connected slaves
     * 
     * @var array
     */
    protected $methods;
    
    /**
     * instanciate new worker server or client
     * 
     * @uses Worker_Master::addEvent() to automatically attach global methods for each connected slave
     */
    public function __construct(){
        parent::__construct();
        
        $this->methods = new Worker_Methods();
        $this->addEvent('slaveConnect',array($this,'onSlaveConnectMethods'));
    }
    
    /**
     * event handler: called when a new slave connects (MUST NOT be called manually!)
     * 
     * @param Worker_Slave $slave
     * @uses Worker_Slave::addMethods() to automatically attach global methods to newly connection slave
     */
    public function onSlaveConnectMethods(Worker_Slave $slave){
        $slave->addMethods($this->methods);
    }
    
    /**
     * get all slaves for given remote method
     * 
     * @param string $method
     * @return array
     * @uses Worker_Slave::hasRemoteMethod()
     */
    public function getRemoteMethodSlaves($method){
        $ret = array();
        foreach($this->getMethodSlaves() as $id=>$slave){
            if($slave->hasRemoteMethod($method)){
                $ret[$id] = $slave;
            }
        }
        return $ret;
    }
    
    /**
     * get random slave for given remote method
     * 
     * @param string $method
     * @return Worker_Slave
     * @throws Exception when no slave was found
     * @uses Worker_Instance::getRemoteMethodSlaves()
     * @uses shuffle()
     */
    public function getRemoteMethodSlave($method){
        $slaves = $this->getRemoteMethodSlaves($method);
        if(!$slaves){
            throw new Exception('No slave for given method found');
        }
        shuffle($slaves);
        return $slaves[0];
    }
    
    /**
     * get array of remote methods names
     * 
     * @return array
     * @uses Worker_Slave::getRemoteMethods()
     */
    public function getRemoteMethods(){
        $ret = array();
        foreach($this->getMethodSlaves() as $slave){
            $ret = array_merge($ret,$slave->getRemoteMethods());
        }
        return array_unique($ret);
    }
    
    /**
     * check whether any slave offers the given remote method
     * 
     * @param string $method
     * @return boolean
     * @uses Worker_Slave::hasRemoteMethod()
     */
    public function hasRemoteMethod($method){
        foreach($this->getMethodSlaves() as $slave){
            if($slave->hasRemoteMethod($method)){
                return true;
            }
        }
        return false;
    }
    
    /**
     * call given remote function
     * 
     * @param string $method
     * @return mixed
     * @uses Worker_Instance::getRemoteMethodSlave()
     * @uses Worker_Slave::call()
     */
    public function call($method){
        $slave = $this->getRemoteMethodSlave($method);
        return call_user_func_array(array($slave,'call'),func_get_args());
    }
    
    /**
     * call given remote function in the background
     * 
     * @param string $method
     * @return Worker_Job
     * @uses Worker_Instance::getRemoteMethodSlave()
     * @uses Worker_Slave::callBackground()
     */
    public function callBackground($method){
        $slave = $this->getRemoteMethodSlave($method);
        return call_user_func_array(array($slave,'callBackground'),func_get_args());
    }
    
    public function proxyBackground(){
        return new Worker_Proxy_Background($this);
    }
    
    public function proxyBlock(){
        return new Worker_Proxy_Block($this);
    }
    
    /**
     * add new server
     * 
     * @param string|int $server
     * @return Worker_Instance $this (chainable)
     * @uses Worker_Master::addSlave()
     */
    public function addServer($server){
        $slave = Worker_Slave::factoryStream($server);
        $slave = $slave->decorateMethods();
        $this->addSlave($slave);
        return $this;
    }
    
    /**
     * add new global method to be offered to all clients
     * 
     * @param string        $name
     * @param callback|NULL $fn
     * @return Worker_Instance $this (chainable)
     * @uses Worker_Methods::addMethod()
     * @uses Worker_Slave::hasMethod()
     * @uses Worker_Slave::addMethod()
     */
    public function addMethod($name,$fn=NULL){
        $this->methods->addMethod($name,$fn);
        
        foreach($this->getMethodSlaves() as $slave){                            // forward method to all clients
            if(!$slave->hasMethod($name)){
                $slave->addMethod($name,$fn);
            }
        }
        return $this;
    }
    
    /**
     * add new global methods to be offered to all clients
     * 
     * @param mixed $methods
     * @return Worker_Instance $this (chainable)
     * @uses Worker_Methods::addMethods()
     * @uses Worker_Slave::hasMethod()
     * @uses Worker_Slave::addMethods()
     */
    public function addMethods($methods){
        $methods = $this->methods->addMethods($methods);
        
        if($methods){
            foreach($this->getMethodSlaves() as $slave){                        // for each client:
                $new = array();                                                 //   build array of new methods
                foreach($methods as $name=>$fn){
                    if(!$slave->hasMethod($name)){
                        $new[$name] = $fn;
                    }
                }
                if($new){
                    $slave->addMethods($new);                                   // only add new ones
                }
            }
        }
        return $this;
    }
    
    /**
     * get array of global method names offered to clients 
     * 
     * @return array
     * @uses Worker_Methods::getMethodNames()
     */
    public function getMethodNames(){
        return $this->methods->getMethodNames();
    }
    
    /**
     * get array of all methodified worker slaves
     * 
     * @return array
     */
    public function getMethodSlaves(){
        return $this->stream->getClientsInstace('Worker_Methodify');
    }
}
