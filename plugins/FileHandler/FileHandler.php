<?php

class FileHandler extends Plugin implements SplObserver {

  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    Cms::setVariable("cfcurl", getCurLink()."?clearfilecache");
    if(!is_null(Cms::getLoggedUser()) && isset($_GET["clearfilecache"])) {
      if(!Cms::isSuperUser()) new Logger(_("Insufficient rights to purge file cache"), Logger::LOGGER_WARNING);
      try {
        $this->deleteOldResources();
        new Logger(_("File cache successfully purged"), Logger::LOGGER_SUCCESS);
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }
    if(!preg_match("/".FILEPATH_PATTERN."/", getCurLink())) return;
    try {
      if(strpos(getCurLink(), FILES_DIR."/") !== 0 && strpos(getCurLink(), LIB_DIR."/") !== 0
        && strpos(getCurLink(), THEMES_DIR."/") !== 0 && strpos(getCurLink(), PLUGINS_DIR."/") !== 0)
      throw new Exception(_("File illegal path"), 404);
      $this->handleFile();
      redirTo(ROOT_URL.getCurLink());
    } catch(Exception $e) {
      $errno = 500;
      if($e->getCode() != 0) $errno = $e->getCode();
      new ErrorPage(sprintf(_("Unable to handle file request: %s"), $e->getMessage()), $errno);
    }
  }

  private function deleteOldResources() {
    $dirs = array(THEMES_DIR => false, PLUGINS_DIR => false, LIB_DIR => false, FILES_DIR => true);
    $e = null;
    foreach($dirs as $dir => $checkSource) {
      try {
        $this->doDeleteOldResources($dir, $checkSource);
      } catch(Exception $e) {}
    }
    if(!is_null($e)) throw new Exception($e->getMessage());
  }

  private function doDeleteOldResources($folder, $checkSource) {
    $passed = true;
    foreach(scandir($folder) as $f) {
      if(strpos($f, ".") === 0) continue;
      $ff = "$folder/$f";
      if(is_dir($ff)) {
        try {
          $this->doDeleteOldResources($ff, $checkSource);
        } catch(Exception $e) {
          $passed = false;
        }
        continue;
      }
      $filePath = findFile($ff, true, true, false);
      if(!$filePath && $checkSource) $filePath = $this->getSourceFile($ff);
      if(!$filePath || filemtime($ff) != filemtime($filePath)) {
        if(!unlink($ff)) $passed = false;
      }
    }
    if(!$passed) throw new Exception(_("Failed to purge file cache"));
  }

  private function getSourceFile($dest, &$mode=null) {
    $src = !is_null($mode) ? findFile($dest) : findFile($dest, true, true, false);
    $pLink = explode("/", $dest);
    if(count($pLink) > 2) {
      if(!is_null($mode)) $mode = $pLink[1];
      if($src === false) {
        unset($pLink[1]);
        $dest = implode("/", $pLink);
        $src = !is_null($mode) ? findFile($dest) : findFile($dest, true, true, false);
      }
    }
    return $src;
  }

  private function handleFile() {
    $dest = getCurLink();
    $mode = "";
    $src = $this->getSourceFile($dest, $mode);
    if(!$src) throw new Exception(_("Requested URL not found on this server"), 404);
    $fp = lockFile($src);
    try {
      if(is_file($dest)) return;
      $mimeType = getFileMime($src);
      if($mimeType != "image/svg+xml" && strpos($mimeType, "image/") === 0) {
        $modes = array(
          "" => array(1000, 1000, 250*1024, 85), // default, e.g. resources like icons
          "images" => array(1000, 1000, 250*1024, 85),
          "preview" => array(500, 500, 150*1024, 85),
          "thumbs" => array(200, 200, 50*1024, 85),
          "big" => array(1500, 1500, 400*1024, 75),
          "full" => array(0, 0, 0, 0)
        );
        if(!isset($modes[$mode])) $mode = "";
        $this->handleImage(realpath($src), $dest, $modes[$mode]);
        return;
      }
      $registeredMime = array(
        "inode/x-empty" => array(), // empty file with any ext
        "text/plain" => array("css", "js"),
        "text/x-c" => array("js"),
        "application/x-elc" => array("js"),
        "application/x-empty" => array("css", "js"),
        "application/octet-stream" => array("woff", "js"),
        "image/svg+xml" => array("svg"),
        "application/pdf" => array("pdf"),
        "application/vnd.ms-fontobject" => array("eot"),
        "application/x-font-ttf" => array("ttf"),
        "application/vnd.ms-opentype" => array("otf"),
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => array("docx"),
      );
      $ext = pathinfo($src, PATHINFO_EXTENSION);
      if(!isset($registeredMime[$mimeType]) || (!empty($registeredMime[$mimeType]) && !in_array($ext, $registeredMime[$mimeType])))
        throw new Exception(sprintf(_("Unsupported mime type %s"), $mimeType), 415);
      copy_plus($src, $dest, true);
    } catch(Exception $e) {
      throw $e;
    } finally {
      unlockFile($fp);
    }
  }

  private function handleImage($src, $dest, $mode) {
    $i = $this->getImageSize($src);
    if($i[0] <= $mode[0] && $i[1] <= $mode[1]) {
      $fileSize = filesize($src);
      if($fileSize > $mode[2])
        throw new Exception(sprintf(_("Image size %s is over limit %s"), fileSizeConvert($fileSize), fileSizeConvert($mode[2])));
      copy_plus($src, $dest);
      return;
    }
    if($mode[0] == 0 && $mode[1] == 0) {
      copy_plus($src, $dest);
      return;
    }
    $im = new Imagick($src);
    $im->setImageCompressionQuality($mode[3]);
    if($i[0] > $i[1]) $result = $im->thumbnailImage($mode[0], 0);
    else $result = $im->thumbnailImage(0, $mode[1]);
    #var_dump($im->getImageLength());
    $imBin = $im->__toString();
    if(!$result || !strlen($imBin))
      throw new Exception(_("Unable to resize image"));
    if(strlen($imBin) > $mode[2])
      throw new Exception(_("Generated image size %s is over limit %s"), fileSizeConvert(strlen($imBin)), fileSizeConvert($mode[2]));
    mkdir_plus(dirname($dest));
    $b = file_put_contents($dest, $imBin);
    if($b === false || !touch($dest, filemtime($src))) throw new Exception(_("Unable to create file"));
  }

  private function getImageSize($imagePath) {
    $i = @getimagesize($imagePath);
    if(is_array($i)) return $i;
    throw new Exception(_("Failed to get image dimensions"));
  }

}
