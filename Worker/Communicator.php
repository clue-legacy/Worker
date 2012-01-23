<?php

abstract class Worker_Communicator{
    /**
     * get stream to read from
     * 
     * @return resource
     */
    abstract public function getStreamRead();
    
    /**
     * get stream to write to
     * 
     * @return resource
     */
    abstract public function getStreamWrite();
    
    /**
     * close all communication streams
     */
    abstract public function close();
    
    abstract public function __toString();
}
