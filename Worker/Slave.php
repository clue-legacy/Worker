<?php

/**
 * 
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Slave{
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
    private $sending = '';
    
    /**
     * whether to automatically send data when calling putPacket()
     * 
     * @var boolean
     */
    protected $autosend = true;
    
    /**
     * debugging on/of
     * 
     * @var boolean
     */
    protected $debug = false; // true;
    
    /**
     * protocol handler instance
     * 
     * @var Worker_Protocol
     */
    protected $protocol;
    
    /**
     * worker communicator
     * 
     * @var Worker_Communicator
     */
    protected $comm;
    
    /**
     * create new slave communicating with given process/command
     * 
     * @param string $cmd
     * @return Worker_Slave
     */
    public static function factoryProcess($cmd){
        return new Worker_Slave(new Worker_Communicator_Process($cmd));
    }
    
    /**
     * create new slave communicating via standard input and output (STDIN / STDOUT)
     * 
     * @return Worker_Slave
     * @uses Worker_Slave::setDebug() to disable debug (which corrupts stdout)
     * @uses Worker_Slave::setAutosend() to enable auto-sending STDOUT
     */
    public static function factoryStdio(){
        $slave = new Worker_Slave(new Worker_Communicator_Stdio());
        return $slave->setDebug(false)->setAutosend(true);
    }
    
    /**
     * create new slave communicating via given duplex stream resource
     * 
     * @param resource $stream
     * @return Worker_Slave
     */
    public static function factoryStream($stream){
        return new Worker_Slave(new Worker_Communicator_Stream($stream));
    }
    
    /**
     * instanciate new worker slave on given communicator
     * 
     * @param Worker_Communicator $comm
     * @uses Worker_Protocol::setMaxlength()
     * @uses Worker_Protocol::setDebug()
     */
    protected function __construct(Worker_Communicator $comm){
        $this->comm = $comm;
        
        $this->protocol = new Worker_Protocol();
        $this->protocol->setMaxlength(self::BUFFER_MAX)->setDebug($this->debug);
    }
    
    /**
     * decorate with interface for invoking remote methods (RPC interface)
     * 
     * @return Worker_Methodify
     */
    public function decorateMethods(){
        return new Worker_Methodify($this);
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
        $this->protocol->setDebug($toggle);
        return $this;
    }
    
    public function getDebug(){
        return $this->debug;
    }
    
    public function hasOutgoing(){
        return ($this->sending !== '');
    }
    
    /**
     * checks whether there is a finished packet in the incoming queue
     * 
     * @return boolean
     */
    public function hasPacket(){
        return $this->protocol->hasPacket();
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
        $this->sending .= $this->protocol->marshall($data);
        
        if(strlen($this->sending) > self::BUFFER_MAX){
            throw new Worker_Exception_Communication('Outgoing buffer size of '.Debug::param(strlen($this->sending)).' exceeds maximum of '.Debug::param(self::BUFFER_MAX));
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
        return $this->protocol->getPacket();
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
     * @uses Worker_Slave::streamSend()
     * @uses Worker_Slave::streamReceive()
     */
    public function getPacketWait($timeout=NULL){
        try{                                                                    // try to get packet once
            return $this->getPacket();
        }
        catch(Worker_Exception $e){ }
        
        $master = new Stream_Master_Standalone();
        $master->addEvent('clientWrite',function($client){
            $client->getNative()->streamSend();
        });
        $master->addEvent('clientRead',function($client) use ($master){
            $client = $client->getNative();
            $client->streamReceive(); // try to read data, may throw an exception
            
            try{
                $packet = $client->getPacket();                                 // try to get packet
            }
            catch(Exception $e){                                                // ignore errors in case packet is not complete
                return;
            }
            $master->stop($packet);
        });
        if($timeout !== NULL){
            $master->addEvent('timeout',function(){
                throw new Worker_Exception_Timeout('Timeout');
            })->setTimeout($timeout);
        }
        $master->addClient($this);
        return $master->start();
    }
    
    /**
     * get communicator
     * 
     * @return Worker_Communicator
     */
    public function getCommunicator(){
        return $this->comm;
    }
    
    /**
     * close slave streams
     *  
     * @return Worker_Slave this (chainable)
     * @uses Worker_Communicator::close()
     */
    public function close(){
        $this->comm->close();
        return $this;
    }
    
    /**
     * get stream resource to read from
     * 
     * @return resource
     * @uses Worker_Communicator::getStreamRead()
     */
    public function getStreamRead(){
        return $this->comm->getStreamRead();
    }
    
    /**
     * get stream resource to write to (should return NULL if there's no data to be written)
     * 
     * @return resource|NULL
     * @uses Worker_Communicator::getStreamWrite()
     */
    public function getStreamWrite(){
        if($this->sending === ''){
            return NULL;
        }
        return $this->comm->getStreamRead();
    }
    
    /**
     * actually receive from worker stream
     * 
     * @throws Worker_Exception on error or if buffer exceeds maximum size
     * @return Worker_Slave this (chainable)
     * @uses Worker_Slave::getPackets()
     * @uses Worker_Slave::onPacket() on each packet received
     */
    public function streamReceive(){
        $buffer = fread($this->getStreamRead(),self::BUFFER_CHUNK);
        if($buffer === false){
            throw new Worker_Exception_Communication('Unable to read data from stream');
        }
        if($buffer === ''){
            throw new Worker_Exception_Disconnect('No data read, stream closed?');
        }
        
        //if($this->debug) Debug::notice('[Received data '.Debug::param($buffer).']');
        $this->protocol->onData($buffer);
    }
    
    /**
     * handle all incoming packets
     * 
     * @return Worker_Slave $this (chainable)
     * @uses Worker_Slave::getPackets() to get all packets
     * @uses Worker_Slave::onPacket() for each packet
     */
    public function handlePackets(){
        foreach($this->getPackets() as $packet){
            $this->onPacket($packet);
        }
        return $this;
    }
    
    /**
     * called when new packet has been received
     * 
     * @param mixed $packet
     */
    protected function onPacket($packet){
        throw new Worker_Exception_Communication('Unknown incoming packet');
    }
    
    /**
     * actually send outgoing buffer to worker stream
     * 
     * @throws Worker_Exception on error
     * @return Worker_Slave this (chainable)
     */
    public function streamSend(){
        //if($this->debug) echo '[Sending '.Debug::param($this->sending).']';
        $bytes = fwrite($this->getStreamWrite(),$this->sending);
        if($bytes === false){
            throw new Worker_Exception_Communication('Unable to write data to stream');
        }
        if($bytes === 0){
            throw new Worker_Exception_Disconnect('Nothing sent, stream closed?');
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
     * start main loop, wait for packets, handle packets
     * 
     * @return mixed $data
     * @throws Worker_Exception on error
     * @uses Stream_Master_Standalone::addClient()
     * @uses Stream_Master_Standalone::start()
     * @uses Worker_Slave::streamSend()
     * @uses Worker_Slave::streamReceive()
     * @uses Worker_Slave::handlePackets()
     */
    public function start(){
        $master = new Stream_Master_Standalone();
        $master->addEvent('clientWrite',function($client){
            $client->getNative()->streamSend();
        });
        $master->addEvent('clientRead',function($client){
            $client = $client->getNative();
            $client->streamReceive();
            
            $client->handlePackets();
        });
        $master->addClient($this);
        $master->start();
    }
}
