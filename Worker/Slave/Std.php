<?php

/**
 * Worker slave using STDIN/STDOUT to communicate with master
 * 
 * @author mE
 */
class Worker_Slave_Std extends Worker_Slave{
    public function __construct(){
        parent::__construct(STDIN,STDOUT);
        $this->setDebug(false)->setAutosend(true);
    }
}