<?php

/**
 * return immediately but fire callback when result is returned
 * 
 * @author mE
 */
class Worker_Proxy_Callback extends Worker_Proxy{
    private $callback;
    
    /**
     * instanciate new callback proxy
     * 
     * @param Worker_Slave $slave
     * @param callback     $callback
     */
    public function __construct($slave,$callback){
        if(!is_callable($callback)){
            throw new Exception('Invalid callback function given');
        }
        parent::__construct($slave);
        $this->callback = $callback;
    }
    
    public function handleJob($job){
        if(parent::handleJob($job)){
            $fn = $this->callback;
            if(is_string($fn) || is_callable(array($fn,'__invoke'))){
                $fn($job,$this->slave);
            }else{
                call_user_func($fn,$job,$this->slave);
            }
            return true;
        }
        return false;
    }
}
