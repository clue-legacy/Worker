<?php

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
