<?php
/**
 * 环境配置
 */
return [
    'lang' => 'zh_CN', //默认语言包名称
    'debug' => 1, //是否打开调试模式
    'dbpre' => 'db_', //前缀(用于数据库名等)
    'timezone' => 'Asia/Shanghai', //时区
    'stop' => 0, //停服开关
    'mysql' => [
        'main' => ['127.0.0.1', 3306, 'local', 'local'],
    ],
    'redis' => [
        'sess' => ['127.0.0.1', 6390, 'sesspass'],
    ],
    'webKey' => '',
];