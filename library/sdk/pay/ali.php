<?php
/**
 * 支付宝APP支付接口 
 * https://docs.open.alipay.com/204
 *  下单请求参数说明
 *      https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.5bweAf&treeId=193&articleId=105465&docType=1
 *  通知接口参数说明
 *      https://doc.open.alipay.com/docs/doc.htm?spm=a219a.7629140.0.0.wDhDOz&treeId=193&articleId=105301&docType=1
 */
class Library_Sdk_Pay_Ali {
    protected $_appid; //支付宝分配给开发者的应用ID
    protected $_appkeys; //公钥和私钥

    public function __construct($appid, $appkeys) {
        $this->_appid = $appid;
        $this->_appkeys = $appkeys;
    }

    /**
     * 生成下单请求参数给客户端用
	 * @return array
     */
    public function genOrderReq($oid, $fee, $subject) {
        $req = [
            'app_id' => $this->_appid,
            'method' => 'alipay.trade.app.pay',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'notify_url' => C::get('baseUrl', '@env') . 'api/callback/pay/ali/notify',
        ];
        $biz = [
            'subject' => $subject,
            'out_trade_no' => $oid,
            'total_amount' => $fee,
            'product_code' => 'QUICK_MSECURITY_PAY',
            'timeout_express' => '60m',
        ];
        $req['biz_content'] = json_encode($biz, JSON_UNESCAPED_UNICODE);
        $req['sign'] = $this->_rsaSign($req);

        return $req;
    }

	/**
     * 获取支付通知回调数据
	 * @return string|array 失败|成功
     */
    public function getNotify() {
		if (empty($_POST)) {
			return 'NODATA';
		}
		$logs = [__FUNCTION__, $_POST];
		if (!$this->_rsaVerify($_POST)) {
			M::log(4, 0x01)->info($logs, 'alipay-notify');
			return 'BADSIGN';
		}
		return $_POST;
    }

    /**
     * 生成支付通知回调接口返回值
     */
    public function retNotify($ok) {
		return $ok ? 'success' : 'failure';
    }

    protected function _rsaSign($arr) {
        $str = $this->_param($arr);
        $ok = openssl_sign($str, $sign, F::rsaKey($this->_appkeys[1], 'RSA'), OPENSSL_ALGO_SHA256); //用商户私钥签名
        return $ok ? base64_encode($sign) : '';       
    }

    protected function _rsaVerify($arr) {
		$sign = $arr['sign'];
		unset($arr['sign'], $arr['sign_type']);
        $str = $this->_param($arr);
        $ok = $sign && (openssl_verify($str, base64_decode($sign), F::rsaKey($this->_appkeys[0], 'PUB'), OPENSSL_ALGO_SHA256) == 1); //用支付宝公钥验签
        return $ok;
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
