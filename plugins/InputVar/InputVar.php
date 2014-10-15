<?php

class InputVar extends Plugin implements SplObserver {
  private $contentXPath;

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "process") return;
    $this->subject = $subject;
    $cf = $subject->getCms()->getContentFull();
    $dom = $this->getDOMPlus();
    $vars = $dom->getElementsByTagName("var");
    foreach($vars as $var) $this->parseVar($var);
  }

  private function parseVar(DOMElement $var) {
    if($var->hasAttribute("fn")) switch($var->getAttribute("fn")) {
      case "hash":
      $value = $this->fnHash($var);
      break;
      case "local_link":
      $value = $this->fnLocal_link($var);
      break;
      case "translate":
      $value = $this->fnTranslate($var);
      if($value === false) return;
      break;
      case "date":
      $value = $this->fnDate($var);
      if($value === false) return;
      break;
    } else {
      $value = $this->parse($var->nodeValue);
    }
    $this->subject->getCms()->setVariable($var->getAttribute("id"),$value);
  }

  private function fnHash(DOMElement $var) {
    return hash("crc32b", $this->parse($var->nodeValue));
  }

  private function fnLocal_link(DOMElement $var) {
    $href = "";
    $title = null;
    if($var->hasAttribute("href")) $href = $this->parse($var->getAttribute("href"));
    if($var->hasAttribute("title")) $title = $var->getAttribute("title");
    return "<a href='" . getLocalLink($href) . "'" . (is_null($title) ? "" : " title='$title'")
    . ">" . $this->parse($var->nodeValue) . "</a>";
  }

  private function fnTranslate(DOMElement $var) {
    if(!$var->hasAttribute("name")) {
      new Logger("Function translate missing attribute 'name'","error");
      return false;
    }
    $name = $this->parse($var->getAttribute("name"));
    $lang = $this->subject->getCms()->getVariable("cms-lang");
    $translation = false;
    foreach($var->getElementsByTagName($lang) as $e) {
      if($e->hasAttribute("name") && $e->getAttribute("name") != $name) continue;
      if(!$e->hasAttribute("name") && $translation !== false) continue;
      $translation = $e->nodeValue;
    }
    return $translation;
  }

  private function fnDate(DOMElement $var) {
    $format = "%D";
    if($var->hasAttribute("format")) {
      $format = $this->parse($var->getAttribute("format"));
      $format = $this->crossPlatformCompatibleFormat($format);
    }
    $time = false;
    if($var->hasAttribute("date"))
      $time = strtotime($this->parse($var->getAttribute("date")));
    if(!$time) $date = strftime($format);
    else $date = strftime($format,$time);
    if($date === false)
      new Logger("Unrecognized date value or format","error");
    return $date;
  }

  /**
   * http://php.net/manual/en/function.strftime.php
   */
  private function crossPlatformCompatibleFormat($format) {
    // Jan 1: results in: '%e%1%' (%%, e, %%, %e, %%)
    #$format = '%%e%%%e%%';

    // Check for Windows to find and replace the %e
    // modifier correctly
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
      $format = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $format);
    }

    return $format;
  }

  private function parse($string) {
    $subStr = explode('\$', $string);
    $output = array();
    foreach($subStr as $s) {
      $r = array();
      preg_match_all('/\$((?:cms-)?[a-z_]+)/',$s,$match);
      foreach($match[1] as $var) {
        $varVal = $this->subject->getCms()->getVariable($var);
        if(is_null($varVal))
          $varVal = $this->subject->getCms()->getVariable("inputvar-$var");
        if(is_null($varVal)) {
          new Logger("Variable '$var' does not exist","warning");
          $output[] = $s;
          continue;
        }
        $r[$var] = $varVal;
      }
      foreach($r as $var => $varVal) {
        $s = str_replace('$'.$var,$varVal,$s);
      }
      $output[] = $s;
    }
    return implode('$',$output);
  }

}

?>