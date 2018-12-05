<?php
/**
 * 日志操作
 */
class Log {
    const DEBUG = 0;
    const INFO = 1;
    const NOTICE = 2;
    const WARN = 3;
    const ERROR = 4;

    public static $levels = [
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::NOTICE => 'NOTICE',
        self::WARN => 'WARN',
        self::ERROR => 'ERROR',
    ];

    /**
     * 志文件大小(兆)
     * @var int
     */
    protected $_size;

    /**
     * 自动操作类型
     * 0x01:超过文件大小限制时自动备份
     * @var int
     */
    protected $_auto;

    public function __construct($size=2, $auto=0x00) {
        $this->_size = $size * 1024 * 1024;
        $this->_auto = $auto;
    }

    public function debug($data, $file='debug') {
        return App::$debug ? $this->_write(self::DEBUG, $data, $file) : false;
    }

    public function info($data, $file='info') {
        return $this->_write(self::INFO, $data, $file);
    }

    public function notice($data, $file='notice') {
        return $this->_write(self::NOTICE, $data, $file);
    }

    public function warn($data, $file='warn') {
        return $this->_write(self::WARN, $data, $file);
    }

    public function error($data, $file='error') {
        $trace = $this->_backtrace();
        return $this->_write(self::ERROR, $data, $file, $trace);
    }

    protected function _write($level, $data, $file, $trace=null) {
        $logs = '[' . date('Y/m/d H:i:s') . '][' . self::$levels[$level] . ']';
        $logs .= is_scalar($data) ? $data : var_export($data, true);
        $trace && ($logs .= var_export($trace, true));
        $logs .= "\n";

        $file = App::$path . "var/log/{$file}.php";
        $path = dirname($file);

        clearstatcache();
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        $size = file_exists($file) ? filesize($file) : 0;
        $less = $size < $this->_size;
        if ((0x01 & $this->_auto) && !$less) { //自动备份
            $path_bak = $path . '/bak';
            if (!is_dir($path_bak)) {
                mkdir($path_bak, 0775, true);
            }
            $file_bak = $path_bak . strrchr($file, '/') . '-' . date('YmdHis') . '.php';
            copy($file, $file_bak);
        }
        $head = $size && $less ? '' : "<?php die; ?>\n";
        return file_put_contents($file, $head . $logs, $less ? FILE_APPEND : 0);
    }

    protected function _backtrace($limit=9) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
        array_shift($trace); //跳过内部调用
        foreach ($trace as $k=>$v) {
            $file = $v['file'] ?? '';
            $line = $v['line'] ?? '';
            $trace[$k] = "{$file}@{$line}:{$v['function']}";
        }
        return $trace;
    }
}
