<?php
/**
 * 阿里云OSS-WEB直传+上传回调
 * 新建专用上传用户:
 *  a. 创建RAM用户(RAM控制台-用户管理)
 *  b. 为用户创建AK并保存到本地(RAM控制台-用户管理-创建accessKeyId)
 *  c. 创建自定义授权策略(RAM控制台-策略管理), 例如创建AliyunOSSPostOnlyAccess
 *  d. 将创建好的自定义策略授权给用户(RAM控制台-用户管理-授权)
 *
 * 上传回调
 * https://help.aliyun.com/document_detail/31989.html
 *
 * 上传回调错误及排除
 * https://help.aliyun.com/document_detail/50092.html
 *
 * 上传回调示例
 * http://oss-demo.aliyuncs.com/oss-h5-upload-js-php-callback/index.html
 *
 * PostObject
 * https://help.aliyun.com/document_detail/31988.html
 *
 * 示例解析
 * http://www.cnblogs.com/softidea/p/7217201.html
 */
class Library_Sdk_Oss_Aliyun {
    protected $_accessKeyId;
    protected $_accessSecret;

    protected $_maxSize = 2097152; //限制文件大小为x字节
    protected $_allowExt = 'jpg|jpeg|png|bmp'; //允许的扩展名

    public function __construct($accessKeyId, $accessSecret) {
        $this->_accessKeyId = $accessKeyId;
        $this->_accessSecret = $accessSecret;
    }

    public function getUserOssParam($uid, $custom=null, $useCallback=true, $expire=600) {
        if (($uid = (int) $uid) < 100000) {
            return [];
        }
        $prefix = 'u/' . date('ym/') . chunk_split($uid, 3, '/');
        return $this->_getOssParam($prefix, $custom, $useCallback, $expire);
    }

    public function getSubfixOssParam($subfix, $custom=null, $useCallback=true, $expire=600) {
        if (!$subfix || !['news'=>1,'promo'=>1,'head'=>1][$subfix]) {
            return [];
        }
        $prefix = $subfix . '/' . date('ym/');
        return $this->_getOssParam($prefix, $custom, $useCallback, $expire);
    }

    public function verify($post) {
        $size = $post['size'];
        if (!$size || ($size > $this->_maxSize)) {
            return -100406;
        }

        $file = $post['object'];
        $ext = $file ? strrchr($file, '.') : false;
        if (!$ext || !preg_match('/^\.('. $this->_allowExt .')$/i', $ext)) {
            return -100407;
        }

        return $file;

        //@TODO 验证签名
        $pubKey = $this->_getPubKey();
        if (!$pubKey || (!$sign = $_SERVER['HTTP_AUTHORIZATION'] ?? false) || (!$sign = base64_decode($sign))) { //签名错误
            return -100401;
        }

        $path = '/notify';
        $body = file_get_contents('php://input');
        $data = $path . "\n" . $body;
        if (openssl_verify($data, $sign, $pubKey, OPENSSL_ALGO_MD5) != 1) { //签名错误
            return -100401;
        }

        return $file;
    }

    protected function _getOssParam($prefix, $custom, $useCallback, $expire) {
        if (!$prefix) {
            return [];
        }
        $ret = [
            'api' => C::get('ossUrl', '@env'), //上传文件请求地址
            'name' => 'name', //文件名表单项名字,客户端填充其值(值如md5文件内容后的串+文件扩展名)
            'json' => null, //传给OSS服务的额外参数列表,解析该JSON后组装成表单元素列表(k=v)
            'file' => 'file', //文件域表单项的名字(注意:文件域表单项必须为表单的最后一项)
            'expire' => time() + $expire, //本次获取到的参数的过期时间戳
        ];
        $policy = [ //参与签名的参数
            'expiration' => $this->_gmtISO8601($ret['expire'] + 5),
            'conditions' => [
                ['content-length-range', 512, $this->_maxSize], //限制文件大小
                ['starts-with', '$key', $prefix], //限制文件名前缀                
                //@TODO 限制文件类型
            ],
        ];
        if ($useCallback) {
            $callback = base64_encode(json_encode([ //上传成功后OSS服务端回调
                'callbackUrl' => C::get('baseUrl', '@env') . 'api/callback/oss/aliyun/notify',
                'callbackBody' => 'bucket=${bucket}&object=${object}&size=${size}&token=${x:token}',
                'callbackBodyType' => 'application/x-www-form-urlencoded',
            ]));
            $policy['conditions'][] = ['eq', '$callback', $callback]; //限制Callback
        }
        $param = [
            'key' => $prefix . '${filename}', //指定文件名前缀
            'policy' => base64_encode(json_encode($policy)),
            'OSSAccessKeyId' => $this->_accessKeyId,
            'success_action_status' => 200, //让服务端返回200(默认会返回204)
        ];
        $useCallback && ($param['callback'] = $callback);
        if ($custom) { //自定义参数(见callbackBody)
            foreach ($custom as $k=>$v) {
                $param["x:{$k}"] = $v;
            }
        }
        $param['signature'] = base64_encode(hash_hmac('sha1', $param['policy'], $this->_accessSecret, true));
        $ret['json'] = json_encode($param);

        return $ret;
    }

    protected function _gmtISO8601($time) {
        $ds = date('c', $time);
        $dt = new DateTime($ds);
        $expire = $dt->format(DateTime::ISO8601);
        $expire = substr($expire, 0, strpos($expire, '+'));
        return $expire . 'Z';
    }

    protected function _getPubKey() {
        $pubKey = 'MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAKs/JBGzwUB2aVht4crBx3oIPBLNsjGsC0fTXv+nvlmklvkcolvpvXLTjaxUHR3W9LXxQ2EHXAJfCB+6H2YF1k8CAwEAAQ==';
        return F::rsaKey($pubKey, 'PUB');

        $url = base64_decode($_SERVER['HTTP_X_OSS_PUB_KEY_URL']);
        if (!$url || (!$pos = strpos($url, 'gosspublic.alicdn.com/')) ||
            ($pos != 7 && $pos != 8)) { //必须http://gosspublic.alicdn.com/或http://gosspublic.alicdn.com/开头
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        $pubKey = curl_exec($ch);
        curl_close($ch);

        return $pubKey;
    }
}