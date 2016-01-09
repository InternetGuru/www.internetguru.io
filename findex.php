<?php

try {
  include("init.php");

  foreach(scandir(PLUGINS_FOLDER) as $plugin) {
    if(strpos($plugin, ".") === 0) continue;
    if(!is_dir(PLUGINS_FOLDER."/$plugin")) continue;
    if(is_dir(PLUGINS_FOLDER."/.$plugin")) continue;
    if(!in_array("ResourceInterface", class_implements($plugin))) continue;
    if(!$plugin::isSupportedRequest()) continue;
    $plugin::handleRequest();
  }
  throw new Exception(_("Unsupported request"), 415);

} catch(Exception $e) {

  $errno = $e->getCode() ? $e->getCode() : 500;
  $m = $e->getMessage();
  if(CMS_DEBUG) $m = sprintf("%s in %s on line %s", $m, $e->getFile(), $e->getLine());
  Logger::log($m, Logger::LOGGER_FATAL, null, false);
  if(class_exists("ErrorPage")) new ErrorPage($m, $errno);

  http_response_code($errno);
  echo $m;

}

?>