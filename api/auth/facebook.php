<?php
class Api_Auth_Facebook extends Api_Auth_Base {
    public function index() {
        $code = Wio::get('code');

        $appsid = $this->_appCfg['appsid'];

        return $this->reply();
	}
}
