<?php
/**
 * 辅助测试脚本
 */
class Api_Demo_Test extends Api_Demo_Base {
    public function index() {
        $test = Wio::get('test');
        echo $test;
        exit(0);
    }
}
