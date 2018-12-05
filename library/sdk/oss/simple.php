<?php
/**
 * 上传参数
 */
class Library_Sdk_Oss_Simple {
    public function getOssParam($uid) {
        $ret = [
            'api' => C::get('ossUrl', '@env'), //上传文件请求地址
            'name' => 'name', //文件名表单项名字,客户端填充其值(值如md5文件内容后的串+文件扩展名)
            'json' => '', //传给OSS服务的额外参数列表,解析该JSON后组装成表单元素列表(k=v)
            'file' => 'upload', //文件域表单项的名字(注意:文件域表单项必须为表单的最后一项)
            'expire' => time() + 600, //本次获取到的参数的过期时间戳
        ];

        $str = sprintf('d=%s&r=%d&u=%d', 'img', 0, $uid);
        $str .= '&s=' . md5($str . 'dd%j8&8D8U');
        parse_str($str, $pars);
        $ret['json'] = json_encode($pars);

        return $ret;
    }
}