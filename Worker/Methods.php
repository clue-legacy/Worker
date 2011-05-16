<?php

class Worker_Methods{
    private $methods;
    
    public function __construct($methods=array()){
        $this->methods = array();
    }
    
    public function hasMethod($name){
        return isset($this->methods[$name]);
    }
    
    public function getMethodNames(){
        return array_keys($this->methods);
    }
    
    public function addMethod($name,$fn){
        $this->methods[$name] = $fn;
    }
    
    public function addMethods($methods){
        if(!is_array($methods)){
            $methods = Worker_Methods::extract($methods);
        }
        foreach($methods as $name=>$fn){
            $this->methods[$name] = $fn;
        }
        return $methods;
    }
    
    public function call($name,$args){
        if(!isset($this->methods[$name])){
            throw new Worker_Exception('Given method not callable');
        }
        return call_user_func_array($this->methods[$name],$args);
    }
    
    public static function extract($class){
        return array();
    }
    
    public function toPacket(){
        return new Worker_Methods(array_fill_keys(array_keys($this->methods),NULL));
    }
}
