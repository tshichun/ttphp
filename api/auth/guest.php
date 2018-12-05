<?php
/**
 * 游客账号登录
 */
class Api_Auth_Guest extends Api_Auth_Base {
    public function index() {
        $guid = Wio::get('guid');

        $appsid = $this->_appCfg['appsid'];

        return $this->reply();
	}
}
