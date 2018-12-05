<?php
/**
 * 华为登录验证接口
 * http://developer.huawei.com/consumer/cn/service/hms/catalog/game.html?page=gamesdk_game_sdkdownload
 * Game开发者指导书.pdf
 * http://developer.huawei.com/consumer/cn/service/hms/catalog/HuaweiJointOperation.html?page=hmssdk_jointOper_api_reference_s4
 */
class Library_Sdk_Auth_Huawei {
	protected $_cfg;

	public function __construct($cfg) {
		$this->_cfg = $cfg;
	}

    public function checkPar($par, $remote=true) {
        if ($par['ts'] && $par['playerId'] && $par['gameAuthSign']) {
            return $remote ? $this->_checkParRemote($par) :
                            $this->_checkParLocal($par);
        } else {
            return false;
        }
    }

    /**
     * 远程验证(新SDK)
     */
    protected function _checkParRemote($par) {
        $url = 'https://gss-cn.game.hicloud.com/gameservice/api/gbClientApi';
        $req = [
            'method'=>'external.hms.gs.checkPlayerSign',
            'appId'=>$this->_cfg['appid'],
            'cpId'=>$this->_cfg['cpid'],
            'ts'=>$par['ts'],
            'playerId'=>$par['playerId'],
            'playerLevel'=>(string)$par['playerLevel'],
            'playerSSign'=>$par['gameAuthSign'],
        ];
        $req['cpSign'] = $this->_rsaSign($req);
        $ret = F::curlPost($url, $req, null, $error, $errno, $code);
		if ($errno || ($code != 200)) {
			M::log()->notice([__METHOD__, $url, $error, $errno, $code]);
		}
        $res = $ret ? json_decode($ret, true) : null;
		if (!$res || ($res['rtnCode'] !== 0) || !$res['rtnSign']) {
			M::log()->warn([__FUNCTION__, $url, $req, $ret], 'huawei-auth');
			return false;
		} else {
            return $this->_rsaVerify($res);
        }
    }

    /**
     * 本地验证(老SDK)
     */
    protected function _checkParLocal($par) {
        if (time() - ($par['ts'] / 1000) > 3600) { //1小时有效
            return false;
        }
        $pubkey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAmKLBMs2vXosqSR2rojMzioTRVt8oc1ox2uKjyZt6bHUK0u+OpantyFYwF3w1d0U3mCF6rGUnEADzXiX/2/RgLQDEXRD22er31ep3yevtL/r0qcO8GMDzy3RJexdLB6z20voNM551yhKhB18qyFesiPhcPKBQM5dnAOdZLSaLYHzQkQKANy9fYFJlLDo11I3AxefCBuoG+g7ilti5qgpbkm6rK2lLGWOeJMrF+Hu+cxd9H2y3cXWXxkwWM1OZZTgTq3Frlsv1fgkrByJotDpRe8SwkiVuRycR0AHsFfIsuZCFwZML16EGnHqm2jLJXMKIBgkZTzL8Z+201RmOheV4AQIDAQAB'; //华为验签公钥
        $data = $this->_cfg['appid'] . $par['ts'] . $par['playerId'];
        $ret = openssl_verify($data, base64_decode($par['gameAuthSign']), F::rsaKey($pubkey, 'PUB'), OPENSSL_ALGO_SHA256);

        return ($ret == 1);
    }

    protected function _rsaSign($arr) {
        $str = $this->_param($arr);
        $ok = openssl_sign($str, $sign, F::rsaKey($this->_cfg['keys'][1], 'RSA'), OPENSSL_ALGO_SHA256);
        return $ok ? base64_encode($sign) : '';       
    }

    protected function _rsaVerify($arr) {
		$sign = $arr['rtnSign'];
		unset($arr['rtnSign']);
        $str = $this->_param($arr);
        $ok = $sign && (openssl_verify($str, base64_decode($sign), F::rsaKey($this->_cfg['keys'][0], 'PUB'), OPENSSL_ALGO_SHA256) == 1);
        return $ok;
    }

    protected function _param($arr) {
        ksort($arr, SORT_STRING);
        return http_build_query($arr);
    }
}
