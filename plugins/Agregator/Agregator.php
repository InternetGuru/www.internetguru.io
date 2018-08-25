<?php

namespace IGCMS\Plugins;
use Exception;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\GetContentStrategyInterface;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class Agregator
 * @package IGCMS\Plugins
 */
class Agregator extends Plugin implements SplObserver, GetContentStrategyInterface {
    /**
   * @var string
   */
  const DOCLIST_CLASS = "IGCMS\\Plugins\\Agregator\\DocList";  // filePath => fileInfo(?)
  /**
   * @var string
   */
  const IMGLIST_CLASS = "IGCMS\\Plugins\\Agregator\\ImgList";
/**
   * @var array
   */
  private $registered = [];
  /**
   * @var DOMElementPlus[]
   */
  private $imgLists = [];
  /**
   * @var DOMElementPlus[]
   */
  private $docLists = [];
  /**
   * @var array
   */
  private $lists = [];

  /**
   * Agregator constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 100);
    $this->lists[self::DOCLIST_CLASS] = [];
    $this->lists[self::IMGLIST_CLASS] = [];
  }

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PREINIT) {
      return;
    }
    if ($this->detachIfNotAttached("HtmlOutput")) {
      return;
    }
    $this->registerFiles(CMS_FOLDER);
    $this->registerFiles(ADMIN_FOLDER);
    $this->registerFiles(USER_FOLDER);
    $this->setVars();
    $msg = _("Unable to create %s id '%s': %s");
    foreach ($this->docLists as $docListId => $docList) {
      try {
        /** @var DOMElementPlus $docListId $docListId or id of referenced $docList */
        $docListId = $this->processFor(self::DOCLIST_CLASS, $docListId, $docList);
        $this->createList(self::DOCLIST_CLASS, $docListId, $docList);
      } catch (Exception $exc) {
        Logger::user_warning(sprintf($msg, self::DOCLIST_CLASS, $docListId, $exc->getMessage()));
      }
    }
    foreach ($this->imgLists as $imgListId => $imgList) {
      try {
        $this->createList(self::IMGLIST_CLASS, $imgListId, $imgList);
      } catch (Exception $exc) {
        Logger::user_warning(sprintf($msg, self::IMGLIST_CLASS, $imgListId, $exc->getMessage()));
      }
    }
  }

  /**
   * @param string $workingDir
   * @param string|null $folder
   */
  private function registerFiles ($workingDir, $folder = null) {
    $cwd = "$workingDir/".$this->pluginDir."/$folder";
    if (!is_dir($cwd)) {
      return;
    }
    switch ($workingDir) {
      case CMS_FOLDER:
        if (is_dir(ADMIN_FOLDER."/".$this->pluginDir."/$folder")
          && !stream_resolve_include_path(ADMIN_FOLDER."/".$this->pluginDir."/.$folder")
        ) {
          return;
        }
      case ADMIN_FOLDER:
        if (is_dir(USER_FOLDER."/".$this->pluginDir."/$folder")
          && !stream_resolve_include_path(USER_FOLDER."/".$this->pluginDir."/.$folder")
        ) {
          return;
        }
    }
    foreach (scandir($cwd) as $file) {
      if (strpos($file, ".") === 0) {
        continue;
      }
      if (stream_resolve_include_path("$cwd/.$file")) {
        continue;
      }
      $filePath = is_null($folder) ? $file : "$folder/$file";
      if (is_dir("$cwd/$file")) {
        $this->registerFiles($workingDir, $filePath);
        continue;
      }
      if (pathinfo($file, PATHINFO_EXTENSION) != "html") {
        continue;
      }
      try {
        HTMLPlusBuilder::register($this->pluginDir."/$filePath", $folder);
        $this->registered[$this->pluginDir."/$filePath"] = null;
      } catch (Exception $exc) {
        Logger::user_warning(sprintf(_("Unable to register '%s': %s"), $filePath, $exc->getMessage()));
      }
    }
  }

  /**
   * @throws Exception
   */
  private function setVars () {
    /** @var DOMElementPlus $child */
    foreach (self::getXML()->documentElement->childNodes as $child) {
      if ($child->nodeType != XML_ELEMENT_NODE) {
        continue;
      }
      try {
        $attrId = $child->getRequiredAttribute("id");
      } catch (Exception $exc) {
        Logger::user_warning($exc->getMessage());
        continue;
      }
      switch ($child->nodeName) {
        case "imglist":
          $this->imgLists[$attrId] = $child;
          break;
        case "doclist":
          $this->docLists[$attrId] = $child;
          break;
      }
    }
  }

  /**
   * @param string $listClass
   * @param string $doclistId
   * @param DOMElementPlus $docList
   * @return string
   * @throws Exception
   */
  private function processFor ($listClass, $doclistId, DOMElementPlus $docList) {
    $for = $docList->getAttribute("for");
    if (!strlen($for)) {
      return $doclistId;
    }
    if (!array_key_exists($for, $_GET)) {
      return $doclistId;
    }
    $this->doclistExists($listClass, $for);
    if ($doclistId != $_GET[$for]) {
      return $doclistId;
    }
    if (!$docList->hasAttribute($docList->nodeName)) {
      $docList->setAttribute($docList->nodeName, $for);
    }
    foreach ($this->lists[$listClass][$for]->attributes as $attrName => $attrNode) {
      if ($docList->hasAttribute($attrName)) {
        continue;
      }
      $docList->setAttribute($attrName, $attrNode->nodeValue);
    }
    $docList->setAttribute("id", $for);
    return $for;
  }

  /**
   * @param string $listClass
   * @param string $doclistId
   * @throws Exception
   */
  private function doclistExists ($listClass, $doclistId) {
    if (!strlen($doclistId)) {
      return;
    }
    if (array_key_exists($doclistId, $this->lists[$listClass])) {
      return;
    }
    throw new Exception(sprintf(_("Reference id '%s' not found"), $doclistId));
  }

  /**
   * @param string $listClass
   * @param string $templateId
   * @param DOMElementPlus $template
   * @throws Exception
   */
  private function createList ($listClass, $templateId, DOMElementPlus $template) {
    $ref = $template->getAttribute($template->nodeName);
    if ($template->hasChildNodes()) {
      $this->lists[$listClass][$templateId] = $template;
      new $listClass($template);
      return;
    }
    if (!strlen($ref)) {
      if ($template->hasAttribute("for")) {
        return;
      }
      throw new Exception(sprintf(_("No content"), $templateId));
    }
    $this->doclistExists($listClass, $ref);
    new $listClass($template, $this->lists[$listClass][$ref]);
  }

  /**
   * @return HTMLPlus|null
   * @throws Exception
   */
  public function getContent () {
    $file = HTMLPlusBuilder::getCurFile();
    if (is_null($file) || !array_key_exists($file, $this->registered)) {
      return null;
    }
    $content = HTMLPlusBuilder::getFileToDoc($file);
    $content->documentElement->addClass(strtolower($this->className));
    return $content;
  }

}

