<?php
class Api_Index extends Api {
    protected $_checkSess = false; // 此类下所有接口不主动验证在线状态

	/**
	 * 登录游戏(统一登录入口)
	 * 该接口的请求流程：
	 * 	如果    1.客户端不是第一次请求并且已缓存有会话ID,则直接尝试请求
	 * 	否则	2.应先请求auth/xxx/xxx接口拿到会话ID,再请求该接口
	 * 	若请求该接口失败是因为会话过期或不存在,则重试上述第2步
	 */
    public function login() {
        return $this->reply(0, []);
    }
}
