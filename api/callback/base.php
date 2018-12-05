<?php
/**
 * 回调
 */
abstract class Api_Callback_Base extends Api {
    protected $_checkSess = false;

    protected function _after() {
        exit(0);
    }
}
