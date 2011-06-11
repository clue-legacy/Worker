<?php

/**
 * temporary class holding slave vars
 */
class Slave_Vars{
    /**
     * optional data
     * 
     * @var array
     */
    private $vars = array();
    
    public function __set($name,$value){
        if($value === NULL){
            unset($this->vars[$name]);
        }else{
            $this->vars[$name] = $value;
        }
    }
    
    public function __get($name){
        if(!isset($this->vars[$name])){
            throw new Exception('Invalid key '.Debug::param($name));
        }
        return $this->vars[$name];
    }
    
    public function __isset($name){
        return isset($this->vars[$name]);
    }
    
    public function __unset($name){
        unset($this->vars[$name]);
    }
}
