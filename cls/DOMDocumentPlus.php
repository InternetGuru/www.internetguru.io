<?php

class DOMDocumentPlus extends DOMDocument {

  function __construct($version="1.0",$encoding="utf-8") {
    parent::__construct($version,$encoding);
    $this->preserveWhiteSpace = false;
    $this->formatOutput = true;
  }

  public function getElementById($id,$attribute="id") {
    $xpath = new DOMXPath($this);
    $q = $xpath->query("//*[@$attribute='$id']");
    if($q->length == 0) return null;
    if($q->length > 1)
      throw new Exception("Duplicit $attribute found for value '$id'");
    return $q->item(0);
  }

  public function renameElement($node, $name) {
    $newnode = $this->createElement($name);
    $children = array();
    foreach ($node->childNodes as $child) {
      $children[] = $child;
    }
    foreach ($children as $child) {
      $child = $this->importNode($child, true);
      $newnode->appendChild($child);
    }
    foreach ($node->attributes as $attrName => $attrNode) {
      $newnode->setAttribute($attrName, $attrNode->nodeValue);
    }
    $node->parentNode->replaceChild($newnode, $node);
    return $newnode;
  }

  public function insertVar($varName,$varValue,$prefix="") {
    $xpath = new DOMXPath($this);
    $noparse = "*[not(contains(@class,'noparse')) and (not(ancestor::*) or ancestor::*[not(contains(@class,'noparse'))])]";
    #$noparse = "*";
    if($prefix == "") $prefix = "Cms";
    $var = $prefix.":".$varName;
    // find elements with current var
    $matches = $xpath->query(sprintf("//%s[contains(@var,'%s')]",$noparse,$var));
    $where = array();
    // check for attributes and substring
    foreach($matches as $e) {
      $vars = explode(" ",$e->getAttribute("var"));
      $keep = array();
      foreach($vars as $v) {
        $p = explode("@",$v);
        if($var != $p[0]) {
          $keep[] = $v;
          continue;
        }
        if(isset($p[1])) $where[$p[1]] = $e;
        else $where[] = $e;
      }
      if(empty($keep)) {
        $e->removeAttribute("var");
        continue;
      }
      $e->setAttribute("var",implode(" ",$keep));
    }
    if(!count($where)) return;
    $type = gettype($varValue);
    if($type == "object") $type = get_class($varValue);
    foreach($where as $a => $e) {
      switch($type) {
        case "string":
        $this->insertVarString($varValue,$e,$a);
        break;
        case "array":
        if(empty($varValue)) $this->emptyVarArray($e);
        else $this->insertVarArray($varValue,$e);
        break;
        case "DOMElement":
        $varxpath = new DOMXPath($varValue->ownerDocument);
        $varValue = $varxpath->query("/*");
        case "DOMNodeList":
        $this->insertVarDOMNodeList($var,$varValue,$e);
        break;
        default:
        throw new Exception("Unsupported type '$type'");
      }
    }
  }

  public function saveRewrite($filepath) {
    $b = $this->save("$filepath.new");
    if($b === false) return false;
    if(!copy($filepath,"$filepath.old")) return false;
    if(!rename("$filepath.new",$filepath)) return false;
    return $b;
  }

  private function insertVarString($varValue,DOMElement $e,$attr="") {
    if(strlen($attr) && !is_numeric($attr)) {
      if(!$e->hasAttribute($attr) || $e->getAttribute($attr) == "") {
        $e->setAttribute($attr,$varValue);
        return;
      }
      $e->setAttribute($attr,$e->getAttribute($attr)." ".$varValue);
      return;
    }
    $new = sprintf($e->nodeValue,$varValue);
    if($new != $e->nodeValue) $e->nodeValue = $new;
    else $e->nodeValue = $varValue;
  }

  private function insertVarArray(Array $varValue,DOMElement $e) {
    $p = $e->parentNode;
    foreach($varValue as $v) {
      $li = $p->appendChild($e->cloneNode());
      $li->nodeValue = $v;
    }
    $p->removeChild($e);
  }

  private function emptyVarArray(DOMElement $e) {
    if($e->nodeValue != "") return;
    $p = $e->parentNode;
    $p->removeChild($e);
    if($p->childNodes->length == 0)
      $p->parentNode->removeChild($p);
  }

  private function insertVarDOMNodeList($varName,DOMNodeList $varValue,DOMNodeList $where) {
    $into = array();
    foreach($where as $e) $into[] = $e;
    foreach($into as $e) {
      $newParent = $e->parentNode->cloneNode();
      $e->ownerDocument->importNode($newParent);
      $children = array();
      foreach($e->parentNode->childNodes as $ch) $children[] = $ch;
      foreach($children as $ch) {
        if(!$ch->isSameNode($e)) {
          $newParent->appendChild($ch);
          continue;
        }
        $parts = explode($varName,$ch->nodeValue);
        foreach($parts as $id => $part) {
          $newParent->appendChild($e->ownerDocument->createTextNode($part));
          if((count($parts)-1) == $id) continue; // (not here) txt1 (here) txt2 (here) txt3 (not here)
          $append = array();
          foreach($varValue as $n) {
            if($n->nodeType === 1) $append[] = $n;
          }
          foreach($append as $n) {
            $newParent->appendChild($e->ownerDocument->importNode($n,true));
          }
        }
      }
      $e->parentNode->parentNode->replaceChild($newParent,$e->parentNode);
    }
  }

}
?>