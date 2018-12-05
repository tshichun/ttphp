<?php
/**
 * 微信小游戏支付接口
 * https://developers.weixin.qq.com/minigame/dev/document/midas-payment/midasPay.html
 */
class Library_Sdk_Pay_WxGame {
    protected $_cid;
    protected $_cfg;
    protected $_url;
    protected $_uri;

	public function __construct($cid, $cfg) {
		$this->_cid = $cid;
		$this->_cfg = $cfg;
        $this->_url = 'https://api.weixin.qq.com';
        $this->_uri = (App::$env == 'dev' ? '/cgi-bin/midas/sandbox' : '/cgi-bin/midas');
	}

    public function balance($uid) {
        return $this->_post($uid, '/getbalance');
    }

    public function consume($uid, $amt) {
        if (($uid > 0) && (($amt = (int) $amt) > 0)) {
            $oid = M::mall_order_info()->genOid($uid);
            $ret = $this->_post($uid, '/pay', [
                'bill_no' => $oid,
                'amt' => $amt,
            ]);
            $ret && ($ret['oid'] = $oid);
            return $ret;
        } else {
            return false;
        }
    }

    public function present($uid, $amt) {
        if (($uid > 0) && (($amt = (int) $amt) > 0)) {
            $oid = M::mall_order_info()->genOid($uid);
            $ret = $this->_post($uid, '/present', [
                'bill_no' => $oid,
                'present_counts' => $amt,
            ]);
            $ret && ($ret['oid'] = $oid);
            return $ret;
        } else {
            return false;
        }
    }

    protected function _post($uid, $uri, $pars=null) {
        if (!$ext = M::user_online()->getSessExtra($uid)) {
            return false;
        }
        $opt = [CURLOPT_SSL_VERIFYPEER=>0, CURLOPT_SSL_VERIFYHOST=>0];
        $copy = [
           'openid' => $ext['openid'],
           'appid' => $this->_cfg['appid'],
           'offer_id' => $this->_cfg['offerid'],
           'ts' => time(),
           'zone_id' => '1',
           'pf' => 'android',
           'user_ip' => F::ip(false),
        ];
        $pars && ($copy += $pars);
        $uri = $this->_uri . $uri;
        $copy['sig'] = $this->_genSign($uri, $copy);
        $atoken = $this->_getAccessToken();

        for ($try = 1; $try <= 3; $try++) {
            $pars = $copy;
            $pars['access_token'] = $atoken;
            $pars['mp_sig'] = $this->_genMpSign($uri, $pars, $ext['sesskey']);
            $url = $this->_url . "{$uri}?access_token={$atoken}";
            unset($pars['access_token']);
            $ret = F::curlPost($url, json_encode($pars), $opt, $error, $errno, $hcode);
            if ($ret && ($res = json_decode($ret, true))) {
                if ($res['errcode'] == 0) { //成功
                    unset($res['errcode'], $res['errmsg']);
                    return $res;
                } elseif ($res['errcode'] == 40001) { //更新AccessToken后重试
                    M::log()->notice([$uri, $uid, $try, $atoken], 'wxgamepay-sdk');
                    $atoken = $this->_getAccessToken(false);
                } else {
                    M::log()->notice([$uri, $uid, $ret, $pars, $try], 'wxgamepay-sdk');
					if ($res['errcode'] != -1) { //出错结束
						break;
					}
                }
            } else {
                M::log()->notice([$uri, $uid, $ret, $pars, $error, $errno, $hcode, $try], 'wxgamepay-sdk');
            }
        }

        return false; //失败
    }

    protected function _getAccessToken($cache=true) {
        $token = $cache ? M::user_online()->getAccessToken($this->_cid) : false;
        if ($token === false) {
            $opt = [CURLOPT_SSL_VERIFYPEER=>0, CURLOPT_SSL_VERIFYHOST=>0];
            $url = 'https://api.weixin.qq.com/cgi-bin/token';
            $pars = [
                'grant_type' => 'client_credential',
                'appid' => $this->_cfg['appid'],
                'secret' => $this->_cfg['secret'],
            ];
            for ($try = 1; $try <= 2; $try++) {
                $ret = F::curlGet($url, $pars, $opt, $error, $errno, $hcode);
                if ($ret && ($res = json_decode($ret, true))) {
                    if ($token = $res['access_token']) {
                        M::user_online()->setAccessToken($this->_cid, $token, $res['expires_in']);
                        break;
                    }
                    if ($res['errcode'] != -1) { //不是系统错误则不再重试
                        break;
                    }
                }
                $token or M::log()->notice([__FUNCTION__, $ret, $pars, $error, $errno, $hcode, $try], 'wxgamepay-sdk');
            }
        }
        return $token;
    }

    protected function _genSign($uri, $arr) {
        $str = $this->_param($arr) . "&org_loc={$uri}&method=POST&secret=" . $this->_cfg['appkey'];
		return hash_hmac('sha256', $str, $this->_cfg['appkey']);
    }

    protected function _genMpSign($uri, $arr, $sesskey) {
        $str = $this->_param($arr) . "&org_loc={$uri}&method=POST&session_key=" . $sesskey;
		return hash_hmac('sha256', $str, $sesskey);
    }

    protected function _param($arr) {
        ksort($arr, SORT_STRING);
        $ret = [];
        foreach ($arr as $k=>$v) {
            if (($v !== null) && ($v !== '')) {
                $ret[] = "{$k}={$v}";
            }
        }
        return implode('&', $ret);
    }
}
