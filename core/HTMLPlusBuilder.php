<?php

namespace IGCMS\Core;

use Exception;

/**
 * @method static getIdToParentId($id = null)
 * @method static getIdToFile($id = null)
 * @method static getIdToShort($id = null)
 * @method static getIdToHeading($id = null)
 * @method static getIdToTitle($id = null)
 * @method static getIdToDesc($id = null)
 * @method static getIdToKw($id = null)
 * @method static getIdToAuthor($id = null)
 * @method static getIdToAuthorId($id = null)
 * @method static getIdToResp($id = null)
 * @method static getIdToRespId($id = null)
 * @method static getIdToCtime($id = null)
 * @method static getIdToMtime($id = null)
 * @method static getIdToLang($id = null)
 * @method static getIdToData($id = null)
 * @method static getIdToLink($id = null)
 * @method static getLinkToId($id = null)
 * @method static getFileToId($fileName = null);
 * @method static HTMLPlus getFileToDoc($fileName = null);
 * @method static getFileToMtime($fileName = null);
 */
class HTMLPlusBuilder extends DOMBuilder {
  /**
   * @var int
   */
  const APC_ID = 0;
  /**
   * @var array
   */
  private static $fileToId = [];
  /**
   * @var array
   */
  private static $fileToDoc = [];
  /**
   * @var array
   */
  private static $fileToMtime = [];
  /**
   * @var array
   */
  private static $idToParentId = [];
  /**
   * @var array
   */
  private static $idToFile = [];
  /**
   * @var array
   */
  private static $idToShort = [];
  /**
   * @var array
   */
  private static $idToHeading = [];
  /**
   * @var array
   */
  private static $idToTitle = [];
  /**
   * @var array
   */
  private static $idToDesc = [];
  /**
   * @var array
   */
  private static $idToKw = [];
  /**
   * @var array
   */
  private static $idToAuthor = [];
  /**
   * @var array
   */
  private static $idToAuthorId = [];
  /**
   * @var array
   */
  private static $idToResp = [];
  /**
   * @var array
   */
  private static $idToRespId = [];
  /**
   * @var array
   */
  private static $idToCtime = [];
  /**
   * @var array
   */
  private static $idToMtime = [];
  /**
   * @var array
   */
  private static $idToLang = [];
  /**
   * @var array
   */
  private static $idToData = [];
  /**
   * @var array
   */
  private static $idToLink = [];
  /**
   * @var array
   */
  private static $linkToId = [];
  /**
   * @var bool
   */
  private static $storeCache = true;
  /**
   * @var array
   */
  private static $currentFileTo;
  /**
   * @var array
   */
  private static $currentIdTo;

  /**
   * @param string $id
   * @return array
   */
  public static function getIdToAll ($id) {
    $register = [];
    $properties = (new \ReflectionClass(get_called_class()))->getStaticProperties();
    foreach (array_keys($properties) as $p) {
      if (strpos($p, "idTo") !== 0) {
        continue;
      }
      #if($p == "idToParentId") continue;
      $register[strtolower(substr($p, 4))] =
        array_key_exists($id, self::${$p}) ? self::${$p}[$id] : null;
    }
    return $register;
  }

  /**
   * @param string $methodName
   * @param string $arguments
   * @return mixed|null
   * @throws Exception
   */
  public static function __callStatic ($methodName, $arguments) {
    $className = (new \ReflectionClass(self::class))->getShortName();
    if (strpos($methodName, "get") !== 0) {
      throw new Exception("Undefined $className method $methodName");
    }
    $propertyName = strtolower(substr($methodName, 3, 1)).substr($methodName, 4);
    if (!property_exists(get_called_class(), $propertyName)) {
      throw new Exception("Undefined $className property $propertyName");
    }
    if (count($arguments)) {
      if (!array_key_exists($arguments[0], self::$$propertyName)) {
        return null;
      }
      return self::${$propertyName}[$arguments[0]];
    }
    return self::$$propertyName;
  }

  /**
   * @param array $idToLink
   */
  public static function setIdToLink (Array $idToLink) {
    self::$idToLink = $idToLink;
    self::$linkToId = [];
    foreach ($idToLink as $id => $link) {
      self::$linkToId[$link] = $id;
    }
  }

  /**
   * @param string $filePath
   * @param string $prefix
   * @return string
   * @throws Exception
   */
  public static function register ($filePath, $prefix = '') {
    $parentId = $prefix;
    if (strlen($parentId)) {
      $parentId = key(self::$idToParentId)."/".$parentId;
      if (!array_key_exists("$parentId", self::$idToParentId)) {
        throw new Exception(sprintf(_("Undefined link '%s'"), $prefix));
      }
    }
    self::$currentFileTo = [];
    self::$currentIdTo = [];
    self::$storeCache = true;
    $cacheKey = apc_get_key(self::APC_ID."/".__FUNCTION__."/".$filePath);
    $useCache = false;
    $cache = null;
    if (apc_exists($cacheKey)) {
      $cache = apc_fetch($cacheKey);
      $useCache = self::isValidApc($cache["currentFileTo"], $cache["currentIdTo"]);
    }
    if ($useCache) {
      self::$currentFileTo = $cache["currentFileTo"];
      self::setNewestFileMtime(current($cache["currentFileTo"]["fileToMtime"]));
      self::build($filePath);
      self::$currentIdTo = $cache["currentIdTo"];
      self::$storeCache = false;
    } else {
      $doc = self::build($filePath, true);
      $id = $doc->documentElement->firstElement->getAttribute("id");
      self::registerIdToData($doc->documentElement, $id);
      self::registerStructure($doc->documentElement, $parentId, $id, $prefix, $filePath);
      self::$currentFileTo["fileToId"] = $id;
    }
    self::addToRegister($filePath);
    if (self::$storeCache) {
      $value = [
        "currentIdTo" => self::$currentIdTo,
        "currentFileTo" => self::$currentFileTo,
      ];
      apc_store_cache($cacheKey, $value, $filePath);
    }
    return self::$currentFileTo["fileToId"];
  }

  /**
   * @param array $fileCache
   * @param array $idCache
   * @return bool
   */
  private static function isValidApc (Array $fileCache, Array $idCache = []) {
    if (!array_key_exists("fileToMtime", $fileCache) || empty($fileCache["fileToMtime"])) {
      return false;
    }
    foreach ($fileCache["fileToMtime"] as $file => $mtime) {
      try {
        if ($mtime == filemtime(find_file($file))) {
          continue;
        }
      } catch (Exception $exc) {
      }
      return false;
    }
    if (empty($idCache)) {
      return true;
    }
    if (!array_key_exists("idToParentId", $idCache) || empty($idCache["idToParentId"])) {
      return false;
    }
    foreach ($idCache["idToParentId"] as $id => $parentId) {
      if (!array_key_exists($parentId, self::$idToParentId)) {
        return false;
      }
    }
    return true;
  }

  /**
   * @param string $filePath
   * @param bool $force
   * @return HTMLPlus
   * @throws Exception
   */
  public static function build ($filePath, $force = false) {
    if (array_key_exists($filePath, self::$fileToDoc)) {
      throw new Exception("File $filePath already built");
    }
    $cacheKey = apc_get_key(self::APC_ID."/".__FUNCTION__."/".$filePath);
    $useCache = false;
    if (!$force && apc_exists($cacheKey)) {
      $cache = apc_fetch($cacheKey);
      $useCache = self::isValidApc($cache);
    }
    if (!$useCache) {
      try {
        $doc = self::doBuild($filePath);
        if (self::$storeCache) {
          $value = [
            "fileToMtime" => self::$currentFileTo["fileToMtime"],
            "xml" => $doc->saveXML(),
          ];
          apc_store_cache($cacheKey, $value, $filePath);
        }
        return $doc;
      } catch (Exception $exc) {
        Logger::error($exc->getMessage());
        if (apc_exists($cacheKey)) {
          $cache = apc_fetch($cacheKey);
          $useCache = true;
        } else {
          try {
            return self::doBuild($filePath, false);
          } catch (Exception $exc) {
            return self::doBuild($filePath, false, false);
          }
        }
      }
    }
    if ($useCache) {
      $doc = new HTMLPlus();
      /** @var array $cache */
      $doc->loadXML($cache["xml"]);
      self::$fileToDoc[$filePath] = $doc;
      return $doc;
    }
  }

  /**
   * @param string $filePath
   * @param bool $user
   * @param bool $admin
   * @return HTMLPlus
   */
  private static function doBuild ($filePath, $user = true, $admin = true) {
    $doc = self::load($filePath, $user, $admin);
    self::$fileToDoc[$filePath] = $doc;
    return $doc;
  }

  /**
   * @param string $filePath
   * @param bool $user
   * @param bool $admin
   * @return HTMLPlus
   * @throws Exception
   */
  private static function load ($filePath, $user = true, $admin = true) {
    $doc = new HTMLPlus();
    $fp = find_file($filePath, $user, $admin);
    try {
      $doc->load($fp);
      self::validateHtml($doc, $fp, $filePath, false);
      self::insertIncludes($doc, dirname($filePath));
      if (array_key_exists("fileToMtime", self::$currentFileTo)
        && count(self::$currentFileTo["fileToMtime"]) > 1
      ) {
        self::validateHtml($doc, $fp, $filePath, true);
      }
    } catch (Exception $exc) {
      self::$storeCache = false;
      throw new Exception(sprintf(_("Unable to load %s: %s"), $filePath, $exc->getMessage()));
    }
    self::$currentFileTo["fileToMtime"][$filePath] = filemtime($fp);
    self::setNewestFileMtime(self::$currentFileTo["fileToMtime"][$filePath]);
    return $doc;
  }

  /**
   * @param HTMLPlus $doc
   * @param string $fileRealPath
   * @param string $filePath
   * @param bool $included
   */
  private static function validateHtml (HTMLPlus $doc, $fileRealPath, $filePath, $included) {
    $doc->validatePlus(true);
    if (empty($doc->getErrors())) {
      return;
    }
    if ($included) {
      $msg = _("File %s invalid syntax caused by includes fixed %s times");
    } else {
      $msg = _("File %s invalid syntax fixed %s times");
    }
    Logger::user_notice(sprintf($msg, $filePath, count($doc->getErrors())));
    if (AUTOCORRECT) {
      rename_incr($fileRealPath);
      file_put_contents($fileRealPath, $doc->saveXML());
      return;
    }
    self::$storeCache = false;
  }

  private static function insertIncludes (HTMLPlus $doc, $workingDir) {
    /** @var DOMElementPlus $h */
    foreach ($doc->getElementsByTagName("h") as $h) {
      if (!$h->hasAttribute("src")) {
        continue;
      }
      try {
        self::insert($h, $workingDir);
      } catch (Exception $exc) {
        $msg = sprintf(_("Unable to import: %s"), $exc->getMessage());
        Logger::user_error($msg);
        self::$storeCache = false;
      }
    }
  }

  /**
   * @param DOMElementPlus $h
   * @param string $workingDir
   * @return string
   */
  private static function insert (DOMElementPlus $h, $workingDir) {
    $src = $h->getAttribute("src");
    $includeFile = self::getIncludeSrc($src, $workingDir);
    $doc = self::load($includeFile);
    $lang = $doc->documentElement->getAttribute("xml:lang");
    foreach ($doc->documentElement->childElementsArray as $n) {
      /** @var DOMElementPlus $e */
      $e = $h->parentNode->insertBefore($h->ownerDocument->importNode($n, true), $h);
      if (strlen($e->getAttribute("xml:lang"))) {
        continue;
      }
      $e->setAttribute("xml:lang", $lang);
    }
    while (!is_null($h)) {
      $next = $h->nextElement;
      $h->parentNode->removeChild($h);
      $h = $next;
      if ($h->nodeName == "h") {
        break;
      }
    }
    return $includeFile;
  }

  /**
   * @param string $src
   * @param string $workingDir
   * @return string
   * @throws Exception
   */
  private static function getIncludeSrc ($src, $workingDir) {
    if (pathinfo($src, PATHINFO_EXTENSION) != "html") {
      throw new Exception(sprintf(_("Included file '%s' extension must be .html"), $src));
    }
    $file = find_file("$workingDir/$src");
    if ($workingDir == ".") {
      return $src;
    }
    if (strpos($file, realpath("$workingDir/")) !== 0) {
      throw new Exception(sprintf(_("Included file '%s' is out of working directory"), $src));
    }
    return "$workingDir/$src";
  }

  /**
   * @param DOMElementPlus $body
   * @param string $fileId
   */
  private static function registerIdToData (DOMElementPlus $body, $fileId) {
    foreach ($body->attributes as $attrName => $attr) {
      if (strpos($attrName, "data-") !== 0) {
        continue;
      }
      self::$currentIdTo["idToData"][$fileId][substr($attrName, strlen("data-"))] = $attr->nodeValue;
    }
  }

  /**
   * @param DOMElementPlus $e
   * @param string $parentId
   * @param string $prefixId
   * @param string $linkPrefix
   * @param string $filePath
   */
  private static function registerStructure (DOMElementPlus $e, $parentId, $prefixId, $linkPrefix, $filePath) {
    $hId = $parentId;
    foreach ($e->childElementsArray as $child) {
      if (strlen($child->getAttribute("id"))) {
        if ($child->nodeName == "h") {
          $hId = self::registerElement($child, $parentId, $prefixId, $linkPrefix, $filePath);
        } else {
          self::registerElement($child, $hId, $prefixId, $linkPrefix, $filePath);
        }
      }
      self::registerStructure($child, $hId, $prefixId, $linkPrefix, $filePath);
    }
  }

  /**
   * @param DOMElementPlus $e
   * @param string $parentId
   * @param string $prefixId
   * @param string $linkPrefix
   * @param string $filePath
   * @return string
   */
  private static function registerElement (DOMElementPlus $e, $parentId, $prefixId, $linkPrefix, $filePath) {
    $id = $e->getAttribute("id");
    $link = (strlen($linkPrefix) ? urlencode($linkPrefix)."/" : "") . urlencode($id);
    if (!strlen($parentId)) {
      $parentId = null;
      if ($filePath == INDEX_HTML) {
        $link = "";
      } else {
        reset(self::$fileToId);
        $parentId = current(self::$fileToId);
      }
    }
    if ($id != $prefixId) {
      $link = urlencode(self::$currentIdTo["idToLink"][$prefixId]) . "#" . urlencode($id);
      $id = "$prefixId/$id";
    }
    if ($e->nodeName == "h") {
      self::$currentIdTo["idToLink"][$id] = $link;
      #self::$currentIdTo["linkToId"][$link] = $id;
      self::setHeadingInfo($id, $e);
    }
    self::$currentIdTo["idToFile"][$id] = $filePath;
    if ($e->hasAttribute("title")) {
      self::$currentIdTo["idToTitle"][$id] = $e->getAttribute("title");
    }
    self::$currentIdTo["idToParentId"][$id] = $parentId;
    return $id;
  }

  /**
   * @param string $id
   * @param DOMElementPlus $h
   */
  private static function setHeadingInfo ($id, DOMElementPlus $h) {
    self::$currentIdTo["idToShort"][$id] = $h->getAttribute("short");
    self::$currentIdTo["idToHeading"][$id] = $h->nodeValue;
    self::$currentIdTo["idToDesc"][$id] = $h->nextElement->nodeValue;
    self::$currentIdTo["idToKw"][$id] = $h->nextElement->getAttribute("kw");
    self::$currentIdTo["idToAuthor"][$id] = $h->getAttribute("author");
    self::$currentIdTo["idToAuthorId"][$id] = $h->getAttribute("authorid");
    self::$currentIdTo["idToResp"][$id] = $h->getAttribute("resp");
    self::$currentIdTo["idToRespId"][$id] = $h->getAttribute("respid");
    self::$currentIdTo["idToCtime"][$id] = $h->getAttribute("ctime");
    self::$currentIdTo["idToMtime"][$id] = $h->getAttribute("mtime");
    self::$currentIdTo["idToLang"][$id] = $h->getSelfOrParentValue("xml:lang");
  }

  /**
   * @param string $filePath
   */
  private static function addToRegister ($filePath) {
    foreach (self::$currentFileTo as $name => $value) {
      switch ($name) {
        case "fileToMtime":
          foreach ($value as $file => $mtime) {
            self::${$name}[$file] = $mtime;
          }
          break;
        default:
          self::${$name}[$filePath] = $value;
      }
    }
    foreach (self::$currentIdTo as $name => $value) {
      foreach ($value as $id => $v) self::${$name}[$id] = $v;
    }
    foreach (self::$currentIdTo["idToLink"] as $name => $value) {
      self::$linkToId[$name] = $value;
    }
  }

  /**
   * @param string $link
   * @return bool
   */
  public static function isLink ($link) {
    return array_key_exists($link, self::$linkToId);
  }

  /**
   * @return string|null
   */
  public static function getCurFile () {
    return self::getIdToFile(self::getLinkToId(get_link()));
  }

  /**
   * @return string
   */
  public static function getRootId () {
    return key(self::$idToParentId);
  }

  /**
   * @param $id
   * @param bool $title
   * @return string
   */
  public static function getHeading ($id, $title = false) {
    if ($title && strlen(HTMLPlusBuilder::getIdToTitle($id))) {
      return HTMLPlusBuilder::getIdToTitle($id);
    }
    if (strlen(HTMLPlusBuilder::getIdToShort($id))) {
      return HTMLPlusBuilder::getIdToShort($id);
    }
    return HTMLPlusBuilder::getIdToHeading($id);
  }

}
