<?php

abstract class Worker_Slave implements Interface_Stream_Duplex{
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
     * chunk to read at once
     * 
     * @var int
     */
    const BUFFER_CHUNK = 4096; // read 4KiB each
    
    /**
     * maximum buffer length
     * 
     * @var int
     */
    const BUFFER_MAX = 16384; // maximum of 16KiB
    
    /**
     * outgoing buffer
     * 
     * @var string
     */
    private $sending;
    
    /**
     * incoming buffer
     * 
     * @var string
     */
    private $receiving;
    
    /**
     * stream to read from
     * 
     * @var resource
     */
    private $rstream;
    
    /**
     * stream to write to
     * 
     * @var resource
     */
    private $wstream;
    
    /**
     * optional data
     * 
     * @var array
     */
    private $vars;
    
    /**
     * proxies waiting for job results
     * 
     * @var array[Worker_Proxy]
     */
    private $proxies;
    
    /**
     * whether to automatically send data when calling putPacket()
     * 
     * @var boolean
     */
    protected $autosend;
    
    /**
     * debugging on/of
     * 
     * @var boolean
     */
    protected $debug;
    
    /**
     * old incoming buffer
     * 
     * @var string
     */
    protected $old;
    
    public function __construct($rstream,$wstream){
        $this->sending   = '';
        $this->receiving = '';
        $this->rstream   = $rstream;
        $this->wstream   = $wstream;
        $this->vars      = array();
        $this->proxies   = array();
        $this->autosend  = true;
        
        $this->debug = false; // true;
        $this->old   = '';
    }
    
    /**
     * set whether to automatically send data when calling putPacket()
     * 
     * @param boolean $toggle
     * @return Worker_Slave this (chainable)
     */
    public function setAutosend($toggle){
        $this->autosend = (bool)$toggle;
        return $this;
    }
    
    /**
     * toggle debugging
     * 
     * @param boolean $toggle
     * @return Worker_Slave this (chainable)
     */
    public function setDebug($toggle){
        $this->debug = (bool)$toggle;
        return $this;
    }
    
    /**
     * close slave streams
     *  
     * @return Worker_Slave this (chainable)
     */
    public function close(){
        fclose($this->rstream);
        if($this->rstream !== $this->wstream){
            fclose($this->wstream);
        }
        return $this;
    }
    
    
    /**
     * checks whether there is a finished packet in the incoming queue
     * 
     * @return boolean
     */
    public function hasPacket(){
        return (strpos($this->receiving,self::ETX) !== false);
    }
    
    /**
     * add new packet to outgoing queue
     * 
     * @param mixed $data
     * @throws Worker_Exception if buffer exceeds maximum size
     * @return Worker_Slave this (chainable)
     * @uses Worker_Slave::send() if autosend is set
     */
    public function putPacket($data){
        $this->sending .= self::STX.serialize($data).self::ETX;
        
        if(strlen($this->sending) > self::BUFFER_MAX){
            throw new Worker_Exception('Outgoing buffer size of '.Debug::param(strlen($this->sending)).' exceeds maximum of '.Debug::param(self::BUFFER_MAX));
        }
        if($this->autosend){
            $this->streamSend();
        }
        return $this;
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
    
    /**
     * get all packets from buffer
     * 
     * @return array[mixed]
     * @uses Worker_Slave::getPacket()
     */
    public function getPackets(){
        $ret = array();
        $go = true;
        do{
            try{
                $ret[] = $this->getPacket();
            }
            catch(Exception $e){
                $go = false;
            }
        }while($go);
        return $ret;
    }
    
    /**
     * wait for packet
     * 
     * this method will block until a packet is available or timeout is reached
     * 
     * @param float|NULL $timeout (optional) timeout in seconds, NULL=wait forever
     * @return mixed $data
     * @throws Worker_Exception on error or when timeout is reached
     * @uses Worker_Slave::getPacket() to check when a packet is finished
     * @uses Worker_Slave::send()
     * @uses Worker_Slave::receive()
     */
    public function getPacketWait($timeout=NULL){
        try{                                                                    // try to get packet once
            return $this->getPacket();
        }
        catch(Worker_Exception $e){ }
            
        $ssleep = NULL;
        $usleep = NULL;
        if($timeout !== NULL){
            $timeout += microtime(true);
        }
        
        $ignore = NULL;
        do{                                                                     // keep waiting for complete new packet
            $read  = array($this->rstream);
            $write = ($this->sending === '') ? array() : array($this->wstream);
            
            if($timeout !== NULL){                                              // calculate timeout into ssleep/usleep
                $ssleep = $timeout - microtime(true);
                if($this->debug) Debug::notice('Wait for '.Debug::param(max($ssleep,0)).'s');
                if($ssleep < 0){
                    $ssleep = 0;
                    $usleep = 0;
                }else{
                    $usleep = (int)(($ssleep - (int)$ssleep)*1000000);
                    $ssleep = (int)$ssleep;
                }
            }else if($this->debug) Debug::notice('Wait forever');
            $ret = stream_select($read,$write,$ignore,$ssleep,$usleep);         // wait for incoming/outgoing stream
            if($ret === false){
                throw new Worker_Exception('stream_select() failed');
            }
            if($write){
                $this->streamSend();
            }
            if($read){
                $this->streamReceive();                                         // receive data and try again
                
                try{                                                            // try to get packet
                    return $this->getPacket();
                }
                catch(Worker_Exception $e){ }                                   // ignore errors in case packet is not complete
            }
        }while($timeout === NULL || $timeout > microtime(true));                // retry until timeout is reached
        
        throw new Worker_Timeout_Exception('Timeout');
    }
    
    /**
     * actually receive from worker stream
     * 
     * @throws Worker_Exception on error or if buffer exceeds maximum size
     * @return Worker_Slave this (chainable)
     * @uses Worker_Slave::onData()
     */
    public function streamReceive(){
        $buffer = fread($this->rstream,self::BUFFER_CHUNK);
        if($buffer === false){
            throw new Worker_Exception('Unable to read data from stream');
        }
        if($buffer === ''){
            throw new Worker_Disconnect_Exception('No data read, stream closed?');
        }
        
        $this->onData($buffer);
        return $this;
    }
    
    /**
     * called when new data has been received
     * 
     * @param string $buffer
     * @uses Worker_Slave::getPackets()
     * @uses Worker_Slave::onPacket() on each packet received
     */
    protected function onData($buffer){
        if($this->debug) Debug::notice('[Received '.Debug::param($buffer).']');
        
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
        if($this->debug) Debug::notice('[Incoming buffer now '.Debug::param($this->receiving).']');
        
        $this->old = substr($this->old.$buffer,-20000);
        
        if(strlen($this->receiving) > self::BUFFER_MAX){
            throw new Worker_Exception('Incoming buffer size of '.Debug::param(strlen($this->receiving)).' exceeds maximum of '.Debug::param(self::BUFFER_MAX));
        }
        
        foreach($this->getPackets() as $packet){
            $this->onPacket($packet);
        }
    }
    
    /**
     * called when new packet has been received
     * 
     * @param mixed $packet
     * @uses Worker_Slave::handleJob() to try to handle packet as job
     * @uses Worker_Slave::putBack() to put back packet to incoming queue if it's not a job
     */
    protected function onPacket($packet){
        if(!$this->handleJob($packet)){
            if($this->debug) Debug::notice('[Incoming packet '.Debug::param($packet).']');
            $this->putBack($packet);
        }
    }
    
    /**
     * put back given packet to incoming stream buffer
     * 
     * @param mixed $data
     * @return Worker_Slave this (chainable)
     */
    protected function putBack($data){
        $this->receiving = serialize($data).self::ETX.(($this->receiving === '') ? '' : (self::STX.$this->receiving));
        return $this;
    }
    
    /**
     * returns whether there's unsent outgoing data remaining
     * 
     * @return boolean
     */
    public function hasStreamOutgoing(){
        return ($this->sending !== '');
    }
    
    /**
     * actually send outgoing buffer to worker stream
     * 
     * @throws Worker_Exception on error
     * @return Worker_Slave this (chainable)
     */
    public function streamSend(){
        //if($this->debug) echo '[Sending '.Debug::param($this->sending).']';
        $bytes = fwrite($this->wstream,$this->sending);
        if($bytes === false){
            throw new Worker_Exception('Unable to write data to stream');
        }
        if($bytes === 0){
            throw new Worker_Disconnect_Exception('Nothing sent, stream closed?');
        }
        if($this->debug) echo '[Sent '.Debug::param($bytes).' B: '.Debug::param(substr($this->sending,0,$bytes)).']';
        if($bytes == strlen($this->sending)){
            $this->sending = '';
        }else{
            $this->sending = substr($this->sending,$bytes);
        }
        if($this->debug) echo '[Outgoing buffer now '.Debug::param($this->sending).']';
        return $this;
    }
    
    /**
     * get stream to read from
     * 
     * @return resource
     */
    public function getStreamReceive(){
        return $this->rstream;
    }
    
    /**
     * get stream to write to
     * 
     * @return resource
     */
    public function getStreamSend(){
        return $this->wstream;
    }
    
    public function __set($name,$value){
        if($value === NULL){
            unset($this->vars[$name]);
        }else{
            $this->vars[$name] = $value;
        }
    }
    
    public function __get($name){
        if(!isset($this->vars[$name])){
            throw new Exception('Invalid key '.Debug::param($name));
        }
        return $this->vars[$name];
    }
    
    public function __isset($name){
        return isset($this->vars[$name]);
    }
    
    public function __unset($name){
        unset($this->vars[$name]);
    }
    
    /**
     * get new callback proxy interface
     * 
     * @param callback $callback
     * @return Worker_Proxy_Callback
     * @see Worker_Proxy_Callback
     */
    public function proxyCallback($callback){
        return new Worker_Proxy_Callback($this,$callback);
    }
    
    /**
     * get new proxy interface ignoring method results
     * 
     * @return Worker_Proxy_Ignore
     * @see Worker_Proxy_Ignore
     */
    public function proxyIgnore(){
        return new Worker_Proxy_Ignore($this);
    }
    
    /**
     * get new proxy interface waiting for methods to return (normal blocking method calls)
     * 
     * @return Worker_Proxy_Block
     * @see Worker_Proxy_Block
     */
    public function proxyBlock(){
        return new Worker_Proxy_Block($this);
    }
    
    /**
     * call given method (and optional arguments)
     * 
     * this is a blocking call and wait for the given job to be finished
     * 
     * @param string|Worker_Job $name
     * @return mixed return value as-is
     * @throws Exception exception as-is
     */
    public function call($name){
        if($name instanceof Worker_Job){
            $job = $name;
        }else{
            $args = func_get_args();
            unset($args[0]);
            $job = new Worker_Job($name,$args); // create new job for given arguments
        }
        
        $this->putPacket($job);
        $handle = $job->getHandle();
        
        $packets = array();
        do{
            $packet = $this->getPacketWait();                                   // wait for new packet
            if($packet->getHandle() === $handle){                               // correct packet received
                $job = $packet;
                break;
            }else{
                $packets[] = $packet;                                           // buffer incorrect packet
            }
        }while(true);
        
        foreach($packets as $packet){                                           // put back incorrect packets
            $this->putBack($packet);
        }
        
        return $job->ret();                                                     // return job results
    }
    
    /**
     * add new job to outgoing queue (should not be called manually)
     * 
     * @param Worker_Job        $job
     * @param Worker_Proxy|NULL $proxy
     * @uses Worker_Slave::putPacket()
     */
    public function putJob($job,$proxy=NULL){
        if($this->debug) Debug::notice('[Outgoing '.Debug::param($job).']');
        $this->putPacket($job);
        
        if($proxy !== NULL && !in_array($proxy,$this->proxies,true)){           // only add proxy if it's not listed already
            $this->proxies[] = $proxy;
        }
    }
    
    /**
     * check whether slave still has any jobs
     * 
     * @return boolean
     */
    public function hasJob(){
        return ($this->proxies ? true : false);
    }
    
    /**
     * try to handle given job
     * 
     * @param Worker_Job $job
     * @return boolean true if given packet was a job that could be handled, false otherwise
     * @uses Worker_Proxy::handleJob() to try to handle job on each proxy
     * @uses Worker_Proxy::hasJob() to remove proxy if it has no more jobs attached
     */
    protected function handleJob($job){
        if($job instanceof Worker_Job){
            if($this->debug) Debug::notice('[Incoming '.Debug::param($job).']');
            foreach($this->proxies as $id=>$proxy){
                if($proxy->handleJob($job)){
                    if(!$proxy->hasJob()){
                        unset($this->proxies[$id]);
                    }
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * start listening for jobs on given object
     * 
     * @param mixed $on instance or object to call methods on
     * @uses Worker_Slave::getPacketWait() to wait for new job
     * @uses Worker_Job::call() to execute job
     * @uses Worker_Slave::putPacket() to send back results
     */
    public function serve($on){
        $this->autosend = false;
        while(true){
            $packet = $this->getPacketWait();
            if($packet instanceof Worker_Job){
                $packet->call($on);
                if(!($packet instanceof Worker_Job_Ignore)){
                    $this->putPacket($packet);
                }
            }else{                                                              // invalid packet
                echo Debug::param($packet).NL;
            }
        }
    }
}
