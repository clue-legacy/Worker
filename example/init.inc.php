<?php

define('PATH_INCLUDE',dirname(__FILE__).'/');

function autoload($class){
    $path = PATH_INCLUDE.'../'.str_replace('_','/',$class).'.php';
    if(file_exists($path)){
        require $path;
    }
}
spl_autoload_register('autoload');

if(!class_exists('EventEmitter',true) || !class_exists('Stream_Master_Client',true)){
    spl_autoload_register(function($class){
        $p1 = '/'.str_replace('_','/',$class).'.php';
        $p2 = '/'.$class.'.class.php';
        foreach(glob(PATH_INCLUDE.'../../*',GLOB_ONLYDIR) as $dir){
            if(file_exists($dir.$p1)){
                require_once($dir.$p1);
                return;
            }else if(file_exists($dir.$p2)){
                require_once($dir.$p2);
                return;
            }
        }
    });
}