<?php

/**
 * adapter used to wrap streaming slave communication to match with Stream_Master_Client
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Adapter_Stream extends Worker_Adapter_Packet{
    /**
     * called when it's save to read from the remote end
     * 
     * try to read data (which may fail if the connection is dead),
     * then try to handle all incoming packets
     * 
     * @uses Worker_Slave::streamReceive()
     * @uses Worker_Slave::handlePackets()
     */
    public function onCanRead($master){
        try{
            $this->slave->streamReceive()->handlePackets();
        }
        catch(Worker_Exception_Disconnect $e){
            $this->onClose($master);
        }
    }
    
    /**
     * called when the connection is dead: just stop master
     * 
     * @uses Worker_Slave::close()
     * @uses Stream_Master_Standalone::stop()
     */
    public function onClose($master){
        $this->slave->close();
        $master->stop();
    }
}
