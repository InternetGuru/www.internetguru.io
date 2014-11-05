<?php

class SubdomManager extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $err = array();
  private $cmsVersions;
  private $cmsPlugins;
  private $userDirs;
  private $filesDirs;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,3);
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if(isAtLocalhost() || !isset($_GET[get_class($this)])) {
      $subject->detach($this);
    }
  }

  public function getContent(HTMLPlus $content) {

    $this->cmsVersions = $this->getSubdirs(CMS_FOLDER ."/..");
    $this->cmsPlugins = $this->getSubdirs(CMS_FOLDER ."/". PLUGIN_FOLDER);
    $this->userDirs = $this->getSubdirs(USER_FOLDER ."/..");
    $this->filesDirs = $this->getSubdirs(FILES_FOLDER ."/..");

    #TODO: run script

    global $var;
    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("errors", $this->err);
    $newContent->insertVar("curlink", getCurLink(true));
    $newContent->insertVar("domain", getDomain());
    $newContent->insertVar("user", $var["USER_ID"]);

    $fset = $newContent->getElementsByTagName("fieldset")->item(0);
    foreach(scandir("..") as $subdom) {
      if(!is_dir("../$subdom") || strpos($subdom, ".") === 0) continue;
      if(!is_file("../$subdom/USER_ID.".$var["USER_ID"])) continue;
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($fset, true));
      $this->modifyDOM($doc, $subdom);
      $fset->parentNode->insertBefore($newContent->importNode($doc->documentElement, true), $fset);
    }

    $fset->parentNode->removeChild($fset);
    return $newContent;
  }

  private function modifyDOM(DOMDocumentPlus $doc, $subdom) {
    $doc->insertVar("subdom", $subdom);
    $doc->insertVar("cmsVerId", "$subdom.CMS_VER");
    $doc->insertVar("userDirId", "$subdom.USER_DIR");
    $doc->insertVar("filesDirId", "$subdom.FILES_DIR");
    if($subdom == basename(dirname($_SERVER["PHP_SELF"]))) $doc->insertVar("nohide", "nohide");

    // versions
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->cmsVersions as $cmsVer => $active) {
      $verName = $cmsVer . ($active ? "" : " (deprecated)");
      $o = $set->appendChild($d->createElement("option", $verName));
      if(is_file("../$subdom/CMS_VER.$cmsVer")) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $cmsVer);
    }
    $doc->insertVar("cmsVers", $set);

    // user directories
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->userDirs as $dir => $active) {
      if(!$active || strpos($dir, "~") === 0) continue; // silently skip dirs to del and mirrors
      $o = $set->appendChild($d->createElement("option", $dir));
      if(is_file("../$subdom/USER_DIR.$dir")) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $dir);
    }
    $doc->insertVar("userDirs", $set);

    // files directories
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->filesDirs as $dir => $active) {
      if(!$active) continue; // silently skip disabled dirs
      $o = $set->appendChild($d->createElement("option", $dir));
      if(is_file("../$subdom/FILES_DIR.$dir")) $o->setAttribute("selected", "selected");
      $o->setAttribute("value", $dir);
    }
    $doc->insertVar("filesDirs", $set);

    // plugins
    $d = new DOMDocumentPlus();
    $set = $d->appendChild($d->createElement("var"));
    foreach($this->cmsPlugins as $pName => $default) {
      if(is_file("../$subdom/.PLUGIN.$pName")) continue; // silently skip forbidden plugins
      $i = $set->appendChild($d->createElement("input"));
      $i->setAttribute("type", "checkbox");
      $i->setAttribute("id", "$subdom.$pName");
      $i->setAttribute("name", "$pName");
      if(file_exists("../$subdom/PLUGIN.$pName")) $i->setAttribute("checked", "checked");
      $l = $set->appendChild($d->createElement("label", " $pName"));
      $l->setAttribute("for","$subdom.$pName");
      $set->appendChild($d->createTextNode(", "));
    }
    $doc->insertVar("plugins", $set);
  }

  private function getSubdirs($dir) {
    $subdirs = array();
    foreach(scandir($dir) as $f) {
      if(!is_dir("$dir/$f") || strpos($f, ".") === 0) continue;
      $subdirs[$f] = !file_exists("$dir/.$f");
    }
    return $subdirs;
  }

}

?>
