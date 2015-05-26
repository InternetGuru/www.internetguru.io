<?php

class LogViewer extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $logFiles;
  private $mailFiles;
  private $verFiles;
  private $curFilePath;
  private $curFileName;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 5);
    if(self::DEBUG) Logger::log("DEBUG");
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PREINIT) {
      if(!Cms::isSuperUser()) $subject->detach($this);
      return;
    }
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
    }
    if($subject->getStatus() != STATUS_INIT) return;
    $this->logFiles = $this->getFiles(LOG_FOLDER, 15, "log");
    $this->mailFiles = $this->getFiles(LOG_FOLDER, 15, "mail");
    $this->verFiles = $this->getFiles(VER_FOLDER);
  }

  public function getContent(HTMLPlus $content) {
    $fName = $_GET[get_class($this)];
    try {
      $fPath = $this->getCurFilePath($fName);
      $vars["content"] = htmlspecialchars($this->file_get_contents($fPath));
    } catch(Exception $e) {
      Cms::addMessage($e->getMessage(), Cms::MSG_ERROR);
    }
    $newContent = $this->getHTMLPlus();
    $vars["cur_file"] = $fName;
    $lf = $this->makeLink($this->logFiles);
    $vars["log_files"] = empty($lf) ? null : $lf;
    $mlf = $this->makeLink($this->mailFiles);
    $vars["log_mailfiles"] = empty($mlf) ? null : $mlf;
    $vars["ver_files"] = $this->makeLink($this->verFiles);
    $newContent->processVariables($vars);
    return $newContent;
  }

  private function getCurFilePath($fName) {
    $fPath = null;
    switch($fName) {
      case "ver":
      reset($this->verFiles);
      $this->redirTo(key($this->verFiles));
      break;
      case "":
      case "log":
      reset($this->logFiles);
      $this->redirTo(key($this->logFiles));
      break;
      default:
      $fPath = $this->getFilePath($fName);
      if(is_null($fPath)) throw new Exception (sprintf(_("File or extension '%s' not found"), $fName));
    }
    return $fPath;
  }

  private function redirTo($fName) {
    redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => get_class($this)."=$fName")));
  }

  private function makeLink(Array $array) {
    $links = array();
    foreach($array as $name => $path) {
      $links[] = "<a href='".getCurLink()."?".get_class($this)."=$name'>$name</a>";
    }
    return $links;
  }

  private function file_get_contents($file) {
    if(substr($file, -4) != ".zip") return file_get_contents($file);
    else return readZippedFile($file, substr(pathinfo($file, PATHINFO_BASENAME), 0, -4));
  }

  private function getFilePath($fName) {
    if(is_file(LOG_FOLDER."/$fName")) return LOG_FOLDER."/$fName";
    if(is_file(VER_FOLDER."/$fName")) return VER_FOLDER."/$fName";
    if(is_file(LOG_FOLDER."/$fName.zip")) return LOG_FOLDER."/$fName.zip";
    if(is_file(VER_FOLDER."/$fName.zip")) return VER_FOLDER."/$fName.zip";
    return null;
  }

  private function getFiles($dir, $limit=0, $ext=null) {
    $files = array();
    foreach(scandir($dir, SCANDIR_SORT_DESCENDING) as $f) {
      if(!is_file("$dir/$f")) continue;
      if(!is_null($ext) && pathinfo($f, PATHINFO_EXTENSION) != $ext) continue;
      $id = (substr($f, -4) == ".zip") ? substr($f, 0, -4) : $f;
      $files[$id] = "$dir/$f";
      if(count($files) == $limit) break;
    }
    return $files;
  }

}

?>
