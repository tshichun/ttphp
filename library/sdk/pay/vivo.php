<?php
/**
 * Vivo支付接口
 * https://dev.vivo.com.cn
 */
class Library_Sdk_Pay_Vivo {
    protected $_cfg;

    public function __construct($cfg) {
        $this->_cfg = $cfg;
    }

    public function makeOrder($oid, $fee, $appid, $name) {
        if (!$oid || !$fee || !$appid || !$name) {
            return [];
        }
        $fee *= 100;
        $url = 'https://pay.vivo.com.cn/vcoin/trade';
        $req = [
            'version' => '1.0.0',
            'cpId' => $this->_cfg['cpid'],
            'appId' => $appid,
            'cpOrderNumber' => $oid,
            'notifyUrl' => C::get('baseUrl', '@env') . 'api/callback/pay/vivo/notify',
            'orderTime' => date('YmdHis'),  
            'orderAmount' => $fee,
            'orderTitle' => $name,
            'orderDesc' => $name,
            'extInfo' => md5($oid . $this->_cfg['cpkey']. $fee), //附加验证(用于简单证明下单参数由本系统生成)
        ];
        $req['signMethod'] = 'MD5';
        $req['signature'] = $this->_genSign($req);
        $opt = [CURLOPT_SSL_VERIFYPEER=>0, CURLOPT_SSL_VERIFYHOST=>0];
        $ret = F::curlPost($url, $req, $opt, $error, $errno, $hcode);
        if ($errno || ($hcode != 200)) {
            M::log()->notice([__METHOD__, $req, $error, $errno, $hcode]);
            return false;
        }

        $logs = [__FUNCTION__, $req, $ret];
        $ret = json_decode($ret, true);
        if (!$ret || ($ret['respCode'] != 200)) {
            M::log()->notice($logs, 'vivopay-sdk');
            return [];
        }
        $sign = $this->_genSign($ret);
        if ($sign != $ret['signature']) { //签名验证失败
            $logs[] = $sign;
            $logs[] = $ret['signature'];
            M::log()->notice($logs, 'vivopay-sdk');
            return [];
        } else {
            return [
                'productName'=>$name,
                'productDes'=>$name,
                'productPrice'=>$ret['orderAmount'],
                'vivoSignature'=>$ret['accessKey'],
                'transNo'=>$ret['orderNumber'],
                'appId'=>$appid,
                'extInfo'=>$req['extInfo'],
            ];
        }
    }

    /**
     * 获取支付通知回调数据
	 * @return int|array 失败|成功
     */
    public function getNotify() {
        $par = $_POST;
		$logs = [__FUNCTION__, $par];
        if (!$par || ($par['respCode'] != 200) || ($par['tradeStatus'] != '0000')) {
            M::log(4, 0x01)->info($logs, 'vivopay-notify');
            return -1;
        }
        if ($par['extInfo'] != md5($par['cpOrderNumber'] . $this->_cfg['cpkey'] . $par['orderAmount'])) { //附加验证失败
            M::log(4, 0x01)->info($logs, 'vivopay-notify');
            return -2;
        }
        $sign = $this->_genSign($par);
		if ($sign != $par['signature']) {
            $logs[] = $sign;
			M::log(4, 0x01)->info($logs, 'vivopay-notify');
			return -3; //验签失败
		}
		return $par;
    }

    /**
     * 生成支付通知回调接口返回值
     */
    public function retNotify($code) {
        return $code == 1 ? 'success' : 'fail';
    }

    protected function _genSign($arr) {
		unset($arr['signMethod'], $arr['signature']);
        ksort($arr, SORT_STRING);
        $str = $this->_param($arr) . '&' . md5($this->_cfg['cpkey']);
        return md5($str);
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
}
