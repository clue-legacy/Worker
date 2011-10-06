<?php

/**
 * adapter used to wrap slave communication to flush outgoing buffer to remote end (or network buffers)
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Adapter_Flush extends Worker_Adapter_Packet{
    /**
     * called when it's save to send to the remote end
     * 
     * try to send data (which may fail if the connection is dead),
     * stop processing if outgoing buffer is empty afterwards
     * 
     * @uses Worker_Adapter_Packet::onCanWrite() to actually send data
     * @uses Worker_Slave::hasOutgoing() to check if outgoing buffer is empty afterwards
     * @uses Stream_Master_Standalone::stop()
     */
    public function onCanWrite($master){
        parent::onCanWrite($master);                                            // actually send data (or fail with an exception)
        
        if(!$this->slave->hasOutgoing()){                                       // no more outgoing data
            $master->stop();                                                    // we're finished
        }
    }
    
    /**
     * do NOT provide a stream to read from
     * 
     * this will avoid overflowing incoming buffer while waiting for outgoing buffer to be sent
     * 
     * @return NULL
     * @todo provide a read stream as long as incoming buffer will not be exceeded
     */
    public function getStreamRead(){
        return NULL;
    }
}
