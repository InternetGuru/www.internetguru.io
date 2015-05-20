<?php

class Logger {
  private $message;
  private $type;
  private $duration = null;
  const LOGGER_FATAL = "Fatal";
  const LOGGER_ERROR = "Error";
  const LOGGER_WARNING = "Warning";
  const LOGGER_INFO = "Info";
  const LOGGER_SUCCESS = "Success";

  function __construct($message, $type=null, $start_time=null, $cmsMsg = true, $ext="log") {
    if(!is_null($start_time))
      $this->duration = round((microtime(true) - $start_time)*1000)."ms";
    if(!in_array($type, array(
      self::LOGGER_FATAL,
      self::LOGGER_ERROR,
      self::LOGGER_WARNING,
      self::LOGGER_SUCCESS))) $type = self::LOGGER_INFO;
    if($cmsMsg && Cms::isSuperUser()) {
      Cms::addMessage("$message [".$this->getCaller()."]", $type, Cms::isForceFlash());
    }
    if(Cms::getLoggedUser() == "server") return;
    $this->message = $message;
    $this->type = $type;
    $this->log($ext);
  }

  private function getCaller() {
    $i = 2;
    $callers = debug_backtrace();
    $c = array();
    if(isset($callers[$i]['class'])) $c[] = $callers[$i]['class'];
    if(CMS_DEBUG && isset($callers[$i]['function'])) $c[] = $callers[$i]['function'];
    if(empty($c)) return "core";
    return implode(".", $c);
  }

  private function log($ext) {
    $logFile = LOG_FOLDER."/".date("Ymd").".$ext";
    if(isset($_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"]))
      $msg[] = $_SERVER["REMOTE_ADDR"].":".$_SERVER["REMOTE_PORT"];
    else
      $msg[] = "0.0.0.0:0000";
    $msg[] = is_null(Cms::getLoggedUser()) ? "unknown" : Cms::getLoggedUser(); // logged user
    $msg[] = date(DATE_ATOM); // http://php.net/manual/en/class.datetime.php
    if(isset($_SERVER["REQUEST_METHOD"], $_SERVER["REQUEST_URI"], $_SERVER["SERVER_PROTOCOL"]))
      $msg[] = '"'.$_SERVER["REQUEST_METHOD"]." ".$_SERVER["REQUEST_URI"]." ".$_SERVER["SERVER_PROTOCOL"].'"';
    else {
      $msg[] = '"UNKNOWN UNKNOWN UNKNOWN"';
    }
    $msg[] = http_response_code();
    $msg[] = $this->type;
    $msg[] = '"'.$this->message.'"';
    $msg[] = '['.$this->getCaller().']';
    $msg[] = $this->duration;
    error_log(implode(" ", $msg)."\n", 3, $logFile);
  }

}

?>