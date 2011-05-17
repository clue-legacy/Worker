<?php

class Worker_Methods implements Countable{
    /**
     * array of registered methods
     * 
     * @var array
     */
    private $methods;
    
    /**
     * instanciate new methods object
     * 
     * @param array $methods
     */
    public function __construct($methods=array()){
        $this->methods = $methods;
    }
    
    /**
     * checks whether given method is registered
     * 
     * @param string $name
     * @return boolean
     */
    public function hasMethod($name){
        return isset($this->methods[$name]);
    }
    
    /**
     * get array of all method names
     * 
     * @return array
     */
    public function getMethodNames(){
        return array_keys($this->methods);
    }
    
    /**
     * add single method
     * 
     * @param string   $name
     * @param callback $fn
     * @return Worker_Methods $this (chainable)
     */
    public function addMethod($name,$fn){
        $this->methods[$name] = $fn;
        return $this;
    }
    
    /**
     * add given methods
     * 
     * @param mixed $methods
     * @return array extracted methods
     * @uses Worker_Methods::extract()
     */
    public function addMethods($methods){
        $methods = Worker_Methods::extract($methods);
        foreach($methods as $name=>$fn){
            $this->methods[$name] = $fn;
        }
        return $methods;
    }
    
    /**
     * call given method name with given arguments
     * 
     * @param string $name
     * @param array $args
     * @return mixed
     * @throws Worker_Exception if method is invalid
     */
    public function call($name,$args){
        if(!isset($this->methods[$name])){
            throw new Worker_Exception('Given method does not exist');
        }
        if(!is_callable($this->methods[$name])){
            throw new Worker_Exception('Given method is not callable (but registered)');
        }
        return call_user_func_array($this->methods[$name],$args);
    }
    
    /**
     * extract method names from given class/instance/array
     * 
     * @param mixed $class
     * @return array array of methods
     */
    public static function extract($class){
        if(is_array($class)){
            return $class;
        }
        $ret = array();
        $r = new ReflectionClass($class);
        foreach($r->getMethods(ReflectionMethod::IS_PUBLIC) as $method){
            if(is_string($class) === $method->isStatic()){
                $ret[$method->name] = array($class,$method->name);
            }
        }
        return $ret;
    }
    
    /**
     * count number of methods
     * 
     * @return int
     */
    public function count(){
        return count($this->methods);
    }
    
    /**
     * convert to methods object with just method names
     * 
     * @return Worker_Methods
     */
    public function toPacket(){
        return new Worker_Methods(array_fill_keys(array_keys($this->methods),NULL));
    }
}
