<?php

#TODO: keep missing url_parts from var->nodeValue

class ContentMatch extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "preinit") {
      $subject->setPriority($this,2);
    }
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached(array("Xhtml11","ContentLink"))) return;
    $this->cfgRedir();
    if(getCurLink() == "") {
      $subject->detach($this);
      return;
    }
    $this->proceed();
  }

  private function cfgRedir() {
    global $cms;
    $cfg = $this->getDOMPlus();
    foreach($cfg->documentElement->childNodes as $var) {
      if($var->nodeName != "var") continue;
      if($var->hasAttribute("link") && $var->getAttribute("link") != getCurLink()) continue;
      $pNam = $var->hasAttribute("parName") ? $var->getAttribute("parName") : null;
      $pVal = $var->hasAttribute("parValue") ? $var->getAttribute("parValue") : null;
      if(!$this->queryMatch($pNam, $pVal)) continue;
      $code = $var->hasAttribute("code") && $var->getAttribute("code") == "permanent" ? 301 : 302;
      $link = parse_url($var->nodeValue, PHP_URL_PATH);
      if(!strlen($link)) $link = getCurLink();
      if($link != getCurLink() && is_null($cms->getContentFull()->getElementById($link,"link"))) {
        new Logger("Redirection link '$link' not found","warning");
        continue;
      }
      $query = parse_url($var->nodeValue, PHP_URL_QUERY);
      if(strlen($query)) $query = $this->alterQuery($query, $pNam);
      redirTo(getRoot() . $link . (strlen($query) ? "?$query" : ""), $code);
    }
  }

  private function alterQuery($query, $pNam) {
    $param = array();
    foreach(explode("&",$query) as $p) {
      list($parName, $parValue) = explode("=","$p="); // ensure there is always parValue
      if(!strlen($parValue)) $parValue = $_GET[$pNam];
      $param[$parName] = $parValue;
    }
    $query = array();
    foreach($param as $k => $v) $query[] = $k . (strlen($v) ? "=$v" : "");
    return implode("&",$query);
  }

  private function queryMatch($pNam, $pVal) {
    foreach(explode("&",parse_url(getCurLink(true),PHP_URL_QUERY)) as $q) {
      if(is_null($pVal) && strpos("$q=","$pNam=$pVal") === 0) return true;
      if(!is_null($pVal) && "$q=" == "$pNam=$pVal") return true;
    }
    return false;
  }

  private function proceed() {
    global $cms;
    $h = $cms->getContentFull()->getElementById(getCurLink(),"link");
    if(!is_null($h)) return;
    $newLink = normalize(getCurLink());
    $links = array();
    foreach($cms->getContentFull()->getElementsByTagName("h") as $h) {
      if($h->hasAttribute("link")) $links[] = $h->getAttribute("link");
    }
    $linkId = $this->findSimilar($links,$newLink);
    if(is_null($linkId)) $newLink = ""; // nothing found, redir to hp
    else $newLink = $links[$linkId];
    new Logger("Link '".getCurLink()."' not found, redir to '$newLink'","info");
    redirTo(getRoot().$newLink,404);
  }

  /**
   * exists: aa/bb/cc/dd, aa/bb/cc/ee, aa/bb/dd, aa/dd
   * call: aa/b/cc/dd -> find aa/bb/cc/dd (not aa/dd)
   */
  private function findSimilar(Array $links,$link) {
    if(!strlen($link)) return null;
    // zero pos substring
    if(($newLink = $this->minPos($links,$link,0)) !== false) return $newLink;
    // low levenstein first
    if(($newLink = $this->minLev($links,$link,1)) !== false) return $newLink;

    $parts = explode("/", $link);
    $first = array_shift($parts);
    $subset = array();
    foreach($links as $k => $l) {
      if(strpos($l,$first) !== 0) continue;
      if(strpos($l,"/") === false) continue;
      else $subset[$k] = substr($l,strpos($l,"/")+1);
    }
    if(count($subset) == 1) return key($subset);
    if(empty($subset)) $subset = $links;
    return $this->findSimilar($subset,implode("/",$parts));
  }

  private function minPos(Array $links,$link,$max) {
    $linkpos = array();
    foreach ($links as $k => $l) {
      $pos = strpos($l, $link);
      if($pos === false || $pos > $max) continue;
      $linkpos[$k] = $pos;
    }
    asort($linkpos);
    if(!empty($linkpos)) return key($linkpos);
    $sublinks = array();
    foreach($links as $k => $l) {
      $l = str_replace(array("_","-"), "/", $l);
      if(strpos($l,"/") === false) continue;
      $sublinks[$k] = substr($l,strpos($l,"/")+1);
    }
    if(empty($sublinks)) return false;
    return $this->minPos($sublinks,$link,$max);
  }

  private function minLev(Array $links,$link,$limit) {
    $leven = array();
    foreach ($links as $k => $l) $leven[$k] = levenshtein($l, $link);
    asort($leven);
    if(reset($leven) <= $limit) return key($leven);
    $sublinks = array();
    foreach($links as $k => $l) {
      $l = str_replace(array("_","-"), "/", $l);
      if(strpos($l,"/") === false) continue;
      $sublinks[$k] = substr($l,strpos($l,"/")+1);
    }
    if(empty($sublinks)) return false;
    return $this->minLev($sublinks,$link,$limit);
  }

}

?>
