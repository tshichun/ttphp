<?php
/**
 * 登录验证
 */
abstract class Api_Auth_Base extends Api {
    protected $_checkSess = false;
    protected $_appCfg = null;

    protected function _before() {
        $ret = parent::_before();
        if ($ret === true) {
            if (!$this->_appCfg) { //未找到配置
                $ret = $this->reply();
            }
        }
        return $ret;
    }
}
