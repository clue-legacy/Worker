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
class Worker_Communicator_Stream extends Worker_Communicator{
    /**
     * timeout for establishing new connections
     * 
     * @var float
     */
    const TIMEOUT_CONNECTION = 30;
    
    /**
     * stream resource handle
     * 
     * @var resource
     */
    private $stream;
    
    /**
     * instanciate new remote connection to master with given address
     * 
     * @param string|int|NULL|resource $address hostname(+port),port or NULL=default address
     */
    public function __construct($address){
        if(is_resource($address)){
            $this->stream = $address;
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
            $this->stream = @fsockopen($hostname,$port,$errno,$errstr,self::TIMEOUT_CONNECTION);
            if($this->stream === false){
                throw new Worker_Exception('Unable to open socket to '.Debug::param($hostname).':'.Debug::param($port).' : '.Debug::param($errstr).' (code '.Debug::param($errno).')');
            }
        }
    }
    
    public function close(){
        fclose($this->stream);
    }
    
    public function getStreamRead(){
        return $this->stream;
    }
    
    public function getStreamWrite(){
        return $this->stream;
    }
    
    public function __toString(){
        return 'Stream to '.stream_socket_get_name($this->stream,true);
    }
}
