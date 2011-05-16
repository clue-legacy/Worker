<?php

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
    
    protected $maxlength = NULL;
    
    protected $receiving = '';
    
    public function setMaxlength($length){
        $this->maxlength = $length;
        return $this;
    }
    
    public function marshall($data){
        if($data instanceof Exception){
            $data = new Exception($data->getMessage(),$data->getCode());
        }
        try{
            return self::STX.serialize($data).self::ETX;
        }
        catch(Exception $e){
            Debug::dump($data);
            throw $e;
        }
    }
    
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
            throw new Worker_Exception('Packet end missing');
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
        
        //$this->old = substr($this->old.$buffer,-20000);
        
        if($this->maxlength !== NULL && strlen($this->receiving) > $this->maxlength){
            throw new Worker_Exception('Incoming buffer size of '.Debug::param(strlen($this->receiving)).' exceeds maximum of '.Debug::param(self::BUFFER_MAX));
        }
    }
    
    public function putBack($data){
        $this->receiving = serialize($data).self::ETX.(($this->receiving === '') ? '' : (self::STX.$this->receiving));
    }
}