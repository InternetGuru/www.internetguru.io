<?php

namespace IGCMS\Core;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;

/**
 * Class Logger
 * @package IGCMS\Core
 *
 * @method static debug($msg)
 * @method static info($msg)
 * @method static user_info($msg)
 * @method static user_success($msg)
 * @method static mail($msg)
 * @method static notice($msg)
 * @method static user_notice($msg)
 * @method static warning($msg)
 * @method static user_warning($msg)
 * @method static error($msg)
 * @method static user_error($msg)
 * @method static critical($msg)
 * @method static alert($msg)
 * @method static emergency($msg)
 *
 */
class Logger {
  /**
   * @var string
   */
  const TYPE_SYS_LOG = "sys";
  /**
   * @var string
   */
  const TYPE_USER_LOG = "usr";
  /**
   * @var string
   */
  const TYPE_MAIL_LOG = "eml";

  /**
   * @var string
   */
  const EMAIL_ALERT_TO = "pavel.petrzela@internetguru.cz jiri.pavelka@internetguru.cz";
  /**
   * @var string
   */
  const EMAIL_ALERT_FROM = "no-reply@internetguru.cz";

  /**
   * Monolog logger name, e.g. IGCMS_log.
   * Can be used in LOG_FORMAT as %channel%.
   * @var string
   */
  const LOGGER_NAME = "IGCMS";

  /**
   * Log format for TYPE_SYS_LOG and TYPE_USER_LOG.
   * @var string
   */
  const LOG_FORMAT = "[%datetime%] %extra.ip% %extra.request% %extra.user% %extra.ver% %level_name%: %message% %extra.backtrace%\n";

  /**
   * Log format for TYPE_MAIL_LOG.
   * @var string
   */
  const EMAIL_FORMAT = "[%datetime%] %extra.ip% %extra.request% %extra.user% %extra.ver%: %message%\n";

  /**
   * Monolog system logger instance.
   * @var MonologLogger
   */
  private static $monologsys = null;

  /**
   * Monolog user logger instance.
   * @var MonologLogger
   */
  private static $monologusr = null;

  /**
   * Monolog mail logger instance.
   * @var MonologLogger
   */
  private static $monologeml = null;

  /**
   * The Log levels.
   * @see http://tools.ietf.org/html/rfc5424#section-6.2.1
   * @var array
   */
  private static $levels = [
    'debug' => MonologLogger::DEBUG,
    'info' => MonologLogger::INFO,
    'user_info' => MonologLogger::INFO,
    'user_success' => MonologLogger::INFO,
    'mail' => MonologLogger::INFO,
    'notice' => MonologLogger::NOTICE,
    'user_notice' => MonologLogger::NOTICE,
    'warning' => MonologLogger::WARNING,
    'user_warning' => MonologLogger::WARNING,
    'error' => MonologLogger::ERROR,
    'user_error' => MonologLogger::ERROR,
    'critical' => MonologLogger::CRITICAL,
    'alert' => MonologLogger::ALERT,
    'emergency' => MonologLogger::EMERGENCY,
  ];

  /**
   * @param string $methodName
   * @param array $arguments
   */
  public static function __callStatic ($methodName, $arguments) {
    validate_callstatic($methodName, $arguments, self::$levels, 1);
    $type = self::TYPE_SYS_LOG;
    if (strpos($methodName, "user_") === 0) {
      $methodName = substr($methodName, strlen("user_"));
      $type = self::TYPE_USER_LOG;
    }
    if ($methodName == "success") {
      Cms::success($arguments[0]);
      $methodName = "info";
      $type = self::TYPE_USER_LOG;
    }
    if ($methodName == "mail") {
      $methodName = "info";
      $type = self::TYPE_MAIL_LOG;
    }
    self::writeLog($methodName, $arguments[0], $type);
  }

  /**
   * Write message to Monolog and add Cms message.
   * @param string $level
   * @param string $message
   * @param string $type
   */
  private static function writeLog ($level, $message, $type = self::TYPE_SYS_LOG) {
    $logger = self::getMonolog($type);
    $monologLevel = self::parseLevel($level);
    $logger->{'add'.$level}($message);
    if (!Cms::isSuperUser()) {
      return;
    }
    switch ($monologLevel) {
      case MonologLogger::DEBUG:
      case MonologLogger::INFO:
        return;
      case MonologLogger::NOTICE:
      case MonologLogger::WARNING:
        Cms::$level($message);
        return;
      default:
        Cms::error($message);
    }
  }

  /**
   * Get or create (if not exists) monolog instance for given type.
   * @param  string $type self::TYPE_SYS_LOG or self::TYPE_MAIL_LOG
   * @return MonologLogger
   */
  private static function getMonolog ($type) {
    if (!is_null(self::${"monolog$type"})) {
      return self::${"monolog$type"};
    }
    $logger = new MonologLogger(self::LOGGER_NAME."_$type");
    $logger->pushProcessor("IGCMS\\Core\\Logger::appendIP");
    $logger->pushProcessor("IGCMS\\Core\\Logger::appendRequest");
    $logger->pushProcessor("IGCMS\\Core\\Logger::appendUserID");
    $logger->pushProcessor("IGCMS\\Core\\Logger::appendVersion");
    $logger->pushProcessor("IGCMS\\Core\\Logger::appendDebugTrace");
    self::pushHandlers($logger, $type);
    self::${"monolog$type"} = $logger;
    return $logger;

  }

  /**
   * @param MonologLogger $logger
   * @param string $logType
   */
  private static function pushHandlers (MonologLogger $logger, $logType) {
    $logFile = LOG_FOLDER."/".date("Ymd").".$logType.log";
    $formatter = $logType != self::TYPE_MAIL_LOG
      ? new LineFormatter(self::LOG_FORMAT)
      : new LineFormatter(self::EMAIL_FORMAT);

    foreach (["CRITICAL", "ALERT", "EMERGENCY"] as $type) {
      $mailHandler = new NativeMailerHandler(
        self::EMAIL_ALERT_TO,
        "IGCMS $type at ".HOST,
        self::EMAIL_ALERT_FROM,
        constant("Monolog\\Logger::$type"),
        false
      );
      $mailHandler->setFormatter($formatter);
      $logger->pushHandler($mailHandler);
    }

    $streamHandler = new StreamHandler($logFile, MonologLogger::DEBUG);
    $streamHandler->setFormatter($formatter);
    $logger->pushHandler($streamHandler);

    if (CMS_DEBUG) {
      $chromeHandler = new ChromePHPHandler(MonologLogger::DEBUG);
      $logger->pushHandler($chromeHandler);
    }
  }

  /**
   * Parse the string level into a Monolog constant.
   * @param  string $level
   * @return int
   * @throws Exception
   */
  private static function parseLevel ($level) {
    if (isset(self::$levels[$level])) {
      return self::$levels[$level];
    }
    throw new Exception(sprintf(_('Invalid log level %s'), $level));
  }

  /**
   * Append backtrace to extra field in given log record.
   * @param  array $record
   * @return array
   */
  public static function appendDebugTrace (Array $record) {
    $backtrace = "";
    if (CMS_DEBUG || $record['level'] >= MonologLogger::CRITICAL) {
      $record["extra"]["backtrace"] = array_slice(debug_backtrace(), 6);
    }
    $record["extra"]["backtrace"] = $backtrace;
    return $record;
  }

  /**
   * @param array $record
   * @return array
   */
  public static function appendRequest (Array $record) {
    $request = "UNKNOWN";
    if (isset($_SERVER["REQUEST_METHOD"], $_SERVER["REQUEST_URI"], $_SERVER["SERVER_PROTOCOL"])) {
      $request = $_SERVER["SERVER_PROTOCOL"]." ".$_SERVER["REQUEST_METHOD"]." ".$_SERVER["REQUEST_URI"];
    }
    $record["extra"]["request"] = $request;
    return $record;
  }

  /**
   * @param array $record
   * @return array
   */
  public static function appendVersion (Array $record) {
    $record["extra"]["ver"] = CMS_RELEASE."/".CMS_VERSION;
    return $record;
  }

  /**
   * @param array $record
   * @return array
   */
  public static function appendIP (Array $record) {
    $ip = "0.0.0.0:0000";
    if (isset($_SERVER["REMOTE_ADDR"], $_SERVER["REMOTE_PORT"])) {
      $ip = $_SERVER["REMOTE_ADDR"].":".$_SERVER["REMOTE_PORT"];
    }
    $record["extra"]["ip"] = $ip;
    return $record;
  }

  /**
   * @param array $record
   * @return array
   */
  public static function appendUserID (Array $record) {
    $user = is_null(Cms::getLoggedUser()) ? "unknown" : Cms::getLoggedUser();
    $record["extra"]["user"] = $user;
    return $record;
  }
}

?>
