<?php

/**
 * class to proxy calls to slaves
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
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
