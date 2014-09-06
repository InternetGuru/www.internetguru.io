<?php

#TODO: singleton contentXpath, contentFullXpath
#TODO: outputStrategy interface
#TODO: default outputStrategy (ignore methods)

class Cms {

  private $domBuilder; // DOMBuilder
  private $config; // DOMDocument
  private $contentFull = null; // HTMLPlus
  private $content = null; // HTMLPlus
  private $outputStrategy = null; // OutputStrategyInterface
  private $link = ".";
  private $plugins = null; // SplSubject
  private $titleQueries = array("/body/h");

  function __construct() {
    $this->domBuilder = new DOMBuilder();
    if(isset($_GET["page"])) $this->link = $_GET["page"];
    if(!strlen(trim($this->link))) $this->link = ".";
    #error_log("CMS created:0",0);
    #error_log("CMS created:3",3,"aaa.log");
  }

  public function setPlugins(SplSubject $p) {
    $this->plugins = $p;
  }

  public function getLink() {
    return $this->link;
  }

  public function getDomBuilder() {
    return $this->domBuilder;
  }

  public function init() {
    $this->config = $this->domBuilder->buildDOMPlus("Cms.xml");
    $er = $this->config->getElementsByTagName("error_reporting")->item(0)->nodeValue;
    if(@constant($er) === null) // keep outside if to check value
      throw new Exception("Undefined constatnt '$er' used in error_reporting");
    error_reporting(constant($er));
    $er = $this->config->getElementsByTagName("display_errors")->item(0)->nodeValue;
    if(ini_set("display_errors", 1) === false)
      throw new Exception("Unable to set display_errors to value '$er'");
    $tz = $this->config->getElementsByTagName("timezone")->item(0)->nodeValue;
    if(!date_default_timezone_set($tz))
      throw new Exception("Unable to set date_default_timezone to value '$er'");
    $this->loadContent();
  }

  public function getTitle() {
    $title = array();
    $xpath = new DOMXPath($this->contentFull);
    foreach($this->titleQueries as $q) {
      $r = $xpath->query($q)->item(0);
      if($r->hasAttribute("short") && count($this->titleQueries) > 1) $title[] = $r->getAttribute("short");
      else $title[] = $r->nodeValue;
    }
    return implode(" - ",$title);
  }

  public function getDescription() {
    $query = "/body/desc";
    foreach($this->plugins->getContentStrategies() as $cs) {
      $query = $cs->getDescription($query);
    }
    $xpath = new DOMXPath($this->contentFull);
    return $xpath->query($query)->item(0)->nodeValue;
  }

  public function getLanguage() {
    if(!is_null($this->content)) $h = $this->content;
    else $h = $this->contentFull;
    return $h->getElementsByTagName("body")->item(0)->getAttribute("xml:lang");
  }

  public function getConfig() {
    return $this->config;
  }

  public function getContentFull() {
    return $this->contentFull;
  }

  public function buildContent() {
    if(is_null($this->contentFull)) throw new Exception("Content not set");
    if(!is_null($this->content)) throw new Exception("Should not run twice");
    $this->content = clone $this->contentFull;
    $contentStrategies = $this->plugins->getContentStrategies();
    foreach($contentStrategies as $cs) {
      $this->titleQueries = $cs->getTitle($this->titleQueries);
    }
    try {
      $cs = null;
      foreach($contentStrategies as $cs) {
        $c = $cs->getContent($this->content);
        #echo $c->saveXML(); die();
        if(!($c instanceof HTMLPlus))
          throw new Exception("Content must be an instance of HTMLPlus");
        $c->validatePlus();
        $this->content = $c;
      }
    } catch (Exception $e) {
      #var_dump($cs);
      #echo $this->content->saveXML();
      #echo $c->saveXML();
      throw new Exception($e->getMessage() . " (" . get_class($cs) . ")");
    }
  }

/*  public function setContentStrategy(ContentStrategyInterface $strategy, $priority=10) {
    $s = get_class($strategy);
    $this->contentStrategy[$s] = $strategy;
    $this->contentStrategyPriority[$s] = $priority;
  }*/

/*  public function setContentStrategyPriority(ContentStrategyInterface $strategy, $priority) {
    $s = get_class($strategy);
    if(!array_key_exists($s,$this->contentStrategy))
      throw new Exception("Strategy '$s' not attached");
    $this->contentStrategyPriority[$s] = $priority;
  }*/

  public function setOutputStrategy(OutputStrategyInterface $strategy) {
    $this->outputStrategy = $strategy;
  }

  private function loadContent() {
    $this->contentFull = $this->domBuilder->buildHTMLPlus("Content.html");
  }

  public function getOutput() {
    if(is_null($this->content)) throw new Exception("Content not set");
    if(!is_null($this->outputStrategy)) return $this->outputStrategy->getOutput($this->content);
    return $this->content->saveXML();
  }

  public function getOutputStrategy() {
    return $this->outputStrategy;
  }

}

interface OutputStrategyInterface {
  public function getOutput(HTMLPlus $content);
}

?>
