<?php

class Agregator extends Plugin implements SplObserver {
  private $files = array();  // filePath => fileInfo(?)
  private $docinfo = array();
  private $currentDoc = null;
  private $currentSubdir = null;
  private $currentFilepath = null;
  private $edit;
  private $cfg;
  private static $sortKey;
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    if(self::DEBUG) new Logger("DEBUG");
    $s->setPriority($this, 2);
    $this->edit = _("Edit");
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
    $this->cfg = $this->getDOMPlus();
    $curLink = getCurLink();
    try {
      mkdir_plus(ADMIN_FOLDER."/".$this->pluginDir);
      mkdir_plus(USER_FOLDER."/".$this->pluginDir);
      $list = array();
      $this->createList(USER_FOLDER."/".$this->pluginDir, $list);
      $this->createList(ADMIN_FOLDER."/".$this->pluginDir, $list);
      foreach($list as $subDir => $files) {
        $this->createHtmlVar($subDir, $files);
      }
    } catch(Exception $e) {
      new Logger($e->getMessage(), Logger::LOGGER_WARNING);
      return;
    }
    #$filesList = $this->createList(FILES_FOLDER);
    #$this->createFilesVar(FILES_FOLDER);
    #$this->createImgVar(FILES_FOLDER);
    if(is_null($this->currentDoc)) return;
    Cms::getOutputStrategy()->addTransformation($this->pluginDir."/Agregator.xsl");
    $this->insertDocInfo($this->currentDoc);
    $this->insertContent($this->currentDoc, $this->currentSubdir);
  }

  private function insertDocInfo(HTMLPlus $doc) {
    $vars = array();
    foreach($this->cfg->getElementsByTagName("var") as $var) {
      $vars[$var->getAttribute("id")] = $var;
    }
    foreach($doc->getElementsByTagName("h") as $h) {
      $ul = $this->createDocInfo($h, $vars);
      if(!$ul->childNodes->length) continue;
      $ul->processVariables($this->docinfo, array(), true);
      if($h->parentNode->nodeName == "body") {
        $wrapper = $doc->createElement("var");
        $wrapper->appendChild($ul);
        Cms::setVariable("docinfo", $wrapper);
        continue;
      }
      $e = $h->nextElement;
      while(!is_null($e)) {
        if($e->nodeName == "h") break;
        $e = $e->nextElement;
      }
      if(is_null($e)) $h->parentNode->appendChild($ul);
      else $h->parentNode->insertBefore($ul, $e);
    }
  }

  private function createDocInfo(DOMElementPlus $h, Array $vars) {
    $doc = $h->ownerDocument;
    $ul = $doc->createElement("ul");
    if($h->parentNode->nodeName == "body") {
      $ul->setAttribute("class", "docinfo nomultiple global");
      $li = $ul->appendChild($doc->createElement("li"));
      // global author & creation
      $li->setAttribute("class", "creation");
      foreach($vars["creation"]->childNodes as $n) {
        $li->appendChild($doc->importNode($n, true));
      }
      // global modification
      if(substr($this->docinfo["ctime"], 0, 10) != substr($this->docinfo["mtime"], 0, 10)) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "modified");
        foreach($vars["modified"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // global responsibility
      if($h->hasAttribute("resp")) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "responsible");
        foreach($vars["responsible"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // edit link
      if(Cms::isSuperUser()) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "edit");
        $a = $li->appendChild($doc->createElement("a", $this->edit));
        $a->setAttribute("href", "?Admin=".$this->currentFilepath);
        $a->setAttribute("title", $this->currentFilepath);
      }
    } else {
      $ul->setAttribute("class", "docinfo nomultiple partial");
      $partinfo = array();
      // local author (?)
      // local responsibility (?)
      // local creation
      if($h->hasAttribute("ctime") && substr($this->docinfo["ctime"], 0, 10) != substr($h->getAttribute("ctime"), 0, 10)) {
        $partinfo["ctime"] = $h->getAttribute("ctime");
        $li = $ul->appendChild($doc->createElement("li"));
        foreach($vars["part_created"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // local modification
      if($h->hasAttribute("mtime") && substr($this->docinfo["mtime"], 0, 10) != substr($h->getAttribute("mtime"), 0, 10)) {
        $partinfo["mtime"] = $h->getAttribute("mtime");
        $li = $ul->appendChild($doc->createElement("li"));
        foreach($vars["part_modified"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      $ul->processVariables($partinfo, array(), true);
    }
    return $ul;
  }

  private function insertContent(HTMLPlus $doc, $subDir) {
    $dest = Cms::getContentFull()->getElementById($subDir, "link");
    if(is_null($dest)) $dest = Cms::getContentFull()->documentElement->firstElement->nextElement;
    while($dest->nodeName != "section") {
      if(is_null($dest->nextElement)) {
        $dest = $dest->parentNode->appendChild($dest->ownerDocument->createElement("section"));
        break;
      }
      if($dest->nextElement->nodeName == "h") {
        $dest = $dest->parentNode->insertBefore($dest->ownerDocument->createElement("section"), $dest->nextElement);
        break;
      }
      $dest = $dest->nextElement;
    }
    foreach($doc->documentElement->attributes as $a) {
      if($a->nodeName == "ns") continue;
      $dest->setAttribute($a->nodeName, $a->nodeValue);
    }
    foreach($doc->documentElement->childElementsArray as $e) {
      $dest->appendChild($dest->ownerDocument->importNode($e, true));
    }
  }

  private function createList($rootDir, Array &$list, $subDir=null) {
    if(!is_dir($rootDir)) return;
    if(!is_null($subDir) && isset($list[$subDir])) return;
    $workingDir = is_null($subDir) ? $rootDir : "$rootDir/$subDir";
    foreach(scandir($workingDir) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(is_dir("$workingDir/$f")) {
        $this->createList($rootDir, $list, is_null($subDir) ? $f : "$subDir/$f");
        continue;
      }
      if(is_file("$workingDir/.$f")) continue;
      $list[$subDir][] = $f;
    }
  }

  private function createImgVar($rootDir) {
    foreach($this->files as $subDir => $null) {
      $workingDir = $subDir == "" ? $rootDir : "$rootDir/$subDir";
      $doc = new DOMDocumentPlus();
      $root = $doc->appendChild($doc->createElement("root"));
      $ol = $root->appendChild($doc->createElement("ol"));
      $found = false;
      foreach($this->files[$subDir] as $f => $null) {
        $mime = getFileMime("$workingDir/$f");
        if(strpos($mime, "image/") !== 0) continue;
        $li = $ol->appendChild($doc->createElement("li"));
        $a = $li->appendChild($doc->createElement("a"));
        $href = $subDir == "" ? $f : "$subDir/$f";
        $a->setAttribute("href", "/$href");
        $o = $a->appendChild($doc->createElement("object"));
        $o->setAttribute("data", "/$href?thumb");
        $o->setAttribute("type", $mime);
        $o->nodeValue = $href;
        $found = true;
      }
      if(!$found) continue;
      Cms::setVariable("img".($subDir == "" ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
  }

  private function createFilesVar($rootDir) {
    foreach($this->files as $subDir => $null) {
      $workingDir = $subDir == "" ? $rootDir : "$rootDir/$subDir";
      $doc = new DOMDocumentPlus();
      $root = $doc->appendChild($doc->createElement("root"));
      $ol = $root->appendChild($doc->createElement("ol"));
      foreach($this->files[$subDir] as $f => $null) {
        $li = $ol->appendChild($doc->createElement("li"));
        $a = $li->appendChild($doc->createElement("a"));
        $href = $subDir == "" ? $f : "$subDir/$f";
        $a->setAttribute("href", "/$href");
        $a->nodeValue = $href;
      }
      Cms::setVariable("files".($subDir == "" ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
  }

  private function createHtmlVar($subDir, Array $files) {
    $vars = array();
    $useCache = true;
    $cacheKey = HOST."/".get_class($this)."/".Cms::isSuperUser()."/$subDir";
    if(!$this->isValidCached($cacheKey, count($files))) {
      $this->storeCache($cacheKey, count($files), $subDir);
      $useCache = false;
    }
    foreach($files as $fileName) {
      if(pathinfo($fileName, PATHINFO_EXTENSION) != "html") continue;
      if(strlen($subDir)) $fileName = "$subDir/$fileName";
      $file = $this->pluginDir."/$fileName";
      $filePath = USER_FOLDER."/".$file;
      if(!is_file($filePath)) $filePath = ADMIN_FOLDER."/".$file;
      try {
        $doc = DOMBuilder::buildHTMLPlus($filePath);
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_WARNING);
        continue;
      }
      $vars[$filePath] = $this->getHTMLVariables($doc, $file);
      foreach($doc->getElementsByTagName("h") as $h) {
        if(!$h->hasAttribute("link")) continue;
        if($h->getAttribute("link") != getCurLink()) continue;
        $this->docinfo = $vars[$filePath];
        $this->currentDoc = $doc;
        $this->currentSubdir = $subDir;
        $this->currentFilepath = $file;
      }
      $cacheKey = HOST."/".get_class($this)."/".Cms::isSuperUser()."/$filePath";
      if(!$this->isValidCached($cacheKey, filemtime($filePath))) {
        $this->storeCache($cacheKey, filemtime($filePath), $file);
        $useCache = false;
      }
    }
    if(empty($vars)) return;
    $filePath = findFile($this->pluginDir."/".get_class($this).".xml");
    $cacheKey = HOST."/".get_class($this)."/".Cms::isSuperUser()."/$filePath";
    if(!$this->isValidCached($cacheKey, filemtime($filePath))) {
      $this->storeCache($cacheKey, filemtime($filePath), $this->pluginDir."/".get_class($this).".xml");
      $useCache = false;
    }
    foreach($this->cfg->documentElement->childElementsArray as $html) {
      if($html->nodeName != "html") continue;
      if(!$html->hasAttribute("id")) {
        new Logger(_("Configuration element html missing attribute id"), Logger::LOGGER_WARNING);
        continue;
      }
      $vName = $html->getAttribute("id").($subDir == "" ? "" : "_".str_replace("/", "_", $subDir));
      $cacheKey = HOST."/".get_class($this)."/".Cms::isSuperUser()."/$vName";
      // use cache
      if($useCache && !self::DEBUG) {
        $sCache = $this->getSubDirCache($cacheKey);
        if(!is_null($sCache)) {
          $doc = new DOMDocumentPlus();
          $doc->loadXML($sCache["value"]);
          Cms::setVariable($sCache["name"], $doc->documentElement);
          continue;
        }
      }
      self::$sortKey = "ctime";
      $reverse = true;
      if($html->hasAttribute("sort") || $html->hasAttribute("rsort")) {
        $reverse = $html->hasAttribute("rsort");
        $userKey = $html->hasAttribute("sort") ? $html->getAttribute("sort") : $html->getAttribute("rsort");
        if(!array_key_exists($userKey, current($vars))) {
          new Logger(sprintf(_("Sort variable %s not found; using default"), $userKey), Logger::LOGGER_WARNING);
        } else {
          self::$sortKey = $userKey;
        }
      }
      uasort($vars, array("Agregator", "cmp"));
      if($reverse) $vars = array_reverse($vars);
      try {
        $vValue = $this->getDOM($vars, $html);
        Cms::setVariable($vName, $vValue->documentElement);
        $var = array(
          "name" => $vName,
          "value" => $vValue->saveXML(),
        );
        $this->storeCache($cacheKey, $var, $vName);
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_WARNING);
        continue;
      }
    }
  }

  private function storeCache($key, $value, $name) {
    $stored = apc_store($key, $value, rand(3600*24*30*3, 3600*24*30*6));
    if(!$stored) new Logger(sprintf(_("Unable to cache variable %s"), $name), Logger::LOGGER_WARNING);
  }

  private function isValidCached($key, $value) {
    if(!apc_exists($key)) return false;
    if(apc_fetch($key) != $value) return false;
    return true;
  }

  private function getSubDirCache($cacheKey) {
    if(!apc_exists($cacheKey)) return null;
    return apc_fetch($cacheKey);
  }

  private static function cmp($a, $b) {
    if($a[self::$sortKey] == $b[self::$sortKey]) return 0;
    return ($a[self::$sortKey] < $b[self::$sortKey]) ? -1 : 1;
  }

  private function getDOM(Array $vars, DOMElementPlus $html) {
    $items = $html->childElementsArray;
    $id = $html->getAttribute("id");
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    if(strlen($html->getAttribute("wrapper")))
      $root = $root->appendChild($doc->createElement($html->getAttribute("wrapper")));
    $nonItemElement = false;
    $patterns = array();
    foreach($items as $item) {
      if($item->nodeName != "item") {
        $nonItemElement = true;
        continue;
      }
      if($item->hasAttribute("since"))
        $patterns[$item->getAttribute("since")-1] = $item;
      else $patterns[] = $item;
    }
    if($nonItemElement) new Logger(sprintf(_("Redundant element(s) found in %s"), $id), Logger::LOGGER_WARNING);
    if(empty($patterns)) throw new Exception(_("No item element found"));
    $i = -1;
    $pattern = null;
    foreach($vars as $k => $v) {
      $i++;
      if(isset($patterns[$i])) $pattern = $patterns[$i];
      if(is_null($pattern) || !$pattern->childNodes->length) continue;
      $item = $root->appendChild($doc->importNode($pattern, true));
      $item->processVariables($v, array(), true);
      $item->stripTag();
    }
    return $doc;
  }

  private function getHTMLVariables(HTMLPlus $doc, $filePath) {
    $vars = array();
    $h = $doc->documentElement->firstElement;
    $desc = $h->nextElement;
    $vars['editlink'] = "";
    if(Cms::isSuperUser()) {
      $vars['editlink'] = "<a href='?Admin=$filePath' title='$filePath' class='flaticon-drawing3'>".$this->edit."</a>";
    }
    $vars['heading'] = $h->nodeValue;
    $vars['link'] = $h->getAttribute("link");
    $vars['author'] = $h->getAttribute("author");
    $vars['authorid'] = $h->hasAttribute("authorid") ? $h->getAttribute("authorid") : "";
    $vars['resp'] = $h->hasAttribute("resp") ? $h->getAttribute("resp") : null;
    $vars['respid'] = $h->hasAttribute("respid") ? $h->getAttribute("respid") : "";
    $vars['ctime'] = $h->getAttribute("ctime");
    $vars['mtime'] = $h->getAttribute("mtime");
    $vars['short'] = $h->hasAttribute("short") ? $h->getAttribute("short") : null;
    $vars['desc'] = $desc->nodeValue;
    $vars['kw'] = $desc->getAttribute("kw");
    return $vars;
  }

}

?>
