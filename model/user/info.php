<?php
/**
 * 用户属性类
 */
class Model_User_Info extends Model_Base {
    protected $_maps = [
        'info' => [
            'table' => _DBPRE_.'user.userinfo',
            'field' => ['uid'=>0,'name'=>1,'_PK_'=>'uid'],
        ],
    ];

    public function __construct() {
        $this->_db = M::db('user');
    }

    public function get($uid, $field='*') {
        return [];
    }
}