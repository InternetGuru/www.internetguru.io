<?php

namespace IGCMS\Plugins\Agregator;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;

/**
 * Class ImgList
 * @package IGCMS\Plugins\Agregator
 */
class ImgList extends AgregatorList {
  /**
   * @var string
   */
  const DEFAULT_SORTBY = "name";
  /**
   * @var bool
   */
  const DEFAULT_RSORT = false;

  /**
   * ImgList constructor.
   * @param DOMElementPlus $doclist
   * @param DOMElementPlus|null $pattern
   */
  public function __construct (DOMElementPlus $doclist, DOMElementPlus $pattern = null) {
    parent::__construct($doclist, self::DEFAULT_SORTBY, self::DEFAULT_RSORT);
    $vars = $this->createVars();
    if (is_null($pattern)) {
      $pattern = $doclist;
    }
    $list = $this->createList($pattern, $vars);
    Cms::setVariable($this->id, $list);
  }

  /**
   * @return array
   * @throws Exception
   */
  private function createVars () {
    $path = strlen($this->path) ? "/".$this->path : "";
    $fileDir = FILES_DIR.$path;
    $fileFolder = USER_FOLDER."/".$fileDir;
    $vars = [];
    if (!is_dir($fileFolder)) {
      throw new Exception(sprintf(_("Path '%s' not found"), $fileDir));
    }
    foreach (scandir($fileFolder) as $file) {
      if (strpos($file, ".") === 0) {
        continue;
      }
      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
      if (!in_array($ext, ["jpg", "jpeg", "png", "gif"])) {
        continue;
      }
      $filePath = "$fileDir/$file";
      $fullFilePath = "$fileFolder/$file";
      if (is_dir($fullFilePath)) {
        continue;
      }
      $mimeType = getFileMime($fullFilePath);
      if ($mimeType != "image/svg+xml" && strpos($mimeType, "image/") !== 0) {
        continue;
      }
      $v = [];
      $v["name"] = $file;
      $v["type"] = $mimeType;
      $v["mtime"] = filemtime($fullFilePath);
      $v["url"] = ROOT_URL.$filePath;
      $v["url-images"] = $v["url"]; // alias for $v["url"]
      $v["url-thumbs"] = ROOT_URL.FILES_DIR."/thumbs$path/$file";
      $v["url-preview"] = ROOT_URL.FILES_DIR."/preview$path/$file";
      $v["url-big"] = ROOT_URL.FILES_DIR."/big$path/$file";
      $v["url-full"] = ROOT_URL.FILES_DIR."/full$path/$file";
      $altPath = ltrim("$path/".pathinfo($file, PATHINFO_FILENAME), "/");
      $v["alt"] = preg_replace(
        [
          "~(\d)([a-z])~", // "1.9tdi" to "1.9 tdi"
          "~([a-z])(\d)~", // "file01" to "file 01"
          "~([a-z])-([a-z]|\d)~",
          "~([a-z]|\d)-([a-z])~",
          "~/~",
          "~_~",
          "~ +~",
        ],
        [
          "\\1 \\2",
          "\\1 \\2",
          "\\1 - \\2",
          "\\1 - \\2",
          " / ",
          " ",
          " ",
        ],
        $altPath
      );
      $vars[$filePath] = $v;
    }
    if (empty($vars)) {
      throw new Exception(sprintf(_("No images found in '%s'"), $fileDir));
    }
    return $vars;
  }

}