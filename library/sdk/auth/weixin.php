<?php
/**
 * 微信登录验证接口
 * https://open.weixin.qq.com
 */
class Library_Sdk_Auth_Weixin {
	protected $_appid;
	protected $_secret;

	public function __construct($appid, $secret) {
		$this->_appid = $appid;
		$this->_secret = $secret;
	}

    /**
     * 跳转到微信授权页获取用户授权码
     */
    public function getCode($action, $state) {
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->_appid;
        $url .= '&redirect_uri=' . urlencode(C::get('baseUrl', '@env') . 'api/' . $action);
        $url .= '&response_type=code&scope=snsapi_userinfo';
        $url .= '&state=' . $state . '#wechat_redirect';

        header("Location: {$url}", true, 301);
    }

	public function getAuth($code) {
		$req = [
			'appid' => $this->_appid,
			'secret' => $this->_secret,
			'code' => $code,
			'grant_type' => 'authorization_code'
		];
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
		$ret = F::curlGet($url, $req, null, $error, $errno, $hcode);
		if ($errno || ($hcode != 200)) {
			M::log()->notice([__METHOD__, $req, $error, $errno, $hcode]);
		}
		$auth = $ret ? json_decode($ret, true) : null;
		if (!$auth || !$auth['openid']) {
			M::log()->warn([__FUNCTION__, $req, $ret], 'weixin-auth');
			return null;
		}

		return $auth;
	}

	public function refresh($rtoken) {
		$req = [
			'appid' => $this->_appid,
			'refresh_token' => $rtoken,
			'grant_type' => 'refresh_token'
		];
		$url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token';
		$ret = F::curlGet($url, $req, null, $error, $errno, $hcode);
		if ($errno || ($hcode != 200)) {
			M::log()->notice([__METHOD__, $req, $error, $errno, $hcode]);
		}
		$auth = $ret ? json_decode($ret, true) : null;
		if (!$auth || !$auth['openid']) {
            M::log()->warn([__FUNCTION__, $req, $ret], 'weixin-auth');
            return null;
		}

		return $auth;
	}

	public function getUserinfo($atoken, $openid) {
		$req = [
			'access_token' => $atoken,
			'openid' => $openid,
			'lang' => 'zh_CN'
		];
		$url = 'https://api.weixin.qq.com/sns/userinfo';
		$ret = F::curlGet($url, $req, null, $error, $errno, $hcode);
		if ($errno || ($hcode != 200)) {
			M::log()->notice([__METHOD__, $req, $error, $errno, $hcode]);
		}
		$info = $ret ? json_decode($ret, true) : null;
		if (!$info || !$info['openid']) {
            M::log()->warn([__FUNCTION__, $req, $ret], 'weixin-auth');
			return null;
		}

		return $info;
	}
}
