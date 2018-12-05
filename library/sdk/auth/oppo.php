<?php
/**
 * Oppo登录验证接口
 * https://open.oppomobile.com
 */
class Library_Sdk_Auth_Oppo {
    protected $_appkey;
	protected $_secret;

    public function __construct($appkey, $secret) {
		$this->_appkey = $appkey;
		$this->_secret = $secret;
	}

	public function getAuth($token, $ssoid) {
        if (!$token || !$ssoid) {
            return null;
        }
        $token = urlencode($token);
        $time = App::$rtime;
        $url = 'http://i.open.game.oppomobile.com/gameopen/user/fileIdInfo';
        $url .= '?fileId=' . $ssoid . '&token=' . $token;
        $pars = [
            'oauthConsumerKey' => $this->_appkey,
            'oauthToken' => $token,
            'oauthSignatureMethod' => 'HMAC-SHA1',
            'oauthTimestamp' => (int)($time * 1000),
            'oauthNonce' => (int)$time + mt_rand(0, 9),
            'oauthVersion' => '1.0',
        ];
        $pars = $this->_param($pars) . '&';
        $sign = $this->_genSig($this->_secret . '&', $pars);
        $opts = [CURLOPT_HTTPHEADER=>["param:{$pars}", "oauthSignature:{$sign}"]];
        $ret = F::curlGet($url, null, $opts, $error, $errno, $code);
		if ($errno || ($code != 200)) {
			M::log()->notice([__METHOD__, $url, $error, $errno, $code]);
		}
        $auth = $ret ? json_decode($ret, true) : null;
		if (!$auth || ($auth['resultCode'] != 200)) {
			M::log()->warn([__FUNCTION__, $url, $opts, $ret], 'oppo-auth');
			return null;
		} else {
            return ['openid'=>$auth['ssoid']];
        }
	}

    protected function _param($arr) {
        $ret = [];
        foreach ($arr as $k=>$v) {
            if (($v !== null) && ($v !== '')) {
                $ret[] = "{$k}={$v}";
            }
        }
        return implode('&', $ret);
    }

    protected function _genSig($key, $str) {
        return urlencode(base64_encode(hash_hmac('sha1', $str, $key, true)));
    }
}
