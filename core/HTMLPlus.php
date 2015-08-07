<?php

class HTMLPlus extends DOMDocumentPlus {
  private $headings = array();
  private $defaultAuthor = null;
  private $defaultCtime = null;
  private $defaultHeading = null;
  private $defaultLink = null;
  private $defaultNs = null;
  private $defaultDesc = null;
  private $defaultKw = null;
  const RNG_FILE = "HTMLPlus.rng";

  function __construct($version="1.0", $encoding="utf-8") {
    parent::__construct($version, $encoding);
    $c = new DateTime("now");
    $this->defaultCtime = $c->format(DateTime::W3C);
    $this->defaultNs = HOST;
  }

  public function __set($vName, $vValue) {
    if(!is_null($vValue) && (!is_string($vValue) || !strlen($vValue)))
      throw new Exception(_("Variable value must be non-empty string or null"));
    switch($vName) {
      case "defaultCtime":
      case "defaultLink":
      case "defaultAuthor":
      case "defaultHeading":
      case "defaultDesc":
      case "defaultKw":
      $this->$vName = $vValue;
    }
  }

  public function __clone() {
    $doc = new HTMLPlus();
    $root = $doc->importNode($this->documentElement, true);
    $doc->appendChild($root);
    return $doc;
  }

  public function processVariables(Array $variables) {
    $ignore = array("h" => array("id", "link"));
    $newContent = parent::processVariables($variables, $ignore);
    $newContent->ownerDocument->validatePlus(true);
    return $newContent->ownerDocument;
  }

  public function processFunctions(Array $functions, Array $variables) {
    $ignore = array("h" => array("id", "link"));
    parent::processFunctions($functions, $variables, $ignore);
    $this->validatePlus(true);
  }

  public function applySyntax() {
    $extend = array("strong", "em", "ins", "del", "sub", "sup", "a", "h", "desc");

    // hide noparse
    $noparse = array();
    foreach($this->getInlineTextNodes($extend) as $n)
      $noparse = array_merge($noparse, $this->parseSyntaxNoparse($n));

    // proceed syntax translation
    foreach($this->getInlineTextNodes() as $n) $this->parseSyntaxCodeTag($n);
    foreach($this->getInlineTextNodes() as $n) $this->parseSyntaxCode($n);
    foreach($this->getInlineTextNodes($extend) as $n) $this->parseSyntaxVariable($n);
    foreach($this->getInlineTextNodes($extend) as $n) $this->parseSyntaxComment($n);

    // restore noparse
    foreach($noparse as $n) {
      $newNode = $this->createTextNode($n[1]);
      $n[0]->parentNode->insertBefore($newNode, $n[0]);
      $n[0]->parentNode->removeChild($n[0]);
    }
  }

  private function getInlineTextNodes($extend = array()) {
    $textNodes = array();
    foreach(array_merge(array("p", "dt", "dd", "li"), $extend) as $eNam) {
      foreach($this->getElementsByTagName($eNam) as $e) {
        foreach($e->childNodes as $n) {
          if($n->nodeType == XML_TEXT_NODE) $textNodes[] = $n;
        }
      }
    }
    return $textNodes;
  }

  private function parseSyntaxNoparse(DOMText $n) {
    $noparse = array();
    $pat = "/<noparse>(.+?)<\/noparse>/";
    $p = preg_split($pat, $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(count($p) < 2) return $noparse;
    foreach($p as $i => $v) {
      if($i % 2 == 0) $newNode = $this->createTextNode($v);
      else {
        $newNode = $this->createElement("noparse");
        $noparse[] = array($newNode, $v);
      }
      $n->parentNode->insertBefore($newNode, $n);
    }
    $n->parentNode->removeChild($n);
    return $noparse;
  }

  private function parseSyntaxCodeTag(DOMText $n) {
    $pat = "/<code(?: [a-z]+)?>(.+?)<\/code>/";
    $p = preg_split($pat, $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(count($p) < 2) return;
    foreach($p as $i => $v) {
      if($i % 2 == 0) $newNode = $this->createTextNode($v);
      else {
        $s = array("&bdquo;", "&ldquo;", "&rdquo;", "&lsquo;", "&rsquo;");
        $r = array('"', '"', '"', "'", "'");
        $v = str_replace($s, $r, translateUtf8Entities($v, true));
        $newNode = $this->createElement("code", translateUtf8Entities($v));
        if(preg_match("/<code ([a-z]+)>/", $n->nodeValue, $match)) {
          $newNode->setAttribute("class", $match[1]);
        }
      }
      $n->parentNode->insertBefore($newNode, $n);
    }
    $n->parentNode->removeChild($n);
  }

  private function parseSyntaxCode(DOMText $n) {
    $pat = "/(?:&lsquo;|&rsquo;|'){2}(.+?)(?:&lsquo;|&rsquo;|'){2}/";
    $src = translateUtf8Entities($n->nodeValue, true);
    $p = preg_split($pat, $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(count($p) < 2) return;
    foreach($p as $i => $v) {
      if($i % 2 == 0) $newNode = $this->createTextNode(translateUtf8Entities($v));
      else {
        $s = array("&bdquo;", "&ldquo;", "&rdquo;", "&lsquo;", "&rsquo;");
        $r = array('"', '"', '"', "'", "'");
        $v = str_replace($s, $r, $v);
        $newNode = $this->createElement("code", translateUtf8Entities($v));
      }
      $n->parentNode->insertBefore($newNode, $n);
    }
    $n->parentNode->removeChild($n);
  }

  private function parseSyntaxVariable(DOMText $n) {
    //if(strpos($n->nodeValue, 'cms-') === false) return;
    foreach(explode('\$', $n->nodeValue) as $src) {
      $p = preg_split('/\$('.VARIABLE_PATTERN.")/", $src, -1, PREG_SPLIT_DELIM_CAPTURE);
      if(count($p) < 2) return;
      foreach($p as $i => $v) {
        if($i % 2 == 0) $newNode = $this->createTextNode($v);
        else {
          // <p>$varname</p> -> <p var="varname"/>
          // <p><strong>$varname</strong></p> -> <p><strong var="varname"/></p>
          // else
          // <p>aaa $varname</p> -> <p>aaa <em var="varname"/></p>
          if($n->parentNode->nodeValue == "\$$v") {
            $n->parentNode->setAttribute("var", $v);
            continue;
          } else {
            $newNode = $this->createElement("em");
            $newNode->setAttribute("var", $v);
          }
        }
        $n->parentNode->insertBefore($newNode, $n);
      }
    }
    $n->parentNode->removeChild($n);
  }

  private function parseSyntaxComment(DOMText $n) {
    $p = preg_split('/\(\(\s*(.+)\s*\)\)/', $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(count($p) < 2) return;
    foreach($p as $i => $v) {
      if($i % 2 == 0) $n->parentNode->insertBefore($this->createTextNode($v), $n);
      else $n->parentNode->insertBefore($this->createComment(" $v "), $n);
    }
    $n->parentNode->removeChild($n);
  }

  public function validatePlus($repair = false) {
    $this->headings = $this->getElementsByTagName("h");
    $this->validateRoot($repair);
    $this->validateSections($repair);
    $this->validateLang($repair);
    $this->validateHid($repair);
    $this->validateHempty($repair);
    $this->validateDesc($repair);
    $this->validateHLink($repair);
    #$this->validateLinks("a", "href", $repair);
    #$this->validateLinks("form", "action", $repair);
    #$this->validateLinks("object", "data", $repair);
    $this->validateDates($repair);
    $this->validateAuthor($repair);
    $this->validateFirstHeadingAuthor($repair);
    $this->validateFirstHeadingLink($repair);
    $this->validateFirstHeadingCtime($repair);
    $this->validateBodyNs($repair);
    $this->validateMeta($repair);
    $this->relaxNGValidatePlus();
  }

  private function validateMeta($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("link")) continue;
      if(!strlen(trim($h->nextElement->nodeValue))) {
        if(!$repair || is_null($this->defaultDesc))
          throw new Exception(sprintf(_("Empty element desc following heading with attribute link %s found"), $h->getAttribute("link")));
        $h->nextElement->nodeValue = $this->defaultDesc;
      }
      if(!$h->nextElement->hasAttribute("kw") || !strlen(trim($h->nextElement->getAttribute("kw")))) {
        if(!$repair || is_null($this->defaultKw))
          throw new Exception(sprintf(_("Attribute kw following heading with link %s not found or empty"), $h->getAttribute("link")));
        $h->nextElement->setAttribute("kw", $this->defaultKw);
      }
    }
  }

  private function validateBodyNs($repair) {
    $b = $this->documentElement;
    if($b->hasAttribute("ns")) return;
    $h = $this->headings->item(0);
    if($h->hasAttribute("ns")) {
      $this->defaultNs = $h->getAttribute("ns");
      $h->removeAttribute("ns");
    }
    if(!$repair || is_null($this->defaultNs))
      throw new Exception(_("Body attribude 'ns' missing"));
    $b->setAttribute("ns", $this->defaultNs);
  }

  private function validateFirstHeadingLink($repair) {
    $h = $this->headings->item(0);
    if($h->hasAttribute("link")) return;
    if(!$repair || is_null($this->defaultLink))
      throw new Exception(_("First heading attribude 'link' missing"));
    $h->setAttribute("link", $this->defaultLink);
  }

  private function validateFirstHeadingAuthor($repair) {
    $h = $this->headings->item(0);
    if($h->hasAttribute("author")) return;
    if(!$repair || is_null($this->defaultAuthor))
      throw new Exception(_("First heading attribute 'author' missing"));
    $h->setAttribute("author", $this->defaultAuthor);
  }

  private function validateFirstHeadingCtime($repair) {
    $h = $this->headings->item(0);
    if($h->hasAttribute("ctime")) return;
    if(!$repair || is_null($this->defaultCtime))
      throw new Exception(_("First heading attribute 'ctime' missing"));
    $h->setAttribute("ctime", $this->defaultCtime);
  }

  public function relaxNGValidatePlus($f=null) {
    return parent::relaxNGValidatePlus(LIB_FOLDER."/".self::RNG_FILE);
  }

  private function validateRoot($repair) {
    if(is_null($this->documentElement))
      throw new Exception(_("Root element not found"));
    if($this->documentElement->nodeName != "body") {
      if(!$repair) throw new Exception(_("Root element must be 'body'"));
      $this->documentElement->rename("body");
    }
    if(!$this->documentElement->hasAttribute("lang")
      && !$this->documentElement->hasAttribute("xml:lang")) {
      if(!$repair) throw new Exception(_("Attribute 'xml:lang' is missing in element body"));
      $this->documentElement->setAttribute("xml:lang", _("en"));
    }
    $fe = $this->documentElement->firstElement;
    if(!is_null($fe) && $fe->nodeName == "section") {
      if(!$repair) throw new Exception(_("Element section cannot be empty"));
      $this->addTitleElements($this->documentElement);
      return;
    }
    $hRoot = 0;
    foreach($this->documentElement->childNodes as $e) {
      if($e->nodeType != XML_ELEMENT_NODE) continue;
      if($e->nodeName != "h") continue;
      if($hRoot++ == 0) continue;
      if(!$repair) throw new Exception(_("There must be exactly one heading in body element"));
      break;
    }
    if($hRoot == 1) return;
    if($hRoot == 0) {
      if(!$repair) throw new Exception(_("Missing heading in body element"));
      $this->documentElement->appendChild($this->createElement("h"));
      return;
    }
    $children = array();
    foreach($this->documentElement->childNodes as $e) $children[] = $e;
    $s = $this->createElement("section");
    foreach($children as $e) $s->appendChild($e);
    $s->appendChild($this->createTextNode("  "));
    $this->documentElement->appendChild($s);
    $this->documentElement->appendChild($this->createTextNode("\n"));
    $this->addTitleElements($s);
  }

  private function addTitleElements(DOMElementPlus $el) {
    $first = $el->firstElement;
    $el->insertBefore($this->createTextNode("\n  "), $first);
    $el->insertBefore($this->createElement("h", _("Web title")), $first);
    $el->insertBefore($this->createTextNode("\n  "), $first);
    $el->insertBefore($this->createElement("desc", _("Web description")), $first);
    $el->insertBefore($this->createTextNode("\n  "), $first);
  }

  private function validateSections($repair) {
    $emptySect = array();
    foreach($this->getElementsByTagName("section") as $s) {
      if(!count($s->childElementsArray)) $emptySect[] = $s;
    }
    if(!$repair && count($emptySect)) throw new Exception(_("Empty section(s) found"));
    if(!count($emptySect)) return;
    foreach($emptySect as $s) $s->stripTag(_("Empty section deleted"));
  }

  private function validateLang($repair) {
    $xpath = new DOMXPath($this);
    $langs = $xpath->query("//*[@lang]");
    if($langs->length && !$repair)
      throw new Exception(_("Lang attribute without xml namespace"));
    foreach($langs as $n) {
      if(!$n->hasAttribute("xml:lang"))
        $n->setAttribute("xml:lang", $n->getAttribute("lang"));
      $n->removeAttribute("lang");
    }
  }

  private function validateHid($repair) {
    $hIds = array();
    foreach($this->headings as $h) {
      $id = $h->hasAttribute("id") ? $h->getAttribute("id") : null;
      if(!isValidId($id)) {
        if(!$repair) throw new Exception(sprintf(_("Heading attribut id '%s' missing or invalid"), $id));
        $h->setUniqueId();
      }
      if(array_key_exists($id, $hIds)) {
        if(!$repair) throw new Exception(sprintf(_("Duplicit heading attribut id '%s'"), $id));
        $h->setUniqueId();
      }
      $hIds[$h->getAttribute("id")] = null;
    }
  }

  private function validateHempty($repair) {
    foreach($this->headings as $h) {
      if(strlen(trim($h->nodeValue))) continue;
      if(!$repair || is_null($this->defaultHeading))
        throw new Exception(_("Heading content must not be empty"));
      $h->nodeValue = $this->defaultHeading;
    }
  }

  private function validateDesc($repair) {
    if($repair) $this->repairDesc();
    foreach($this->headings as $h) {
      if(is_null($h->nextElement) || $h->nextElement->nodeName != "desc") {
        if(!$repair) throw new Exception(_("Missing element 'desc'"));
        $desc = $h->ownerDocument->createElement("desc");
        $h->parentNode->insertBefore($desc, $h->nextElement);
      }
    }
  }

  private function repairDesc() {
    $desc = array();
    foreach($this->getElementsByTagName("description") as $d) $desc[] = $d;
    foreach($desc as $d) {
      $d->rename("desc");
    }
  }

  private function validateHLink($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("link")) continue;
      #$this->getElementById($h->getAttribute("link"), "link");
      $link = normalize($h->getAttribute("link"), "a-zA-Z0-9/_-");
      while(preg_match("/^[^a-z]/", $link)) $link = substr($link, 1); // must start with a-z
      if(trim($link) == "") {
        if($link != $h->getAttribute("link"))
          throw new Exception(sprintf(_("Normalize link leads to empty value '%s'"), $h->getAttribute("link")));
        throw new Exception(_("Empty attribute link found"));
      }
      if($link != $h->getAttribute("link")) {
        if(!$repair) throw new Exception(sprintf(_("Invalid link value found '%s'"), $h->getAttribute("link")));
        if(!is_null($this->getElementById($link, "link"))) {
          throw new Exception(sprintf(_("Normalize link leads to duplicit value '%s'"), $h->getAttribute("link")));
        }
        $h->setAttribute("link", $link);
      }
    }
  }

  private function validateLinks($elName, $attName, $repair) {
    Logger::log(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__), Logger::LOGGER_ERROR);
  }

  private function validateAuthor($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("author")) continue;
      if(strlen(trim($h->getAttribute("author")))) continue;
      if(!$repair) throw new Exception(_("Attr 'author' cannot be empty"));
      $h->parentNode->insertBefore(new DOMComment(" empty attr 'author' removed "), $h);
      $h->removeAttribute("author");
    }
  }

  private function validateDates($repair) {
    foreach($this->headings as $h) {
      $ctime = null;
      $mtime = null;
      if($h->hasAttribute("ctime")) $ctime = $h->getAttribute("ctime");
      if($h->hasAttribute("mtime")) $mtime = $h->getAttribute("mtime");
      if(is_null($ctime) && is_null($mtime)) continue;
      if(is_null($ctime)) $ctime = $h->getAncestorValue("ctime");
      if(is_null($ctime)) {
        if(!$repair) throw new Exception(_("Attribute 'mtime' requires 'ctime'"));
        $ctime = $mtime;
        $h->setAttribute("ctime", $ctime);
      }
      $ctime_date = $this->createDate($ctime);
      if(is_null($ctime_date)) {
        if(!$repair) throw new Exception(_("Invalid 'ctime' attribute format"));
        $h->parentNode->insertBefore(new DOMComment(" invalid ctime='$ctime' "), $h);
        $h->removeAttribute("ctime");
      }
      if(is_null($mtime)) return;
      $mtime_date = $this->createDate($mtime);
      if(is_null($mtime_date)) {
        if(!$repair) throw new Exception(_("Invalid 'mtime' attribute format"));
        $h->parentNode->insertBefore(new DOMComment(" invalid mtime='$mtime' "), $h);
        $h->removeAttribute("mtime");
      }
      if($mtime_date < $ctime_date) {
        if(!$repair) throw new Exception(_("'mtime' cannot be lower than 'ctime'"));
        $h->parentNode->insertBefore(new DOMComment(" invalid mtime='$mtime' "), $h);
        $h->removeAttribute("mtime");
      }
    }
  }

  private function createDate($d) {
    $date = new DateTime();
    $date->setTimestamp(strtotime($d));
    $date_errors = DateTime::getLastErrors();
    if($date_errors['warning_count'] + $date_errors['error_count'] > 0) {
      return null;
    }
    return $date;
  }

}
?>