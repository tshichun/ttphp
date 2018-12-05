<?php
/**
 * 微信小游戏登录验证接口
 * https://developers.weixin.qq.com/minigame/dev/document/open-api/login/code2accessToken.html
 */
class Library_Sdk_Auth_WxGame {
	protected $_appid;
	protected $_secret;

	public function __construct($appid, $secret) {
		$this->_appid = $appid;
		$this->_secret = $secret;
	}

	public function getAuth($code) {
		$req = [
			'appid' => $this->_appid,
			'secret' => $this->_secret,
			'js_code' => $code,
			'grant_type' => 'authorization_code'
		];
		$url = 'https://api.weixin.qq.com/sns/jscode2session';
		$ret = F::curlGet($url, $req, null, $error, $errno, $hcode);
		if ($errno || ($hcode != 200)) {
			M::log()->notice([__METHOD__, $req, $error, $errno, $hcode]);
		}
		$auth = $ret ? json_decode($ret, true) : null;
		if (!$auth || !$auth['openid'] || !$auth['session_key']) {
			M::log()->warn([__FUNCTION__, $req, $ret], 'wxgame-auth');
			return null;
		}

		return $auth;
	}
}
