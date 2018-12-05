<?php
/**
 * 请求处理
 */
abstract class Api {
    protected $_protocal = Wio_Base::DEFAULT;
    protected $_checkSess = true; // 是否检查在线状态
    protected $_userSess = null; // 用户会话信息

    /**
     * 启动方法
     */
    public function _run_($method) {
        if ($method && is_callable([$this, $method])) {
            $ret = $this->_before();
            ($ret === true) && ($ret = $this->$method());
            Wio::setOutput($ret);
            ($this->_after() === true) && Wio::output();
        }
    }

    /**
     * 生成响应数据
     * @param int $code 0成功,其他值失败
     * @param string|array $data
     */
    public function reply($code=100, $data=null) {
        $ret = ['code'=>$code]; //结果码(必须)
        if ($data) {
            if (is_array($data)) {
                $ret += $data;
            } else {
                $ret['desc'] = $data;
            }
        }
        return $ret;
    }

    protected function _before() {
        if (!Wio::isProtocal($this->_protocal)) {
            return $this->reply(400);
        }
        if (C::get('stop', '@env')) { //停服提示
            return $this->reply(503, C::lang(503, 'error'));
        }
        if ($this->_checkSess) { // 获取并验证在线信息
            $sess = M::user_sess()->check(SID, CID, VER);
            if (!$sess || ($sess['uid'] != UID)) { // 会话无效
                return $this->reply(401);
            }
            $this->_userSess = $sess;
        }
        return true;
    }

    protected function _after() {
        return true;
    }
}

abstract class CliApi {
    public function __construct() {
        if (PHP_SAPI != 'cli') {
            exit('PLEASE_RUN_IN_CLI_MODE');
        }
    }

    public function _run_($method) {
        if ($method && is_callable([$this, $method])) {
            $this->$method();
        }
    }
}
