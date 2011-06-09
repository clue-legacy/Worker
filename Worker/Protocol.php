<?php

/**
 * helper class used to wrap protocol information and packet marshalling
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Protocol{
    /**
     * packet start identifier
     * 
     * @var string
     */
    const STX = "\x02";
    
    /**
     * packet end delimiter
     * 
     * @var string
     */
    const ETX = "\x03";
    
    /**
     * maximum incoming buffer length
     * 
     * @var int|NULL number of bytes (NULL=infinite)
     */
    protected $maxlength = NULL;
    
    /**
     * incoming/receiving buffer
     * 
     * @var string
     */
    protected $receiving = '';
    
    /**
     * whether to print protocol debug messages
     * 
     * @var boolean
     */
    protected $debug = false;
    
    /**
     * set incoming buffer length
     * 
     * @param int|NULL $length length in bytes (NULL=infinite)
     * @return Worker_Protocol $this (chainable)
     */
    public function setMaxlength($length){
        $this->maxlength = $length;
        return $this;
    }
    
    public function setDebug($toggle){
        $this->debug = !!$toggle;
        return $this;
    }
    
    /**
     * pack given data and return packet contents (no envelope!)
     * 
     * @param mixed $data
     * @return string
     */
    protected function pack($data){
        return serialize($data);
    }
    
    /**
     * pack given data and return new packet
     * 
     * @param mixed $data
     * @return string packet
     */
    public function marshall($data){
        try{
            return self::STX.$this->pack($data).self::ETX;
        }
        catch(Exception $e){
            Debug::dump($data);
            throw $e;
        }
    }
    
    /**
     * checks whether there is a finished packet in the incoming buffer
     * 
     * @return boolean
     */
    public function hasPacket(){
        return (strpos($this->receiving,self::ETX) !== false);
    }
    
    /**
     * get packet from buffer
     * 
     * @return mixed
     * @throws Worker_Exception when packet is not ready
     */
    public function getPacket(){
        $pos = strpos($this->receiving,self::ETX);                              // check for packet end
        if($pos === false){
            throw new Worker_Exception_Communication('Packet end missing');
        }
        
        $data = substr($this->receiving,0,$pos);                                // read packet until packet end
        
        $pos = strpos($this->receiving,self::STX);                              // change buffer to beginning of next packet
        if($pos !== false){                                                     // next start found
            $this->receiving = substr($this->receiving,$pos+1);                 // skip packet start
            //if($this->debug) Debug::notice('Incoming remaings '.Debug::param($this->receiving));
        }else{                                                                  // no next start found
            $this->receiving = '';                                              // clear buffer (may contain outbound data)
            //if($this->debug) Debug::notice('Clear incoming buffer');
        }
        
        $data = unserialize($data);
        //if($this->debug) Debug::notice('Packet '.Debug::param($data));
        //Debug::param('Incoming buffer remaining '.Debug::param($this->receiving));
        
        return $data;
    }
    
    /**
     * will be called when Worker_Slave receives new data => push data to incoming buffer
     * 
     * @param string $buffer additional incoming data
     * @return Worker_Protocol $this (chainable)
     * @throws Worker_Exception on error or if buffer exceeds maximum size
     */
    public function onData($buffer){
        if($this->receiving !== ''){                                            // already buffering, just append
            $this->receiving .= $buffer;
        }else{                                                                  // first packet
            $pos = strpos($buffer,self::STX);                                   // make sure it includes packet start
            if($pos !== false){
                //if($this->debug) echo '[Skipping '.Debug::param(substr($buffer,0,$pos)).']';
                $this->receiving = substr($buffer,$pos+1);                      // skip packet start
            }else{                                                              // ignore outbound data
                if($this->debug) echo '[Received out of bound '.Debug::param($buffer).']';
            }
        }
        if($this->debug && $this->receiving !== $buffer) Debug::notice('[Incoming buffer now '.Debug::param($this->receiving).']');
        
        if($this->maxlength !== NULL && strlen($this->receiving) > $this->maxlength){
            throw new Worker_Exception_Communication('Incoming buffer size of '.Debug::param(strlen($this->receiving)).' exceeds maximum of '.Debug::param(self::BUFFER_MAX));
        }
    }
    
    /**
     * put back given packet to incoming stream buffer
     * 
     * @param mixed $data
     * @return Worker_Protocol $this (chainable)
     * @uses Worker_Protocol::pack()
     */
    public function putBack($data){
        $this->receiving = $this->pack($data).self::ETX.(($this->receiving === '') ? '' : (self::STX.$this->receiving));
        return $this;
    }
    
    /**
     * push back multiple packets to incoming stream buffer
     * 
     * @param array[mixed] $packets
     * @return Worker_Protocol $this (chainable)
     * @uses Worker_Protocol::putBack()
     */
    public function putBacks($packets){
        foreach(array_reverse($packets) as $packet){
            $this->putBack($packet);
        }
        return $this;
    }
}
