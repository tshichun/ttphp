<?php
/**
 * 应用核心类
 */
class App {
    /**
     * 根目录
     * @var string
     */
    public static $path;

    /**
     * 运行环境 dev,pre,pro
     * @var string
     */
    public static $env;

    /**
     * 默认语言包名称
     * @var string
     */
    public static $lang;

    /**
     * 是否打开调试模式
     * @var int
     */
    public static $debug;

    /**
     * 请求到达时间
     * @var float
     */
    public static $rtime;

    /**
     * 请求路由(接口都在api目录下)
     * @var array [模块,控制器,动作]
     */
    public static $route;

    /**
     * 请求处理入口
     * WEB: rewrite ^/api(.*)?(.*)$ /index.php?r=$1$2 last;
     * CLI: php index.php 'r=(.*)&(.*)'
     */
    public static function run($path, $env) {
        self::$path = $path;
        self::$env = $env;
        self::$rtime = microtime(true);

        require($path . 'library/api.php');
        require($path . 'library/kit.php');
        require($path . 'library/log.php');
        require($path . 'library/wio.php');

        error_reporting(E_ALL ^ E_NOTICE);
        set_error_handler(['App', 'handleError'], E_ALL ^ E_NOTICE);
        register_shutdown_function(['App', 'handleShutdown']);
        set_exception_handler(['App', 'handleException']);

        spl_autoload_register(['App', 'autoload']);

        $cfg = C::get(null, '@env'); //加载环境配置
        if (!$cfg || !$cfg['lang']) {
            die('ENV_CFG_ERR');
        }

        self::$lang = $cfg['lang'];
        self::$debug = $cfg['debug'];

        define('_DBPRE_', $cfg['dbpre']);
        mb_internal_encoding('UTF-8');
        date_default_timezone_set($cfg['timezone']);
        set_time_limit(10);
        ini_set('open_basedir', $path . ':/tmp/');
        umask(002);

        Wio::init();

        self::$route = self::route();
        $class = 'Api_';
        self::$route[0] && ($class .= self::$route[0] . '_'); //分子目录
        $class .= self::$route[1];
        if (class_exists($class)) {
            $obj = new $class;
            $obj->_run_(self::$route[2]);
        }

        self::$debug && M::log()->debug(['HEADERS', Wio::getHeaders(), 'ROUTE', self::$route, 'INPUT', Wio::get(), 'OUTPUT', Wio::getOutput()]);
    }

    /**
     * 解析路由
     */
    public static function route() {
        if ($r = trim(Wio::get('r'), '/')) {
            $r = explode('/', $r, 5); //限定最大目录层级数
            $n = count($r);
            if ($n == 1) {
                $a = 'index'; //默认方法
                $c = $r[0]; //控制器
            } else {
                $a = array_pop($r); //指定方法
                $c = array_pop($r);
                $m = $n > 3 ? implode('_', $r) : $r[0]; //模块
                ($a{0} == '_') && ($a = 'index'); //禁止访问下划线开头的方法
            }
        } else {
            $a = $c = 'index';
            $m = null;
        }

        return [$m, $c, $a];
    }

    public static function handleError($errno, $errstr, $errfile=null, $errline=null) {
        App::handleException(new ErrorException($errstr, $errno, 0, $errfile, $errline));
    }

    public static function handleShutdown() {
        static $_shutdown_count_ = 0;
        if ($_shutdown_count_++) {
            exit;
        }

        $error = error_get_last();
        $fatal = [E_PARSE, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if (!in_array($error['type'], $fatal)) {
            return;
        }

        $e = new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']);
        App::handleException($e);
    }

    public static function handleException($e) {
        $errno = $e->getCode();
        $error = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();

        $logs = [];
        $logs[] = "[{$errno}]{$file}@{$line}";
        $logs[] = $_SERVER['REMOTE_ADDR'] ?? '-';
        $logs[] = self::$route ? implode('/', self::$route) : '-';
        $logs[] = $_SERVER['REQUEST_TIME'];
        $logs[] = $error;
        M::log()->error(implode('#', $logs));
        die('SYS_ERR');
    }

    public static function autoload($class) {
        if($pos = strpos($class, '_')) { //常规类名规则
            $type = substr($class, 0, $pos);
            $auto = [
                'Api' => 'api/',
                'Library' => 'library/',
                'Model' => 'model/',
            ];
            if ($auto[$type]) {
                $file = str_replace('_', '/', strtolower($class));
                $file = App::$path . $file . '.php';
                is_readable($file) && require($file);
            }
        }
    }
}
