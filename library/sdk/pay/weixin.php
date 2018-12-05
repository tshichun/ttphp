<?php
/**
 * 微信APP支付接口 
 * https://pay.weixin.qq.com/wiki/doc/api/app/app.php?chapter=8_3
 */
class Library_Sdk_Pay_Weixin {
    protected $_mchid; //商户号
    protected $_mchkey; //商户密钥

    public function __construct($mchid, $mchkey) {
        $this->_mchid = $mchid;
        $this->_mchkey = $mchkey;
    }

    /**
     * APP下单
     */
    public function appOrder($oid, $fee, $appid, $body) {
        $prepayid = $this->_order($oid, $fee, $appid, $body, [
            'notify_url' => 'appNotify',
            'trade_type' => 'APP',
        ]);
        if (!$prepayid) {
            return false;
        }
        $ret = [
            'appid' => $appid,
            'noncestr' => $this->_nonceStr(),
            'partnerid' => $this->_mchid,
            'package' => 'Sign=WXPay',
            'timestamp' => time(),
            'prepayid' => $prepayid,
        ];
        $ret['sign'] = $this->_genSign($ret);
        return $ret;
    }

    /**
     * 公众号下单
     */
    public function jsOrder($oid, $fee, $appid, $body, $openid) {
        $prepayid = $this->_order($oid, $fee, $appid, $body, [
            'notify_url' => 'jsNotify',
            'trade_type' => 'JSAPI',
            'openid' => $openid
        ]);
        if (!$prepayid) {
            return false;
        }
        $ret = [
            'appId' => $appid,
            'timeStamp' => (string) time(),
            'nonceStr' => $this->_nonceStr(),
            'package' => 'prepay_id=' . $prepayid,
            'signType' => 'HMAC-SHA256',
        ];
        $ret['paySign'] = $this->_genSign($ret);
        return $ret;
    }

    /**
     * 获取支付通知回调数据
	 * @return string|array 失败|成功
     */
    public function getNotify() {
        $raw = file_get_contents('php://input');
        $logs = [__FUNCTION__, $raw];
        if (!$ret = $this->_fromXml($raw)) {
            M::log(4, 0x01)->info($logs, 'weixinpay-notify');
            return 'NODATA';
        }
        if (($ret['return_code'] != 'SUCCESS') || ($ret['result_code'] != 'SUCCESS')) {
            $logs[] = $ret['return_msg'];
            $logs[] = $ret['err_code'];
            M::log(4, 0x01)->info($logs, 'weixinpay-notify');
            return 'ERROR';
        }
        $sign = $this->_genSign($ret);
        if ($sign != $ret['sign']) { //签名验证失败
            $logs[] = $sign;
            $logs[] = $ret['sign'];
            M::log(4, 0x01)->info($logs, 'weixinpay-notify');
            return 'BADSIGN';
        }

		return $ret;
    }

    /**
     * 生成支付通知回调接口返回值
     */
    public function retNotify($ok, $msg=null) {
        $ret = [
            'return_code' => $ok ? 'SUCCESS' : 'FAIL',
            'return_msg' => $ok ? 'OK' : (string) $msg,
        ];
        return $this->_toXml($ret);
    }

    /**
     * 统一下单
     */
    protected function _order($oid, $fee, $appid, $body, $pars) {
        if (!$oid || !$fee || !$appid || !$body ||
            !$pars['notify_url'] || !$pars['trade_type']) {
            return false;
        }
 
        $time = time();
        $pars['mch_id'] = $this->_mchid;
        $pars['appid'] = $appid;
        $pars['body'] = $body;
        $pars['out_trade_no'] = $oid;
        $pars['total_fee'] = $fee * 100; //金额(单位分)
        $pars['spbill_create_ip'] = F::ip(false);
        $pars['notify_url'] = C::get('baseUrl', '@env') . 'api/callback/pay/weixin/' . $pars['notify_url']; //限定回调地址
        $pars['sign_type'] = 'HMAC-SHA256';
        $pars['nonce_str'] = $this->_nonceStr();
        $pars['time_start'] = date('YmdHis', $time);
        $pars['time_expire'] = date('YmdHis', $time + 3600);

        $pars['sign'] = $this->_genSign($pars);
        $opt = [CURLOPT_SSL_VERIFYPEER=>0, CURLOPT_SSL_VERIFYHOST=>0];
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $ret = F::curlPost($url, $this->_toXml($pars), $opt, $error, $errno, $hcode);
        if ($errno || ($hcode != 200)) {
            M::log()->notice([__METHOD__, $pars, $error, $errno, $hcode]);
            return false;
        }
        $logs = [__FUNCTION__, $pars, $ret];
        if (!$res = $this->_fromXml($ret)) {
            M::log()->notice($logs, 'weixinpay-sdk');
            return false;
        }
        if (($res['return_code'] != 'SUCCESS') || ($res['result_code'] != 'SUCCESS')) {
            $logs[] = $res['return_msg'];
            $logs[] = $res['err_code'];
            M::log()->notice($logs, 'weixinpay-sdk');
            return false;
        }
        $sign = $this->_genSign($res);
        if ($sign != $res['sign']) { //签名验证失败
            $logs[] = $sign;
            $logs[] = $res['sign'];
            M::log()->notice($logs, 'weixinpay-sdk');
            return false;
        }

        return $res['prepay_id'];
	}

    protected function _nonceStr() {
        return F::randStr(16);
    }

    protected function _genSign($arr) {
		unset($arr['sign']);
        ksort($arr, SORT_STRING);
        $str = $this->_param($arr) . '&key=' . $this->_mchkey;
		return strtoupper(hash_hmac('sha256', $str, $this->_mchkey));
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

    protected function _fromXml($xml) {
        if ($xml) {
            libxml_disable_entity_loader(true);
            return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        }
    }

    protected function _toXml($arr) {
        $xml = '<xml>';
        foreach ($arr as $k=>$v) {
            $v = is_numeric($v) ? $v : "<![CDATA[{$v}]]>";
            $xml .= "<{$k}>{$v}</{$k}>";
        }
        $xml .= '</xml>';
        return $xml;
    }
}
