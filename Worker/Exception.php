<?php

/**
 * lightweight worker exception
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Worker
 * @version v0.0.1
 * @link https://github.com/clue/Worker
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
