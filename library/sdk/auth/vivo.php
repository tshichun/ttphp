<?php
/**
 * Vivo登录验证接口
 * https://dev.vivo.com.cn
 */
class Library_Sdk_Auth_Vivo {
	public function getAuth($token) {
        if (!$token) {
            return null;
        }
        $url = 'https://usrsys.vivo.com.cn/sdk/user/auth.do';
        $ret = F::curlPost($url, ['authtoken'=>$token], null, $error, $errno, $code);
		if ($errno || ($code != 200)) {
			M::log()->notice([__METHOD__, $url, $error, $errno, $code]);
		}
        $auth = $ret ? json_decode($ret, true) : null;
		if (!$auth || ($auth['retcode'] !== 0) || !$auth['data']['success']) {
			M::log()->warn([__FUNCTION__, $url, $token, $ret], 'vivo-auth');
			return null;
		} else {
            return ['openid'=>$auth['data']['openid']];
        }
	}
}
