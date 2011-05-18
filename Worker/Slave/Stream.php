<?php

/**
 * worker slave using remote streams in order to communicate with master
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Slave_Stream extends Worker_Slave{
    /**
     * connection timeout
     * 
     * @var float
     */
    const TIMEOUT_CONNECTION = 30;
    
    /**
     * instanciate new remote connection to master with given address
     * 
     * @param string|int|NULL|resource $address hostname(+port),port or NULL=default address
     */
    public function __construct($address){
        if(is_resource($address)){
            $socket = $address;
        }else{
            $hostname = 'localhost';
            $port     = 12345;
            if(is_int($address)){
                $port = $address;
            }else if(strpos($address,':') !== false){
                $temp = explode(':',$address,2);
                $hostname = $temp[0];
                $port = (int)$temp[1];
            }else if(is_string($address)){
                $hostname = $address;
            }
            
            $errstr = NULL; $errno = NULL;
            $socket = fsockopen($hostname,$port,$errno,$errstr,self::TIMEOUT_CONNECTION);
            if($socket === false){
                throw new Worker_Exception('Unable to open socket to '.Debug::param($hostname).':'.Debug::param($port).' : '.Debug::param($errstr));
            }
        }
        parent::__construct($socket,$socket);
    }
}
