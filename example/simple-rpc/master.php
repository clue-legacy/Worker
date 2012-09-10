<?php

require_once __DIR__.'/../../vendor/autoload.php';

$master = new Worker_Master();
$master->setDebug(true);
$slave = $master->addSlave('php '.dirname(__file__).'/slave.php')->decorateMethods();

echo 'ECHO';
var_dump(123);
var_dump($slave->proxyBlock()->test(123));

//$master->setDebug(false);
$slave->setDebug(false);

echo 'ECHO again';
var_dump(123);
var_dump($slave->proxyBlock()->test(123));

//$slave2 = $master->addSlave(new Worker_Slave_Local('Worker'));

echo 'invalid';
try{
    var_dump($slave->call('debug'));
}
catch(Exception $e){
    echo ' FAILED (which is GOOD!)';
}

//$slave->stop();

//$master->close();

/*
$proxy = $slave->proxyBackground();

$jobs = new Worker_Job_Queue();
$jobs->add($proxy->test(1));
$jobs->add($slave,'test',1);

$jobs->work();
*/


