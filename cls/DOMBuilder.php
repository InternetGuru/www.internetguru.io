<?php

/**
 * Create DOM from XML file and update elements from adm/usr directories.
 * Add by default; empty element to delete all elements with same nodeName.
 * Respect readonly attribute when applying user file.
 * Default XML file is required (plugins do not have to use Config at all).
 * Elements with attribute subdom will be applied only when matched.
 *
 * @param: String plugin (optional)
 * @return: DOMDocument
 * @throws: Exception when files don't exist or are corrupted/empty
 */
class DOMBuilder {

  const DEBUG = false;
  private $backupStrategy = null;
  private $doc; // DOMDocument (HTMLPlus)
  private $plugin;
  private $replace; // bool
  private $filename;

  public function __construct() {}

  public function setBackupStrategy(BackupStrategyInterface $backupStrategy) {
    $this->backupStrategy = $backupStrategy;
  }

  public function buildDOMPlus($filePath,$replace=false,$user=true) {
    $this->doc = new DOMDocumentPlus();
    $this->build($filePath,$replace,$user);
    return $this->doc;
  }

  public function buildHTMLPlus($filePath,$replace=true,$user=true) {
    $this->doc = new HTMLPlus();
    $this->build($filePath,$replace,$user);
    return $this->doc;
  }

  private function build($filePath,$replace,$user) {

    if($replace) {
      $this->loadDOM(findFile($filePath,$user),$this->doc);
      if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
      return;
    }

    $this->loadDOM(findFile($filePath,false,false),$this->doc);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";

    $f = ADMIN_FOLDER . "/$filePath";
    if(is_file($f)) $this->updateDOM($f,true);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";

    if(!$user) return;

    $f = USER_FOLDER . "/$filePath";
    if(is_file($f)) $this->updateDOM($f);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
  }

  private function loadDOM($filePath, DOMDocumentPlus $doc, $backup=true) {
    try {
      // load
      if(!@$doc->load($filePath))
        throw new Exception("Unable to load DOM from file '$filePath'");
      // validate if htmlplus
      try {
        if($doc instanceof HTMLPlus) $doc->validate();
      } catch(Exception $e) {
        $doc->validate(true);
        saveRewrite($filePath, $doc->saveXML());
      }
    } catch(Exception $e) {
      // restore file if backupstrategy && $backup && !atLocalhost
      if(!isAtLocalhost() && !is_null($this->backupStrategy) && $backup)
        #$this->backupStrategy->restoreNewestBackup($filePath);
        $filePath = $this->backupStrategy->getNewestBackupFilePath($filePath);
      else throw $e;
      // loadDOM(false)
      $this->loadDOM($filePath,$doc,false);
    }
    // do backup if $backup
    if(!is_null($this->backupStrategy) && $backup)
      $this->backupStrategy->doBackup($filePath);
  }

  /**
   * Load XML file into DOMDocument using backup/restore
   * Respect subdom attribute
   * @param  string      $filePath File to be loaded into document
   * @return void
   * @throws Exception   if unable to load XML file incl. backup file
   */
  private function updateDOM($filePath,$ignoreReadonly=false) {
    $doc = new DOMDocumentPlus();
    $this->loadDOM($filePath,$doc);
    // create root element if not exists
    if(is_null($this->doc->documentElement)) {
      $this->doc->appendChild($this->doc->importNode($doc->documentElement));
    }
    foreach($doc->documentElement->childNodes as $n) {
      if(get_class($n) != "DOMElement") continue;
      if($this->ignoreElement($n)) continue;
      if($n->hasAttribute("id")) {
        $sameIdElement = $this->doc->getElementById($n->getAttribute("id"));
        if(is_null($sameIdElement)) {
          $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
          continue;
        }
        if($sameIdElement->nodeName != $n->nodeName)
          throw new Exception ("Id conflict with " . $n->nodeName);
        if(!$ignoreReadonly && $sameIdElement->hasAttribute("readonly")) continue;
        $this->doc->documentElement->replaceChild($this->doc->importNode($n,true),$sameIdElement);
      } elseif($n->nodeValue == "") {
        $remove = array();
        foreach($this->doc->getElementsByTagName($n->nodeName) as $d) {
          if($ignoreReadonly || !$d->hasAttribute("readonly")) $remove[] = $d;
        }
        #if(!count($remove)) {
        #  $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
        #  continue;
        #}
        foreach($remove as $d) $d->parentNode->removeChild($d);
      } else {
        // if empty && readonly => user cannot modify
        foreach($this->doc->getElementsByTagName($n->nodeName) as $d) {
          if(!$ignoreReadonly && $d->hasAttribute("readonly") && $d->nodeValue == "") return;
        }
        $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
      }
    }
  }

  #private function getElementById($id) {
  #  $xpath = new DOMXPath($this->doc);
  #  $q = $xpath->query("//*[@id='$id']");
  #  if($q->length == 0) return null;
  #  return $q->item(0);
  #}

  private function ignoreElement(DOMElement $e) {
    if(!$e->hasAttribute("subdom")) return false;
    return !in_array($this->getSubdom(),explode(" ",$e->getAttribute("subdom")));
  }

  private function getSubdom() {
    if(isAtLocalhost()) return "localhost";
    // eg => /subdom/private/server.php
    $d = explode("/",$_SERVER["SCRIPT_NAME"]);
    return $d[2];
  }

}

interface BackupStrategyInterface {
    public function doBackup($filePath);
    #public function restoreNewestBackup($filePath);
    public function getNewestBackupFilePath($filePath);
}

?>
