<?php

/**
 * lightweight worker exception
 * 
 * @author mE
 */
class Worker_Exception extends Exception { }

/**
 * lightweight exception thrown in case of a timeout
 * 
 * @author mE
 */
class Worker_Timeout_Exception extends Worker_Exception { }

/**
 * lightweight exception thrown when a stream closes
 * 
 * @author mE
 */
class Worker_Disconnect_Exception extends Worker_Exception { }
