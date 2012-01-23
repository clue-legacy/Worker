<?php

/**
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
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
     * @param string        $name
     * @param callback|NULL $fn
     * @return Worker_Methods $this (chainable)
     */
    public function addMethod($name,$fn){
        if($fn === NULL && is_string($name)){
            $fn = $name;
        }
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
        $method = $this->methods[$name];
        if(!is_callable($method)){
            throw new Worker_Exception('Given method is not callable (but registered)');
        }
        if(is_string($method)){
            switch(count($args)){
                case 0:
                    return $method();
                case 1:
                    return $method($args[0]);
                case 2:
                    return $method($args[0],$args[1]);
                case 3:
                    return $method($args[0],$args[1],$args[2]);
            }
        }else if(is_object($method[0])){
            $obj = $method[0];
            $call = $method[1];
            switch(count($args)){
                case 0:
                    return $obj->$call();
                case 1:
                    return $obj->$call($args[0]);
                case 2:
                    return $obj->$call($args[0],$args[1]);
                case 3:
                    return $obj->$call($args[0],$args[1],$args[2]);
            }
        }
        return call_user_func_array($method,$args);
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
