<?php

interface ResourceInterface {
  public static function isSupportedRequest();
  public static function handleRequest();
}

?>