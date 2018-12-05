<?php
/**
 * 通用函数
 */
class F {
    /**
     * 生成唯一ID
     * +-----+---+------+---------+----------+
     * | bit | 1 |  41  |    4    |    18    |
     * +-----+---+------+---------+----------+
     * | val | - | time | machine | sequence |
     * +-----+---+------+---------+----------+
     * @return string uint64
     */
    public static function id() {
        $time = floor(microtime(true) * 1000);

        $start = 1482710400000;
        $min41 = 1099511627776;
        ($time >= $start) && ($time -= $start);
        $base = decbin($min41 + $time);

        $swid = str_pad(decbin(min(max(0, 32), 15)), 4, '0', STR_PAD_LEFT);
        $rand = str_pad(decbin(mt_rand(0, 262143)), 18, '0', STR_PAD_LEFT);

        $id = bindec($base . $swid . $rand);
        return number_format($id, 0, '', '');
    }

    /**
     * 获取IP
     * @return int|string
     */
    public static function ip($long=true) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
        $ip or ($ip = $_SERVER['REMOTE_ADDR']);
        if ($ip) {
            return $long ? (int) sprintf('%u', ip2long($ip)) : $ip;
        } else {
            return $long ? 0 : '0.0.0.0';
        }
    }

    public static function isLocalIp($ip=null) {
        $ip or ($ip = self::ip(false));
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        return (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false);
    }

    /**
     * 生成随机串
     */
    public static function randStr($len=5) {
        $src = '345678ABCDEFGHJKLMNPRSTUVWYZabcdefhjkmnprstuvwyz';
        $max = strlen($src) - 1;
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $k = mt_rand(0, $max);
            $str .= $src{$k};
        }
        return $str;
    }

    /**
     * 转义HTML
     */
    public static function escapeHtml($val) {
        if (is_array($val)) {
            foreach ($val as $k=>$v) {
                $val[$k] = self::escapeHtml($val, $del);
            }
        } else {
            $val = preg_replace('/&amp;((#(\d{3,5}|x[a-fA-F0-9]{4})|[a-zA-Z][a-z0-9]{2,5});)/', '&\\1',
                str_replace(['&', '"', '<', '>'], ['&amp;', '&quot;', '&lt;', '&gt;'], $val));
        }
        return $val;
    }

    /**
     * RSA加解密钥格式化
     */
    public static function rsaKey($key, $type) {
        if (!$key) {
            return '';
        }
        if ($type == 'PUB') { //公钥
            $prefix = '-----BEGIN PUBLIC KEY-----';
            $suffix = '-----END PUBLIC KEY-----';
        } else { //私钥
            $type && ($type .= ' ');
            $prefix = "-----BEGIN {$type}PRIVATE KEY-----";
            $suffix = "-----END {$type}PRIVATE KEY-----";
        }
        $key = PHP_EOL . wordwrap($key, 64, PHP_EOL, true) . PHP_EOL;
        return $prefix . $key . $suffix;
    }

    /**
     * RSA加密
     */
    public static function rsaEnc($data, $key, $type='PUB') {
        if (!$data || (!$key = self::rsaKey($key, $type))) {
            return false;
        }
        $ok = ($type == 'PUB' ? openssl_public_encrypt($data, $enc, $key) :
                    openssl_private_encrypt($data, $enc, $key));
        return $ok === true ? base64_encode($enc) : false;
    }

    /**
     * RSA解密
     */
    public static function rsaDec($data, $key, $type='RSA') {
        if (!$data || (!$key = self::rsaKey($key, $type))
            || (!$data = base64_decode(str_replace(' ', '+', $data)))) {
            return false;
        }
        $ok = ($type == 'PUB' ? openssl_public_decrypt($data, $dec, $key) :
                    openssl_private_decrypt($data, $dec, $key));
        return $ok === true ? $dec : false;
    }

    /**
     * AES加解密密钥
     */
    public static function aesKey($key) {
        return $key ? [
            'secret' => substr(md5($key), 8, 16),
            'vector' => '1234567890000000',
            'method' => 'AES-128-CBC'
        ] : [];
    }

    /**
     * AES加密
     */
    public static function aesEnc($data, $key) {
        if (!$data || (!$keys = self::aesKey($key))) {
            return false;
        }
        return openssl_encrypt($data, $keys['method'], $keys['secret'], false, $keys['vector']);
    }

    /**
     * AES解密
     */
    public static function aesDec($data, $key) {
        if (!$data || (!$keys = self::aesKey($key))
            || (!$data = base64_decode(str_replace(' ', '+', $data)))) {
            return false;
        }
        return openssl_decrypt($data, $keys['method'], $keys['secret'], true, $keys['vector']);
    }

    public static function formatNumber($number) {
        return number_format($number);
    }

    public static function formatMoney($money, $lang=null) {
        if (($m = (int) $money) == 0) {
            return '';
        }

        $p = '';
        $w = 10000;
        $b = 100000000;
        if ($m < 0) {
            $p = '-';
            $m = abs($m);
        }        
        if ($m >= $w) {
            if($m < $b) {
                $m = $p . (floor(($m / $w) * 100) / 100);
                return C::lang("money_{$w}", 'base', [$m], $lang);
            } else {
                $m = $p . (floor(($m / $b) * 100) / 100);
                return C::lang("money_{$b}", 'base', [$m], $lang);
            }
        }
        return $p . $m;
    }

    /**
	 * GZ压缩数据
	 * @param string|array $data
	 * @return string
	 */
	public static function gz($data, $b64=true) {
		if ($data !== null) {
			$data = gzcompress(is_array($data) ? json_encode($data) : $data, 9);
			return $b64 ? base64_encode($data) : $data;
		} else {
			return '';
		}
	}

	public static function ungz($str, $arr=true, $b64=true) {
		if ($str && is_string($str)) {
			$b64 && ($str = base64_decode($str));
			if (($str !== false) && (($str = gzuncompress($str)) !== false)) {
				return $arr ? json_decode($str, true) : $str;
			}
		}

        return false;
	}

    public static function mapIndex($keys, $vals) {
        $ret = [];
        foreach ($keys as $i=>$k) {
            if (array_key_exists($k, $vals) && ($vals[$k] !== null) && ($vals[$k] !== false)) {
                $ret[$i] = $vals[$k];
            }
        }
        return $ret;
    }

    public static function mapField($keys, $vals) {
        $ret = [];
        foreach ($keys as $i=>$k) {
            if (array_key_exists($i, $vals) && ($vals[$i] !== null) && ($vals[$i] !== false)) {
                $ret[$k] = $vals[$i];
            }
        }
        return $ret;
    }

    /**
     * 转换[{k=>1,s=>S,...},...]到{k=>{s=>S,...},...}
     * @param array $list
     * @param string $key
     * @param string $glue
     * @return array
     */
    public static function list2kvs($list, $key, $glue=false) {
        if (!$key || !is_array($list)) {
            return [];
        }

        $kvs = [];
        foreach ($list as $v) {
            if (isset($v[$key])) {
                $k = $v[$key];
                unset($v[$key]);
                $kvs[$k] = $glue !== false ? implode($glue, $v) : $v;
            }
        }

        return $kvs;
    }

    public static function list2tree($list) {
        $tree = [];
        foreach ($list as $v) {
            $tree[$v['id']] = $v;
            $tree[$v['id']]['list'] = []; //子类
        }
        foreach ($tree as $k=>$v) {
            if ($v['pid']) {
                $tree[$v['pid']]['list'][] = &$tree[$k];
                if (!$v['list']) {
                    unset($tree[$k]['list']);
                }
            }
        }
        foreach ($tree as $k=>$v) {
            if ($v['pid']) { //非根
                unset($tree[$k]);
            }
        }
        return $tree;
    }

    public static function curlPost($url, $post=null, $opts=null, &$error=false, &$errno=false, &$code=false) {
        $defs = [
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => !$post || is_string($post) ? $post : http_build_query($post),
        ];
        if (!$ch = curl_init()) {
            return '';
        }
        if ($opts && is_array($opts)) {
            foreach ($opts as $k=>$v) {
                $defs[$k] = $v;
            }
        }
        curl_setopt_array($ch, $defs);
        $ret = curl_exec($ch);
        if ($ret === false) {
            if ($error !== false) {
                $error = curl_error($ch);
            }
            if ($errno !== false) {
                $errno = curl_errno($ch);
            }
        }
        if ($code !== false) {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);

        return $ret;
    }

    public static function curlGet($url, $get=null, $opts=null, &$error=false, &$errno=false, &$code=false) {
        $defs = [
            CURLOPT_URL => $url . (strpos($url, '?') === false ? '?' : '&') . (is_array($get) ? http_build_query($get) : (string) $get),
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ];
        if (!$ch = curl_init()) {
            return '';
        }
        if ($opts && is_array($opts)) {
            foreach ($opts as $k=>$v) {
                $defs[$k] = $v;
            }
        }
        curl_setopt_array($ch, $defs);
        $ret = curl_exec($ch);
        if ($ret === false) {
            if ($error !== false) {
                $error = curl_error($ch);
            }
            if ($errno !== false) {
                $errno = curl_errno($ch);
            }
        }
        if ($code !== false) {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);

        return $ret;
    }

    public static function setCookie($name, $value='', $expire=0) {
        $path = '';
        $domain = C::get('cookieDomain', '@env');
        $secure = (App::$env == 'dev' ? false : true);
        return setcookie($name, $value, $expire, $path, $domain, $secure, true);
    }

    public static function getCookie($name) {
        return $_COOKIE[$name];
    }
}

/**
 * 配置操作
 */
class C {
    protected static $_cache = [];

    public static function get($key=null, $path='base') {
        return self::_read($path, $key, 'cfg');
    }

    /**
     * 获取语言包内容
     * @param string $key 在语言包的键
     * @param string $path 语言包名称
     * @param array $vars 占位符替换值列表
     * @param string $lang 语言类型 默认 App::$lang
     */
    public static function lang($key=null, $path='base', $vars=null, $lang=null) {
        $lang or ($lang = App::$lang);
        $val = self::_read($path, $key, 'lang/' . $lang);
        if ($vars && $val && is_string($val)) { //替换占位符
            $keys = array_keys($vars);
            foreach ($keys as $k=>$v) {
                $keys[$k] = '{'.$v.'}';
            }
            $val = str_replace($keys, $vars, $val);
        }
        return $val;
    }

    protected static function _read($path, $key, $type) {
        $val = self::_load($path, $type);
        $keys = $key ? explode('.', $key) : [];
        foreach ($keys as $k) {
            if (isset($val[$k])) {
                $val = $val[$k];
            } else {
                return null;
            }
        }
        return $val;
    }

    protected static function _load($path, $type) {
        if ($path{0} == '@') {
            $path = substr($path, 1) . '.' . App::$env;
        }
        $path = $type . '/' . $path;

        if (!isset(self::$_cache[$path])) {
            $file = App::$path . $path . '.php';
            self::$_cache[$path] = file_exists($file) ? include($file) : [];
        }
        return self::$_cache[$path];
    }
}

/**
 * 对象生成
 * 模型类对象自动生成
 * 其他须定义生成方法
 */
class M {
    protected static $_objects = [];

    public static function getObjects() {
        return self::$_objects;
    }

    protected static function _getObject($class, $args=null, $argk=0) {
        if (isset(self::$_objects[$class][$argk])) {
            return self::$_objects[$class][$argk];
        }
        if (!class_exists($class)) {
            return null;
        }
        $object = $args ? new $class(...$args) : new $class();
        return (self::$_objects[$class][$argk] = $object);
    }

    /**
     * 自动生成模型类对象,无须在M中定义生成方法
     */
    public static function __callStatic($class, $args) {
        return self::_getObject('Model_' . $class, $args);
    }

    /**
     * 日志操作类
     * @return Log
     */
    public static function log(...$args) {
        return self::_getObject('Log', $args, $args ? implode('_', $args) : 0);
    }

    /**
     * 获取MySQL操作对象
     * @return Library_MySQL
     */
    public static function db($name) {
        if (!$args = C::get('mysql.' . $name, '@env')) {
            return null;
        } else {
            $argk = $args[0] . ':' . $args[1]; //按host和port划分实例
            return self::_getObject('Library_MySQL', $args, $argk);
        }
    }

    /**
     * 获取Redis操作对象
     * @return Library_Redis
     */
    public static function redis($name) {
        if (!$args = C::get('redis.' . $name, '@env')) {
            return null;
        } else {
            $argk = $args[0] . ':' . $args[1]; //按host和port划分实例
            return self::_getObject('Library_Redis', $args, $argk);
        }
    }

    /**
     * 获取Socket操作对象
     * @return Library_Socket
     */
    public static function socket() {
        return self::_getObject('Library_Socket');
    }

    /**
     * 获取Server操作对象
     * @return Library_Server_{$name}
     */
    public static function server($name) {
        if (!$args = C::get('server.' . $name, '@env')) {
            return null;
        } else {
            $argk = $args[0] . ':' . $args[1]; //按host和port划分实例
            return self::_getObject('Library_Server_' . $name, $args, $argk);
        }
    }

    /**
     * 获取SDK对象
     * @return Library_Sdk_$module_{$name}
     */
    public static function sdk($name, $module, ...$args) {
        return self::_getObject("Library_Sdk_{$module}_{$name}", $args, $args ? implode('_', $args) : 0);
    }
}
