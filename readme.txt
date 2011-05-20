== Description ==
Worker is a simple and unobstrusive RPC (remote procedure call) and IPC
(inter process communication) library which tries to stay out of your way as
much as possible. It's entirely written in pure PHP

Author:   Christian Lück <christian@lueck.tv>
Homepage: https://github.com/clue/Worker
License:  MIT-style license

== Requirements / Dependencies ==
* PHP 5.3+
* Stream_Master [https://github.com/clue/Stream_Master]
  Handle multiple worker streams
* EventEmitter [https://github.com/clue/EventEmitter]
  Provides event-driven paradigm

Recommended:
* Process [https://github.com/clue/Process]
  Required for fork to multiple processes (Worker_Slave_Process)

Optional:
* Output_Mirror [https://github.com/clue/Output_Mirror]
  When installed, Worker_Job takes advantage of it in that function output is
  immediately mirrored. Otherwise, whole function output gets buffered using
  PHP's output buffering functions. 