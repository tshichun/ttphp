<?php
/**
 * Oppo支付接口
 * https://open.oppomobile.com
 */
class Library_Sdk_Pay_Oppo {
    protected $_cfg;

    public function __construct($cfg) {
        $this->_cfg = $cfg;
    }

    public function genOrderReq($oid, $fee, $name) {
        if (!$oid || !$fee || !$name) {
            return [];
        }
        $fee *= 100;
        return [
            'order' => $oid,
            'amount' => $fee,
            'productName' => $name,
            'productDesc' => $name,
            'callbackUrl' => C::get('baseUrl', '@env') . 'api/callback/pay/oppo/notify',
            'attach' => md5($oid . $this->_cfg['salt'] . $fee), //附加验证(用于简单证明下单参数由本系统生成)
        ];
    }

    /**
     * 获取支付通知回调数据
	 * @return string|array 失败|成功
     */
    public function getNotify() {
        $par = Wio::get();
        if (!$par || !$par['attach'] || !$par['sign']) {
            return -1;
        }
        $str = "notifyId={$par['notifyId']}&partnerOrder={$par['partnerOrder']}&productName={$par['productName']}&productDesc={$par['productDesc']}&price={$par['price']}&count={$par['count']}&attach={$par['attach']}";
        $logs = [__FUNCTION__, $str];
        if ($par['attach'] != md5($par['partnerOrder'] . $this->_cfg['salt'] . $par['price'])) { //附加验证失败
            M::log(4, 0x01)->info($logs, 'oppopay-notify');
            return -2;
        }
        if ($this->_rsaVerify($str, $par['sign']) != 1) { //验签失败
            $logs[] = $par['sign'];
            M::log(4, 0x01)->info($logs, 'oppopay-notify');
            return -3;
        }

        return $par;
    }

    /**
     * 生成支付通知回调接口返回值
     */
    public function retNotify($code) {
        if ($code == 1) {
            $res = 'OK';
            $msg = 'OK';
        } else {
            $res = 'FAIL';
            $msg = $code;
        }
        return "result={$res}&resultMsg={$msg}";
    }

    protected function _rsaVerify($str, $sign) {
        $key = F::rsaKey($this->_cfg['pubkey'], 'PUB');
        $sign = base64_decode($sign);
        return openssl_verify($str, $sign, $key);
    }
}
