<?php

/**
 * Worker slave using STDIN/STDOUT to communicate with master
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Slave_Std extends Worker_Slave{
    public function __construct(){
        parent::__construct();
        $this->setDebug(false)->setAutosend(true);
    }
    
    public function getStreamRead(){
        return STDIN;
    }
    
    public function getStreamWrite(){
        return $this->hasOutgoing() ? STDOUT : NULL;
    }
}