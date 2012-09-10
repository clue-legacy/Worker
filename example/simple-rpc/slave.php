<?php

require_once __DIR__.'/../../vendor/autoload.php';

$master = Worker_Master::connect()->decorateMethods();

$master->addMethod('test',function($a){
    //var_dump($a);
    //throw new Exception();
    //Debug::backtrace();
    return $a;
});

$master->start();
