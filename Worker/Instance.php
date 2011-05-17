<?php

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
        
        $that = $this;
        $this->addEvent('slaveConnect',function($slave) use ($that){
            $slave->addMethods($that->getMethodsInstance());                    // send each global method to slave
        });
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
        foreach($this->getSlaves() as $id=>$slave){
            if($slave->hasRemoteMethod($method)){
                $ret[$id] = $slave;
            }
        }
        return $ret;
    }
    
    /**
     * get slave for given remote method
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
        foreach($this->getSlaves() as $slave){
            $ret += $slave->getRemoteMethods();
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
        foreach($this->getSlaves() as $slave){
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
     */
    public function call($method){
        $slave = $this->getRemoteMethodSlave($method);
        return call_user_func_array(array($slave,'call'),func_get_args());
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
        $this->addSlave(new Worker_Slave_Stream($server));
        return $this;
    }
    
    /**
     * add new global method to be offered to all clients
     * 
     * @param string   $name
     * @param callback $fn
     * @return Worker_Instance $this (chainable)
     * @uses Worker_Methods::addMethod()
     * @uses Worker_Slave::hasMethod()
     * @uses Worker_Slave::addMethod()
     */
    public function addMethod($name,$fn){
        $this->methods->addMethod($name,$fn);
        
        foreach($this->getSlaves() as $slave){                                  // forward method to all clients
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
     * @uses Worker_Slave::addMethod()
     */
    public function addMethods($methods){
        $methods = $this->methods->addMethods($methods);
        
    	foreach($methods as $name=>$fn){
            if(!$slave->hasMethod($name)){
                $slave->addMethod($name,$fn);
            }
        }
        return $this;
    }
    
    /**
     * get methods instance
     * 
     * @return Worker_Methods
     */
    public function getMethodsInstance(){
        return $this->methods;
    }
    
    /**
     * get array of global method names offered to clients 
     * 
     * @return array
     * @uses Worker_Methods::getMethodNames()
     */
    public function getMethodsNames(){
        return $this->methods->getMethodNames();
    }
}
