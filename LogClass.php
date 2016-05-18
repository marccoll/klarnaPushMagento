<?php

/**
 * Log generator
 */
class Log
{
  function add($msg){
     // open file
     $fd = fopen('./log/klarna.log', "a");
     // append date/time to message
     $str = "[" . date("Y/m/d h:i:s", mktime()) . "] " . $msg;
     // write string
     fwrite($fd, $str . "\n");
     // close file
     fclose($fd);
   }
}
