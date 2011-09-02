<?php

/**
 * adapter used to wrap slave communication to receive a single packet to match with Stream_Master_Client
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Adapter_Packet extends Stream_Master_Client{
    /**
     * 
     * @var Worker_Slave
     */
    protected $slave;
    
    /**
     * instanciate new adapter for given slave
     * 
     * @param Worker_Slave $slave
     */
    public function __construct(Worker_Slave $slave){
        $this->slave = $slave;
    }
    
    /**
     * called when it's save to read from the remote end
     * 
     * try to read data (which may fail if the connection is dead),
     * then try to stop master if a new packet is ready
     * 
     * @uses Worker_Slave::streamReceive()
     * @uses Worker_Slave::getPacket() to check when a packet is finished
     * @uses Stream_Master_Standalone::stop()
     */
    public function onCanRead($master){
        try{
            $this->slave->streamReceive();
        }
        catch(Worker_Exception_Disconnect $e){
            $this->onClose($master);
        }
        
        try{
            $packet = $this->slave->getPacket();                                // try to get packet
        }
        catch(Exception $e){                                                    // ignore errors in case packet is not complete
            return;
        }
        $master->stop($packet);
    }
    
    /**
     * called when it's save to send to the remote end
     * 
     * try to send data (which may fail if the connection is dead)
     * 
     * @uses Worker_Slave::streamSend()
     */
    public function onCanWrite($master){
        try{
            $this->slave->streamSend(); // try to send (may throw an exception and trigger onClose())
        }
        catch(Worker_Exception_Disconnect $e){
            $this->onClose($master);
        }
    }
    
    /**
     * called when the connection is dead
     * 
     * we're still waiting for a packet, so this is a fatal error
     * 
     * @throws Worker_Exception_Disconnect
     * @uses Worker_Slave::close()
     */
    public function onClose($master){
        $this->slave->close();
        throw new Worker_Exception_Disconnect('Connection lost');
    }
    
    public function getStreamRead(){
        return $this->slave->getStreamRead();
    }
    
    public function getStreamWrite(){
        return $this->slave->getStreamWrite();
    }
}

