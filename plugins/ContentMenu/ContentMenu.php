<?php

#TODO: title

class ContentMenu extends Plugin implements SplObserver, ContentStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $subject->setPriority($this,60);
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $xpath = new DOMXPath($cms->getContentFull());
    $menu = $this->getMenu($content,$xpath->query("/body/section")->item(0));
    if(is_null($menu)) return $content;
    $menu->setAttribute("class","cms-menu");
    $s = $content->documentElement->getElementsByTagName("section")->item(0);
    $content->documentElement->insertBefore($menu,$s);
    $this->trimList($menu);
    return $content;
  }

  private function trimList(DOMElement $ul) {
    $currentLink = false;
    $deepLink = false;
    foreach($ul->childNodes as $li) {
      foreach($li->childNodes as $n) {
        if($this->isProperLink($n)) $currentLink = true;
        if($n->nodeName == "ul") $deepLink = $this->trimList($n);
      }
    }
    if($currentLink || $deepLink) return true;
    $ul->parentNode->removeChild($ul);
    return false;
  }

  private function isProperLink(DOMElement $n) {
    if($n->nodeName != "a") return false;
    if($n->hasAttribute("class") && $n->getAttribute("class") == "fragment") return false;
    return true;
  }

  private function getMenu(HTMLPlus $content, DOMElement $section, $parentLink = "/") {
    $ul = $content->createElement("ul");
    $li = null;
    foreach($section->childNodes as $n) {
      if($n->nodeType != 1) continue;
      if($n->nodeName == "section") {
        $menu = $this->getMenu($content,$n,$parentLink);
        if(!is_null($menu)) {
          $li->appendChild($menu);
        }
        continue;
      }
      if($n->nodeName != "h") continue;
      $li = $content->createElement("li");
      $parentLink = getRoot();
      $link = null;
      if($n->hasAttribute("link")) {
        $link = $n->getAttribute("link");
        $parentLink = $link;
      }
      $a = $content->createElement("a",$n->nodeValue);
      if($n->hasAttribute("short")) {
        $a->nodeValue = $n->getAttribute("short");
        $a->setAttribute("title",$n->nodeValue);
      }
      if($this->subject->getCms()->getLink() === $link) {
        $a->setAttribute("class","current");
      } else {
        if(!is_null($link)) $a->setAttribute("href",$link);
        else {
          $a->setAttribute("href","$parentLink#".$n->getAttribute("id"));
          $a->setAttribute("class","fragment");
        }
      }
      $li->appendChild($a);
      $ul->appendChild($li);
    }
    if(is_null($li)) return null;
    return $ul;
  }

}

?>