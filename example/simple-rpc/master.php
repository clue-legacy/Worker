<?php

require_once(dirname(__FILE__).'/../init.inc.php');

$master = new Worker_Master();
$slave = $master->addSlave('php '.dirname(__file__).'/slave.php')->decorateMethods();

var_dump('echo',$slave->proxyBlock()->test(123));

var_dump('echo again',$slave->proxyBlock()->test(123));

//$slave2 = $master->addSlave(new Worker_Slave_Local('Worker'));

echo 'invalid';
try{
    var_dump($slave->call('debug'));
}
catch(Exception $e){
    echo ' FAILED (which is GOOD!)';
}

/*
$proxy = $slave->proxyBackground();

$jobs = new Worker_Job_Queue();
$jobs->add($proxy->test(1));
$jobs->add($slave,'test',1);

$jobs->work();
*/


