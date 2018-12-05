<?php
/**
 * 辅助测试脚本
 */
class Api_Demo_Base extends Api {
    protected function _before() {
        (App::$env == 'dev') or die('!DEV');
        return true;
    }
}
