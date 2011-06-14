<?php

require_once(dirname(__FILE__).'/../init.inc.php');

$master = Worker_Master::connect()->decorateMethods();

$master->addMethod('test',function($a){
    //var_dump($a);
    //throw new Exception();
    //Debug::backtrace();
    return $a;
});

$master->start();
