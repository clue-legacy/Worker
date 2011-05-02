<?php

/**
 * mirror output to given string reference
 * 
 * output (echo, print, etc.) will work as-is and all contents will be
 * mirrored to the referenced string as long as and instance of the mirror
 * lives.
 * 
 * @author mE
 */
class Output_Mirror{
    /**
     * reference to output string
     * 
     * @var string
     */
    private $ref;
    
    /**
     * instanciate new output mirror to given string reference
     * 
     * @param string $ref
     * @uses ob_start() to start smallest possible output buffer (flush data automatically ASAP)
     */
    public function __construct(&$ref){
        $this->ref =& $ref;
        if(ob_start(array($this,'obFlush'),2) === false){ // start smallest possible output buffer (chunksize=2 as 1 is reserved)
            throw new Exception('Unable to start output buffer');
        }
    }
    
    /**
     * flush new output chunk (this callback MUST NOT be called manually!)
     * 
     * @param string $chunk
     * @return boolean
     */
    public function obFlush($chunk){
        $this->ref .= $chunk;
        return false;                                                           // display original output
    }
    
    /**
     * destruct output mirror (leave scope, clear remaining buffer)
     * 
     * @uses ob_end_flush() to flush remaining buffer
     */
    public function __destruct(){
        if(ob_end_flush() === false){
            throw new Exception('Unable to end output buffer');
        }
    }
    
    /**
     * get string contents of output mirror
     * 
     * @return string
     */
    public function __toString(){
        return $this->ref;
    }
}
