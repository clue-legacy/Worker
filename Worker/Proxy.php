<?php
/**
 * proxy calls to slaves
 * 
 * @package Worker
 */

/**
 * class to proxy calls to slaves
 * 
 * @author mE
 * @package Worker
 */
abstract class Worker_Proxy{
    /**
     * slave to send calls to
     * 
     * @var Worker_Slave
     */
    protected $slave;
    
    /**
     * instanciate new worker proxy
     * 
     * @param Worker_Slave $slave
     */
    public function __construct($slave){
        $this->slave = $slave;
    }
    
    /**
     * magic function, transform all calls into jobs and forward to slave
     * 
     * @param string $name
     * @param array  $args
     */
    abstract public function __call($name,$args);
}
