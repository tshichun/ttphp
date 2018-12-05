<?php
/**
 * 华为支付接口
 * http://developer.huawei.com/consumer/cn/service/hms/catalog/game.html?page=gamesdk_game_sdkdownload
 * Game开发者指导书.pdf > 华为支付接口 > 订单数据生成
 * Game支付服务端回调接口.pdf
 * http://developer.huawei.com/consumer/cn/service/hms/catalog/HuaweiJointOperation.html?page=hmssdk_jointOper_api_reference_s1
 */
class Library_Sdk_Pay_Huawei {
    protected $_cfg;

    public function __construct($cfg) {
        $this->_cfg = $cfg;
    }

    /**
     * 生成下单请求参数给客户端用
	 * @return array
     */
    public function genOrderReq($oid, $fee, $appid, $name, $newSdk=true) {
        $req = [
            'applicationID' => $appid,
            'amount' => sprintf('%.2f', $fee),
            'productName' => $name,
            'requestId' => $oid,
            'productDesc' => $name,
        ];
        if ($newSdk) { //新SDK
            $req['merchantId'] = $this->_cfg['cpid'];
            $req['sdkChannel'] = 3;
            $req['sign'] = $this->_rsaSign($req);
            $req['merchantName'] = $this->_cfg['body'];
            $req['serviceCatalog'] = 'X6';
        } else { //老SDK
            $req['userID'] = $this->_cfg['cpid'];
            $req['sign'] = $this->_rsaSign($req);
            $req['userName'] = $this->_cfg['body'];
            $req['signType'] = 'RSA256';
        }

        return $req;
    }

    /**
     * 获取支付通知回调数据
	 * @return int|array 失败|成功
     */
    public function getNotify() {
        $par = null;
        $raw = file_get_contents('php://input');
        $raw && parse_str($raw, $par);
        if (!$par || !isset($par['result'])) {
            return 98; //参数错误
        }
        $par['sign'] && ($par['sign'] = str_replace(' ', '+', urldecode($par['sign'])));
        $par['extReserved'] && ($par['extReserved'] = urldecode($par['extReserved']));
        $par['sysReserved'] && ($par['sysReserved'] = urldecode($par['sysReserved']));

		$logs = [__FUNCTION__, $par];
		if (!$this->_rsaVerify($par)) {
			M::log(4, 0x01)->info($logs, 'huaweipay-notify');
			return 1; //验签失败
		}
		return $par;
    }

    /**
     * 生成支付通知回调接口返回值
     */
    public function retNotify($code) {
        $code = (int) $code;
        return '{"result":' . $code . '}';
    }

    protected function _rsaSign($arr) {
        $str = $this->_param($arr);
        $ok = openssl_sign($str, $sign, F::rsaKey($this->_cfg['keys'][1], 'RSA'), OPENSSL_ALGO_SHA256);
        return $ok ? base64_encode($sign) : '';       
    }

    protected function _rsaVerify($arr) {
		$sign = $arr['sign'];
		unset($arr['sign'], $arr['signType']);
        $str = $this->_param($arr);
        $ok = $sign && (openssl_verify($str, base64_decode($sign), F::rsaKey($this->_cfg['keys'][0], 'PUB'), OPENSSL_ALGO_SHA256) == 1);
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
