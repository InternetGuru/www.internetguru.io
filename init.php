<?php

require __DIR__.'/vendor/autoload.php';

session_cache_limiter("");

define("SERVER_USER", "server");
define("INDEX_HTML", "index.html");
define("FINDEX_PHP", "findex.php");
define("INOTIFY", ".inotify");
define("NGINX_CACHE_FOLDER", "/var/cache/nginx");
define("PLUGINS_DIR", "plugins");
define("THEMES_DIR", "themes");
define("RESOURCES_DIR", "res");
define("CMS_DIR", "cms");
define("CMSRES_DIR", "cmsres");
define("VER_DIR", "ver");
define("LIB_DIR", "lib");
define("VENDOR_DIR", "vendor");
define("CORE_DIR", "core");
define("FILES_DIR", "files");
define("SERVER_FILES_DIR", "_server");
define("LOG_DIR", "log");
define("DEBUG_FILE", "DEBUG");
define("HTTPS_FILE", "HTTPS");
define('CACHE_PARAM', "Cache");
define('CACHE_IGNORE', "ignore");
define('CACHE_FILE', "file");
define('CACHE_NGINX', "nginx");
define('PAGESPEED_PARAM', "PageSpeed");
define('PAGESPEED_OFF', "off");
define('DEBUG_PARAM', "Debug");
define('DEBUG_ON', "on");
define("FORBIDDEN_FILE", "FORBIDDEN");
define("ADMIN_ROOT_DIR", "admin");
define("USER_ROOT_DIR", "user");
define("FILE_LOCK_WAIT_SEC", 4);
define('W3C_DATETIME_PATTERN', '(19|20)\d\d(-(0[1-9]|1[012])(-(0[1-9]|[12]\d|3[01])(T([01]\d|2[0-3]):[0-5]\d:[0-5]\d[+-][01]\d:00)?)?)?');
define('EMAIL_PATTERN', '([_a-zA-Z0-9-]+(?:\.[_a-zA-Z0-9-]+)*)@([a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*)\.([a-zA-Z]{2,})');
define('SUBDOM_PATTERN', '[a-z][a-z0-9]*');
define('VARIABLE_PATTERN', '(?:[a-z]+-)?[a-z0-9_-]+');
#define('FILEPATH_PATTERN', '(?:[a-zA-Z0-9_-][a-zA-Z0-9._-]*\/)*[a-zA-Z0-9_-][a-zA-Z0-9._-]*\.[a-zA-Z0-9]{2,4}');
define('FILEPATH_PATTERN', '(?:[.a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-zA-Z0-9]{2,4}');
define('FILE_HASH_ALGO', 'crc32b');
define('SCRIPT_NAME', basename($_SERVER["SCRIPT_NAME"]));
define('STATUS_PREINIT', 'preinit');
define('STATUS_INIT', 'init');
define('STATUS_PROCESS', 'process');
define('STATUS_POSTPROCESS', 'postprocess');
define('APC_PREFIX', 2); // change if APC structure changes
define('HOST', basename(getcwd()));
$hostArr = explode(".", HOST);
define('DOMAIN', $hostArr[count($hostArr)-2].".".$hostArr[count($hostArr)-1]);
define('CURRENT_SUBDOM', substr(HOST, 0, -(strlen(DOMAIN)+1)));
define('ROOT_URL', "/");
define('CMS_RELEASE', basename(dirname(__FILE__)));
define("WWW_FOLDER", "/var/www");
define("CMS_ROOT_FOLDER", WWW_FOLDER."/".CMS_DIR);
define("CMS_FOLDER", CMS_ROOT_FOLDER."/".CMS_RELEASE);
define("CMSRES_FOLDER", WWW_FOLDER."/".CMSRES_DIR."/".CMS_RELEASE);
define('ADMIN_ID', is_file("ADMIN") ? trim(file_get_contents("ADMIN")) : null);
define('ADMIN_ROOT_FOLDER', WWW_FOLDER."/".ADMIN_ROOT_DIR);
define('USER_ROOT_FOLDER', WWW_FOLDER."/".USER_ROOT_DIR);
define('ADMIN_FOLDER', ADMIN_ROOT_FOLDER."/".HOST);
define('USER_FOLDER', USER_ROOT_FOLDER."/".ADMIN_ID."/".HOST);
define('LOG_FOLDER', WWW_FOLDER."/".LOG_DIR."/".HOST);
define('CMS_DEBUG', is_file(DEBUG_FILE));
define("SCHEME", (@$_SERVER["HTTPS"] == "on" ? "https" : "http"));
define("URL", SCHEME."://".HOST);
define("URI", URL.(isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : ""));
define("CORE_FOLDER", CMS_FOLDER."/".CORE_DIR);
define('PLUGINS_FOLDER', CMS_FOLDER."/".PLUGINS_DIR);
define('LIB_FOLDER', CMS_FOLDER."/".LIB_DIR);
define('VER_FOLDER', CMS_FOLDER."/".VER_DIR);
define('THEMES_FOLDER', USER_FOLDER."/".THEMES_DIR);
define('FILES_FOLDER', USER_FOLDER."/".FILES_DIR);
define('CMS_VERSION_FILENAME', "VERSION");
define('CMS_CHANGELOG_FILENAME', "CHANGELOG.md");
define('CMS_VERSION', trim(file_get_contents(CMS_FOLDER."/".CMS_VERSION_FILENAME)));
$verfile = getcwd()."/".CMS_VERSION_FILENAME;
define('DEFAULT_RELEASE', is_file($verfile) ? trim(file_get_contents($verfile)) : CMS_RELEASE);
define('CMS_NAME', "IGCMS ".CMS_RELEASE."/".CMS_VERSION.(CMS_DEBUG ? " DEBUG" : ""));
#print_r(get_defined_constants(true)); die();
date_default_timezone_set("Europe/Prague");
#todo: localize lang

if(CMS_DEBUG) {
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
  setlocale(LC_ALL, "en_US.UTF-8");
  putenv("LANG=en_US.UTF-8"); // for gettext
} else {
  setlocale(LC_ALL, "cs_CZ.UTF-8");
  #else setlocale(LC_ALL, "czech");
  putenv("LANG=cs_CZ.UTF-8"); // for gettext
  bindtextdomain("messages", LIB_FOLDER."/locale");
  textdomain("messages");
}

define('METHOD_NA', _("Method %s is no longer available"));
if(is_null(ADMIN_ID)) die(_("Domain is ready to be acquired"));
require_once(CORE_FOLDER.'/globals.php');
if(isset($_GET["login"]) && SCHEME == "http") loginRedir();
if(update_file(CMS_FOLDER."/".SERVER_FILES_DIR."/".SCRIPT_NAME, SCRIPT_NAME)
  || update_file(CMS_FOLDER."/".SERVER_FILES_DIR."/".FINDEX_PHP, FINDEX_PHP)) {
  redirTo($_SERVER["REQUEST_URI"], null, _("Root file(s) updated"));
}
initDirs();

?>
