<?php

class Cms {

  private static $contentFull = null; // HTMLPlus
  private static $content = null; // HTMLPlus
  private static $outputStrategy = null; // OutputStrategyInterface
  private static $variables = array();
  private static $functions = array();
  private static $forceFlash = false;
  private static $flashList = null;
  const DEBUG = false;
  const MSG_ERROR = "Error";
  const MSG_WARNING = "Warning";
  const MSG_INFO = "Info";
  const MSG_SUCCESS = "Success";

  public static function isForceFlash() {
    return self::$forceFlash;
  }

  public static function init() {
    global $plugins;
    if(self::DEBUG) new Logger("DEBUG");
    self::setVariable("messages", self::$flashList);
    self::setVariable("version", CMS_VERSION);
    self::setVariable("name", CMS_NAME);
    self::setVariable("ip", $_SERVER["REMOTE_ADDR"]);
    self::setVariable("admin_id", ADMIN_ID);
    self::setVariable("plugins", array_keys($plugins->getObservers()));
    self::$contentFull = DOMBuilder::buildHTMLPlus(INDEX_HTML);
    $h1 = self::$contentFull->documentElement->firstElement;
    self::setVariable("lang", self::$contentFull->documentElement->getAttribute("xml:lang"));
    self::setVariable("mtime", $h1->getAttribute("mtime"));
    self::setVariable("ctime", $h1->getAttribute("ctime"));
    self::setVariable("author", $h1->getAttribute("author"));
    self::setVariable("authorid", $h1->hasAttribute("authorid") ? $h1->getAttribute("authorid") : null);
    self::setVariable("resp", $h1->getAttribute("resp"));
    self::setVariable("respid", $h1->hasAttribute("respid") ? $h1->getAttribute("respid") : null);
    self::setVariable("host", HOST);
    self::setVariable("url", URL);
    self::setVariable("uri", URI);
    self::setVariable("link", getCurLink());
    if(isset($_GET["PageSpeed"])) self::setVariable("pagespeed", $_GET["PageSpeed"]);
  }

  private static function createFlashList() {
    $doc = new DOMDocumentPlus();
    self::$flashList = $doc->appendChild($doc->createElement("root"));
    $ul = self::$flashList->appendChild($doc->createElement("ul"));
    #$ul->setAttribute("class", "selectable");
    self::setVariable("messages", self::$flashList);
  }

  private static function addFlashItem($message, $type) {
    if(!is_null(self::getLoggedUser())) $message = "$type: $message";
    $li = self::$flashList->ownerDocument->createElement("li");
    self::$flashList->firstElement->appendChild($li);
    $li->setAttribute("class", strtolower($type));
    $li->setAttribute("var", "message");
    $li->processVariables(array("message" => $message));
  }

  private static function getMessages() {
    if(!isset($_SESSION["cms"]["flash"]) || !count($_SESSION["cms"]["flash"])) return;
    if(is_null(self::$flashList)) self::createFlashList();
    foreach($_SESSION["cms"]["flash"] as $type => $item) {
      foreach($item as $i) self::addFlashItem($i, $type);
    }
    $_SESSION["cms"]["flash"] = array();
  }

  public static function getContentFull() {
    return self::$contentFull;
  }

  public static function buildContent() {
    self::getMessages();
    if(is_null(self::$contentFull)) throw new Exception(_("Full content must be set to build content"));
    if(!is_null(self::$content)) throw new Exception(_("Method cannot run twice"));
    self::$content = clone self::$contentFull;
    try {
      $cs = null;
      global $plugins;
      foreach($plugins->getIsInterface("ContentStrategyInterface") as $cs) {
        $c = $cs->getContent(self::$content);
        if(!($c instanceof HTMLPlus))
          throw new Exception(_("Content must be an instance of HTML+"));
        try {
          $c->validatePlus();
        } catch(Exception $e) {
          $c->validatePlus(true);
          new Logger(sprintf(_("HTML+ generated by %s autocorrected: %s"), get_class($cs), $e->getMessage()), "warning");
        }
        self::$content = $c;
      }
      #echo $c->saveXML();die();
    } catch (Exception $e) {
      if(self::DEBUG) echo $c->saveXML();
      throw new Exception($e->getMessage()." (".get_class($cs).")");
    }
  }

  public static function checkAuth() {
    $loggedUser = self::getLoggedUser();
    if(!is_null($loggedUser)) {
      self::setLoggedUser($loggedUser);
      return;
    }
    if(!file_exists(FORBIDDEN_FILE) && SCRIPT_NAME == "index.php") return;
    loginRedir();
  }

  public static function setLoggedUser($user) {
    self::setVariable("logged_user", $user);
    if(!self::isSuperUser()) return;
    self::setVariable("super_user", $user);
    if((session_status() == PHP_SESSION_NONE && !session_start())
      || !session_regenerate_id()) {
      throw new Exception(_("Unable to re/generate session ID"));
    }
    $_SESSION[get_called_class()]["loggedUser"] = $user;
    #$_SESSION["expire"] = time(); #todo + xxx sec;
  }

  public static function isSuperUser() {
    if(IS_LOCALHOST) return true;
    if(self::getLoggedUser() == "admin") return true;
    if(self::getLoggedUser() == ADMIN_ID) return true;
    if(isset($_SERVER["REMOTE_ADDR"])
      && $_SERVER["REMOTE_ADDR"] == $_SERVER['SERVER_ADDR']) return true;
    return false;
  }

  public static function getLoggedUser() {
    if(IS_LOCALHOST) return ADMIN_ID;
    if(isset($_SERVER["REMOTE_ADDR"])
      && $_SERVER["REMOTE_ADDR"] == $_SERVER['SERVER_ADDR']) return "server";
    if(isset($_SERVER['REMOTE_USER']) && strlen($_SERVER['REMOTE_USER']))
      return $_SERVER['REMOTE_USER'];
    if(isset($_SESSION[get_called_class()]["loggedUser"]))
      return $_SESSION[get_called_class()]["loggedUser"];
    return null;
  }

  public static function contentProcessVariables() {
    $oldContent = clone self::$content;
    self::$content->processVariables(self::$variables);
    self::$content->processFunctions(self::$functions, self::$variables);
    try {
      self::$content->validatePlus(true);
    } catch(Exception $e) {
      #echo self::$content->saveXML();die();
      new Logger(sprintf(_("Some variables or functions causing HTML+ error: %s"), $e->getMessage()), Logger::LOGGER_ERROR);
      self::$content = $oldContent;
    }
  }

  public static function processVariables(DOMDocumentPlus $doc) {
    new Logger(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__), Logger::LOGGER_ERROR);
    return $doc;
  }

  private static function insertVar(HTMLPlus $newContent, $varName, $varValue) {
    new Logger(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__), Logger::LOGGER_ERROR);
    return;
    $tmpContent = clone $newContent;
    $tmpContent->insertVar($varName, $varValue);
    $tmpContent->validatePlus();
    $newContent = $tmpContent;
  }

  public static function setOutputStrategy(OutputStrategyInterface $strategy) {
    self::$outputStrategy = $strategy;
  }

  public static function addMessage($message, $type, $flash = false) {
    if(!$flash && self::$forceFlash) {
      new Logger(_("Adding message after output - forcing flash"));
      $flash = true;
    }
    if($flash) {
      if(!Cms::isSuperUser()) new Logger(_("Unable to set flash message if super user not logged"));
      else $_SESSION["cms"]["flash"][$type][] = $message;
      return;
    }
    if(is_null(self::$flashList)) self::createFlashList();
    self::addFlashItem($message, $type);
  }

  public static function getVariable($name) {
    $id = strtolower($name);
    if(!array_key_exists($id, self::$variables)) return null;
    return self::$variables[$id];
  }

  public static function getFunction($name) {
    $id = strtolower($name);
    if(!array_key_exists($id, self::$functions)) return null;
    return self::$functions[$id];
  }

  public static function addVariableItem($name, $value) {
    $varId = self::getVarId($name);
    $var = self::getVariable($varId);
    if(is_null($var)) {
      self::$variables[$varId] = array($value);
      return;
    }
    if(!is_array($var)) $var = array($var);
    $var[] = $value;
    self::$variables[$varId] = $var;
  }

  private static function getVarId($name) {
    $d = debug_backtrace();
    if(!isset($d[2]["class"])) throw new LoggerException(_("Unknown caller class"));
    $varId = strtolower($d[2]["class"]);
    if($varId == $name) return $varId;
    return $varId.(strlen($name) ? "-".normalize($name) : "");
  }

  public static function setFunction($name, $value) {
    if(!$value instanceof Closure) {
      new Logger(sprintf(_("Unable to set function %s: not a function"), $name), Logger::LOGGER_WARNING);
      return null;
    }
    $varId = self::getVarId($name, "fn");
    self::$functions[$varId] = $value;
    return $varId;
  }

  public static function setVariable($name, $value) {
    $varId = self::getVarId($name);
    if(!array_key_exists($varId, self::$variables))
      self::addVariableItem("variables", $varId);
    self::$variables[$varId] = $value;
    return $varId;
  }

  public static function getAllVariables() {
    return self::$variables;
  }

  public static function getAllFunctions() {
    return self::$functions;
  }

  public static function setForceFlash() {
    self::$forceFlash = true;
  }

  public static function getOutput() {
    if(is_null(self::$content)) throw new Exception(_("Content is not set"));
    if(!is_null(self::$outputStrategy)) return self::$outputStrategy->getOutput(self::$content);
    return self::$content->saveXML();
  }

  public static function getOutputStrategy() {
    return self::$outputStrategy;
  }

  public static function applyUserFn($fName, $value) {
    $fn = self::getFunction($fName);
    if(is_null($fn))
      throw new Exception(sprintf(_("Function %s does not exist"), $fName));
    return $fn($value);
  }

}

?>
