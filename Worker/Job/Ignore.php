<?php

/**
 * ignore job results
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
 */
class Worker_Job_Ignore extends Worker_Job{
    /**
     * perform current job on given methods object
     * 
     * do not care about any output / return values / exceptions
     * 
     * @param Worker_Methods $methods methods object to perform actual call on
     */
    public function call($methods){
        try{
            $methods->call($this->callee,$this->arguments);
        }
        catch(Exception $e){ }
    }
}
