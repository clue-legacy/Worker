<?php

use Evenement\EventEmitter;

/**
 * represent a single connection to another worker instance
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Slave extends Stream_Master_Client{
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
    
    protected $events;
    
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
     */
    public static function factoryStdio(){
        $slave = new Worker_Slave(new Worker_Communicator_Stdio());
        return $slave->setDebug(false);
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
        
        $this->events = new EventEmitter();
        
        $this->methodify = new Worker_Methodify($this);
    }
    
    /**
     * decorate with interface for invoking remote methods (RPC interface)
     * 
     * @return Worker_Methodify
     */
    public function decorateMethods(){
        return $this->methodify;
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
        $this->methodify->setDebug($toggle);
        return $this;
    }
    
    public function addEvent($name,$fn){
        $this->events->on($name,$fn);
    }
    
    public function addEventOnce($name,$fn){
        $this->events->once($name,$fn);
    }
    
    /**
     * get debugging toggle state
     * 
     * @return boolean
     * @see Worker_Slave::setDebug()
     */
    public function getDebug(){
        return $this->debug;
    }
    
    /**
     * returns whether this slave has any outgoing data left to be sent
     * 
     * @return boolean
     */
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
     * check whethere there is a finished packet in the incoming queue (or wait for once to become available)
     * 
     * this method will block until a packet is available or timeout is reached
     * 
     * @param float|NULL $timeoutIn (optional) timeout in seconds, NULL=wait forever
     * @return boolean 
     * @throws Worker_Exception on error
     * @uses Worker_Slave::hasPacket() to return immediately if a packet is ready
     * @uses Worker_Adapter_Packet
     * @uses Stream_Master_Standalone::addClient()
     * @uses Stream_Master_Standalone::setTimeoutIn()
     * @uses Stream_Master_Standalone::start()
     * @uses Worker_Slave::hasPacket() to return result
     */
    public function hasPacketWait($timeoutIn=NULL){
        if($this->hasPacket()){                                                 // check for packet once before setting up network communication
            return true;
        }
        
        $master = new Stream_Master_Standalone();
        $master->addClient(new Worker_Adapter_Packet($this));
        if($timeoutIn !== NULL){
            $master->setTimeoutIn($timeoutIn);
        }
        $master->start();
        return $this->hasPacket();
    }
    
    /**
     * add new packet to outgoing queue
     * 
     * @param mixed $data
     * @throws Worker_Exception if buffer exceeds maximum size
     * @return Worker_Slave $this (chainable)
     * @uses Worker_Protocol::marshall()
     */
    public function putPacket($data){
        $packet = $this->protocol->marshall($data);
        if(strlen($this->sending.$packet) > self::BUFFER_MAX){
            throw new Worker_Exception_Communication('Adding '.strlen($packet).' byte(s) "'.$packet.'" to outgoing buffer exceeds maximum of '.self::BUFFER_MAX.' bytes');
        }
        $this->sending .= $packet;
        return $this;
    }
    
    public function putBacks(array $packets){
        $this->protocol->putBacks($packets);
        return $this;
    }
    
    /**
     * add packet to outgoing queue and wait for buffer to be sent
     * 
     * this method will block until the outgoing buffer has been sent to the remote end or timeout is reached
     * 
     * @param mixed      $data      data to send to the remote side
     * @param float|NULL $timeoutIn (optional) timeout in seconds, NULL=wait forever
     * @return Worker_Slave $this (chainable)
     * @throws Worker_Exception on error
     * @uses Worker_Slave::putPacket() to add given data to outgoing queue
     * @uses Worker_Adapter_Flush
     * @uses Stream_Master_Standalone::addClient()
     * @uses Stream_Master_Standalone::setTimeoutIn()
     * @uses Stream_Master_Standalone::start()
     * @todo support sending packets that exceed outgoing buffer size by sending in chunks
     */
    public function putPacketWait($data,$timeoutIn=NULL){
        $this->putPacket($data);
        
        $master = new Stream_Master_Standalone();
        $master->addClient(new Worker_Adapter_Flush($this));
        if($timeoutIn !== NULL){
            $master->setTimeoutIn($timeoutIn);
        }
        $master->start();
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
     * @param float|NULL $timeoutIn (optional) timeout in seconds, NULL=wait forever
     * @return mixed $data
     * @throws Worker_Exception_Timeout when timeout is given and exceeded
     * @throws Worker_Exception on error
     * @uses Worker_Slave::hasPacketWait() to check (and wait) for available packet 
     * @uses Worker_Slave::getPacket() to actually return packet
     */
    public function getPacketWait($timeoutIn=NULL){
        if(!$this->hasPacketWait($timeoutIn)){
            throw new Worker_Exception_Timeout('Communication with remote end timed out');
        }
        return $this->getPacket();
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
     * @return Worker_Slave $this (chainable)
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
     * get stream resource to write to 
     * 
     * returns NULL if there's no data to be written
     * 
     * @return resource|NULL
     * @uses Worker_Communicator::getStreamWrite()
     */
    public function getStreamWrite(){
        if($this->sending === ''){
            return NULL;
        }
        return $this->comm->getStreamWrite();
    }
    
    /**
     * actually receive from worker stream
     * 
     * @throws Worker_Exception on error or if buffer exceeds maximum size
     * @return Worker_Slave $this (chainable)
     * @uses Worker_Communicator::getStreamRead()
     * @uses Worker_Slave::getPackets()
     */
    public function streamReceive(){
        $stream = $this->comm->getStreamRead();
        if($this->debug) echo '[read';
        $meta = stream_get_meta_data($stream);
        $len = $meta['unread_bytes'];
        if($len === 0){ // length unknown (usual case), read up to one chunk from incoming streams
            $len = self::BUFFER_CHUNK;
            if($this->debug) echo ' up to one chunk';
        }else if($len > self::BUFFER_CHUNK){ // buffer length known and bigger than chunk, read chunk from buffer
            $len = self::BUFFER_CHUNK;
            if($this->debug) echo ' exactly one buffered chunk';
        }else{  // small buffer remaining, read EXACTLY remaining buffer. execeeding buffer length WILL block fread() when no more data is incoming
            if($this->debug) echo ' remaining buffer';
        }
        if($this->debug) echo ' from '.$stream.'...';
        $buffer = fread($stream,$len);
        if($buffer === false){
            if($this->debug) echo ' ERROR]';
            throw new Worker_Exception_Communication('Unable to read data from stream');
        }
        if($buffer === ''){
            if($this->debug) echo ' CLOSED]';
            throw new Worker_Exception_Disconnect('No data read, stream closed?');
        }
        
        if($this->debug) echo ' OK '.strlen($buffer).' byte(s): "'.$buffer.'"]';
        
        //if($this->debug) Debug::notice('[Received data '.Debug::param($buffer).']');
        $this->protocol->onData($buffer);
        return $this;
    }
    
    /**
     * handle all incoming packets
     * 
     * @return Worker_Slave $this (chainable)
     * @throws Worker_Exception if either packet can not be handled
     * @uses Worker_Slave::getPackets() to get all packets
     * @uses EventEmitter::emit() for each packet
     */
    public function handlePackets(){
        foreach($this->getPackets() as $packet){
            $this->events->emit('packet',array($packet,$this));
        }
        return $this;
    }
    
    /**
     * actually send outgoing buffer to worker stream
     * 
     * @throws Worker_Exception on error
     * @return Worker_Slave this (chainable)
     * @uses Worker_Communicator::getStreamWrite()
     */
    public function streamSend(){
        $len = strlen($this->sending);
        $stream = $this->comm->getStreamWrite();
        if($this->debug) echo '[send '.$len.' byte(s) "'.$this->sending.'" to '.$stream.'...';
        //if($this->debug) echo '[Sending '.Debug::param($this->sending).']';
        $bytes = @fwrite($stream,$this->sending); // suppress errors in in case of a broken pipe (connection closed by remote side)
        if($bytes === false){
            if($this->debug) echo ' ERROR]';
            throw new Worker_Exception_Communication('Unable to write data to stream');
        }
        if($bytes === 0){
            if($this->debug) echo ' CLOSED]';
            throw new Worker_Exception_Disconnect('Nothing sent, stream closed?');
        }
        if($bytes == $len){
            $this->sending = '';
            if($this->debug) echo ' OK]';
        }else{
            $this->sending = substr($this->sending,$bytes);
            if($this->debug) echo ' OK - sent '.$bytes.' byte(s) - remaining '.strlen($this->sending).' byte(s): "'.$this->sending.'"]';
        }
        return $this;
    }
    
    /**
     * start main loop, wait for packets, handle packets
     * 
     * will block until connection is closed
     * 
     * @return Worker_Slave $this (chainable)
     * @throws Worker_Exception on error
     * @uses Worker_Adapter_Stream
     * @uses Stream_Master_Standalone::addClient()
     * @uses Stream_Master_Standalone::start()
     */
    public function start(){
        $master = new Stream_Master_Standalone();
        $master->addClient(new Worker_Adapter_Stream($this));
        $master->start();
        return $this;
    }
}
