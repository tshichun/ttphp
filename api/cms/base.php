<?php
/**
 * CMS接口
 */
abstract class Api_Cms_Base extends Api {
    protected $_protocal = Wio_Base::ENCRYPT;

	protected function _before() {
        if (!Wio::isProtocal($this->_protocal)) {
			return $this->reply(400);
		}
		return true;
	}

    /**
     * 处理数据发布请求
     */
    public function sendData() {
        $data = Wio::get();
        if (!$data || !$data['map']) {
            return $this->reply(); //参数错误
        }

        $model = $this->_getModel();
        foreach ($data['list'] as $k=>$v) {
            $data['list'][$k] = array_combine($data['keys'], $v);
		}
        $count = count($data['list']);
        if (!$count || ($count > 500)) { //限制批量数
            return $this->reply();
        }
        $ret = $model->setSendData($data['map'], $data['list']);

        return $this->reply($ret);
    }

    /**
     * 获取当前控制器对应的模型对象
     */
    protected function _getModel() {
        $class = ltrim(get_class($this), 'Api_');
        return call_user_func("M::{$class}");
    }
}
