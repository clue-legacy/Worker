<?php

require_once('Worker/Methods.php');

class A{
    public function a(){
        
    }
    
    private function b(){
        
    }
    
    public static function c(){
        
    }
    
    private static function d(){
        
    }
}

class Worker_MethodsTest extends PHPUnit_Framework_TestCase{
    public function testSimple(){
        $methods = new Worker_Methods();
        
        $methods->addMethod('test',array($this,'testSimple'));
        $methods->addMethod('print_r','print_r');
        $methods->addMethod('closure',function($a){
            var_dump($a);
        });
        
        $this->assertEquals(3,count($methods));
    }
    
    public function testClass(){
        $a = new A();
        $methods = new Worker_Methods();
        
        $methods->addMethods($a); // add all non-static public functions
        
        $this->assertEquals(array('a'),$methods->getMethodNames());
        $this->assertEquals(1,count($methods));
        
        $methods->addMethods('A'); // add all static public functions
        
        $this->assertEquals(array('a','c'),$methods->getMethodNames());
        $this->assertEquals(2,count($methods));
    }
}
