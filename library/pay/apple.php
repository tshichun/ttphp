<?php
/**
 * 苹果IAP支付接口
 * https://developer.apple.com/in-app-purchase/
 */
class Apple_Pay_Sdk_Library {
    protected $_sandbox;
    protected $_verifyUrl;

    public function __construct($sandbox=false) {
        if ($sandbox) {
            $this->_sandbox = true;
            $this->_verifyUrl = 'https://sandbox.itunes.apple.com/verifyReceipt';
        } else {
            $this->_sandbox = false;
            $this->_verifyUrl = 'https://buy.itunes.apple.com/verifyReceipt';
        }
    }

	/**
	 * 21000 App Store不能读取你提供的JSON对象
     * 21002 receipt-data域的数据有问题
     * 21003 receipt无法通过验证
     * 21004 提供的shared secret不匹配你账号中的shared secret
     * 21005 receipt服务器当前不可用
     * 21006 receipt合法,但是订阅已过期(服务器接收到这个状态码时receipt数据仍然会解码并一起发送)
     * 21007 receipt是Sandbox环境receipt但却发送至生产环境验证服务
     * 21008 receipt是生产环境receipt但却发送至Sandbox环境验证服务 
	 */
	public function verify($receipt) {
        $req = json_encode(['receipt-data'=>$receipt]);
        $opt = [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        $ret = F::curlPost($this->_verifyUrl, $req, $opt, $error, $errno, $hcode);
        if ($errno || ($hcode != 200)) {
			M::log()->notice([__METHOD__, $receipt, $error, $errno, $hcode]);
        }
        return $ret ? json_decode($ret, true) : null;
	}
}
