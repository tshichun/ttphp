<?php
/**
 * 请求/响应数据操作助手
 */
class Wio {
    /**
     * @var Wio_Base
     */
    protected static $_wio;

    /**
     * 当前请求协议类型
     * @var int
     */
    protected static $_protocal;

    /**
     * 初始化IO对象
     */
    public static function init() {
        if (PHP_SAPI == 'cli') {
            $wio = new Wio_Cli;
            self::$_protocal = Wio_Base::CLI;
        } elseif ($_SERVER['CONTENT_TYPE'] == 'application/x-encrypt') {
            $wio = new Wio_Encrypt;
            self::$_protocal = Wio_Base::ENCRYPT;
        } else {
            $wio = new Wio_Default;
            self::$_protocal = Wio_Base::DEFAULT;
        }
        $wio->input();
        self::$_wio = $wio;
    }

    public static function isProtocal($protocal) {
        return self::$_protocal === $protocal;
    }

    /**
     * 获取解析后的请求数据
     * @param $field
     */
    public static function get($field=null, $default=null) {
        return self::$_wio->getInput($field, $default);
    }

    /**
     * 获取响应数据
     */
    public static function getOutput() {
        return self::$_wio->getOutput();
    }

    /**
     * 设置响应数据
     * @param array $output
     */
    public static function setOutput($output) {
        return self::$_wio->setOutput($output);
    }

    /**
     * 输出生成好的响应数据
     */
    public static function output() {
        self::$_wio->output();
    }

    /**
     * 获取所有自定义HTTP请求头
     */
    public static function getHeaders() {
        $ret = [];
        foreach ($_SERVER as $k=>$v) {
            if (strpos($k, 'HTTP_X_') === 0) {
                $ret[str_replace('_', '-', substr($k, 5))] = $v;
            }
        }
        return $ret;
    }
}

abstract class Wio_Base {
    const CLI = 0;
    const DEFAULT = 1;
    const ENCRYPT = 2;

    /**
     * 请求数据
     * @var array
     */
    protected $_input;

    /**
     * 响应数据
     * @var array
     */
    protected $_output;

    public abstract function input();
    public abstract function output();

    public function getInput($field=null, $default=null) {
        if (!$field) {
            return $this->_input;
        }
        if (strpos($field, '.') === false) {
            return $this->_input[$field] ?? $default;
        }

        $keys = explode('.', $field, 4);
        $val = $this->_input;
        foreach ($keys as $k) {
            if (isset($val[$k])) {
                $val = $val[$k];
            } else {
                return $default;
            }
        }
        return $val;
    }

    public function getOutput() {
        return $this->_output;
    }

    public function setOutput($output=null) {
        $this->_output = $output;
    }
}

class Wio_Default extends Wio_Base {
    public function input() {
        $this->_input = array_merge($_GET, $_POST);
    }

    public function output() {
        if (($data = $this->_output) !== null) {
            headers_sent() or header('Content-Type:application/json');
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }
}

class Wio_Encrypt extends Wio_Base {
    protected $_key;

    public function __construct() {
        $this->_key = C::get('webKey', '@env');
    }

    public function input() {
        $enc = file_get_contents('php://input');
        $dec = F::ungz(F::aesDec($enc, $this->_key), true, false);
        $this->_input = array_merge($_GET, $dec);
    }

    public function output() {
        headers_sent() or header('Content-Type: application/x-encrypt');
        $enc = $this->_output ? F::aesEnc(F::gz($this->_output, false), $this->_key) : false;
        $enc && file_put_contents('php://output', $enc);
    }
}

class Wio_Cli extends Wio_Base {
    public function input() {
        $input = [];
        if (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1]) { //最多只传一个参数
            parse_str($_SERVER['argv'][1], $input);
        }
        $this->_input = $input;
    }

    public function output() {
        //Nothing to do
    }
}
