<?php
/**
 * 模型基类
 */
abstract class Model_Base {
    /**
     * 数据表字段映射配置等
     * 以下两个字段的值通过
     *  'r=demo/index/showColumns'
     * 生成:
     *  $_maps['field']
     *  $_maps['cache']['col']
     * @var array
     */
    protected $_maps;

    /**
     * 数据库操作对象
     * @var Library_MySQL
     */
    protected $_db;

    /**
     * Redis操作对象
     * @var Library_Redis
     */
    protected $_redis;

    private $_sql; //临时SQL语句
    private $_map; //临时映射配置

    private function _clear() {
        $this->_sql = [];
        $this->_map = null;
    }

    /**
     * 选择一个数据表映射配置
     * @param string $map 在$_maps中的键
     * @param int $mod
     */
    protected function table($map, $mod=false) {
        $this->_map = $this->_maps[$map];
        $this->_map && ($mod !== false) && ($this->_map['table'] .= $mod);
        return $this;
    }

    /**
     * SELECT
     * @param string $field 字段列表(逗号分隔多个)
     * @param array $aggrs 聚合字段(如{COUNT=>id,MAX=>id,...})
     */
    protected function select($field='*', $aggrs=null) {
        if ($field != '*') {
            $field = explode(',', $field);
            $tmp = [];
            foreach($field as $v) {
                if (isset($this->_map['field'][$v])) {
                    $tmp["`{$v}`"] = 1;
                }
            }
            $pk = '`' . $this->_map['field']['_PK_'] . '`';
            if ($tmp && !isset($tmp[$pk])) { //自动带上主键字段
                $tmp[$pk] = 1;
            }
            $field = implode(',', array_keys($tmp)); //过滤后的字段列表
            if ($aggrs) {
                $funcs = ['COUNT'=>1,'SUM'=>1,'MIN'=>1,'MAX'=>1]; //支持的聚合函数
                foreach ($aggrs as $k=>$v) {
                    if ($funcs[$k] && isset($this->_map['field'][$v])) {
                        $field .= ",{$k}(`{$v}`) AS {$k}_{$v}";
                    }
                }
            }
        }
        $this->_sql['select'] = "SELECT {$field} FROM " . $this->_map['table'];
        return $this;
    }

    /**
     * WHERE
     * @param array $where 条件键值对(条件为IN时, 多值用数组或以逗号分隔的字符串)
     *                      格式: {k1=>v1,k2=>v2,...} 或 [{k1=>v1,k2=>v2,...},...]
     * @param array $logic 条件运算符(例如{k1=>"<",k2=>"IN",...}, 默认"="可不设置)
     *                      格式: 同$where
     */
    protected function where($where, $logic=null) {
        $chars = ['<'=>1,'<='=>1,'='=>1,'!='=>1,'>'=>1,'>='=>1,'IN'=>1];
        if (!isset($where[0])) { //将{k=>v}格式转成[{k=>v}]格式统一处理
            $where = [$where];
            $logic && ($logic = [$logic]);
        }
        $tmp = [];
        foreach ($where as $k=>$v) {
            $v = array_intersect_key($v, $this->_map['field']);
            foreach ($v as $kk=>$vv) {
                $op = $logic && $logic[$k] ? $logic[$k][$kk] : null;
                ($op && $chars[$op]) or ($op = '=');
                if ($op == 'IN') {
                    $vv = implode(',', array_map('intval', is_array($vv) ? $vv : explode(',', $vv)));
                    $tmp[] = "`{$kk}` IN({$vv})";
                } else {
                    $vv = $this->_map['field'][$kk] ? $this->_db->escape($vv) : (int) $vv;
                    $tmp[] = "`{$kk}`{$op}'{$vv}'";
                }
            }
        }
        $this->_sql['where'] = 'WHERE ' . implode(' AND ', $tmp);
        return $this;
    }

    /**
     * ORDER BY
     * @param array $order 例如{'k1'=>'DESC','k2'=>'ASC',...}, 默认按主键倒序
     */
    protected function order($order=null) {
        $sort = ['ASC'=>1,'DESC'=>1];
        if ($order === null) { //默认按主键倒序
            $order = $this->_map['field']['_PK_'] . ' DESC';
        } else {
            $tmp = [];
            foreach ($order as $k=>$v) {
                if (isset($this->_map['field'][$k])) {
                    $v = $sort[$v] ? $v : 'ASC';
                    $tmp[] = "`{$k}` {$v}";
                }
            }
            $order = implode(',', $tmp);
        }
        $this->_sql['order'] = 'ORDER BY ' . $order;
        return $this;
    }

    /**
     * GROUP BY
     * @param string $field 字段列表(逗号分隔多个) 
     */
    protected function group($field) {
        $field = explode(',', $field);
        $tmp = [];
        foreach($field as $v) {
            if (isset($this->_map['field'][$v])) {
                $tmp["`{$v}`"] = 1;
            }
        }
        $this->_sql['group'] = 'GROUP BY ' . implode(',', array_keys($tmp));
        return $this;
    }

    /**
     * LIMIT
     * @param int $rows 限制记录数
     * @param bool|int $offset 偏移量
     */
    protected function limit($rows, $offset=0) {
        $this->_sql['limit'] = sprintf('LIMIT %d,%d', $offset, $rows);
        return $this;
    }

    /**
     * ON DUPLICATE ...
     * @param string|array $field 重复时更新的字段
     * @param array $incrs 重复时累加/减字段值 ['k1'=>1,'k2'=>-1,...]
     */
    protected function duplicate($field, $incrs=null) {
        is_array($field) or ($field = explode(',', $field));
        $tmp = [];
        foreach($field as $k) {
            if (isset($this->_map['field'][$k]) &&
                ($k != $this->_map['field']['_PK_'])) {
                if ($incrs && isset($incrs[$k])) {
                    $v = "`{$k}`=`{$k}`+" . (int) $incrs[$k];
                } else {
                    $v = "`{$k}`=VALUES(`{$k}`)";
                }
                $tmp[$k] = $v;
            }
        }
        $this->_sql['duplicate'] = 'ON DUPLICATE KEY UPDATE ' . implode(',', $tmp);
        return $this;
    }

    /**
     * 获取一条记录
     * @return array
     */
    protected function getOne() {
        $this->_sql['limit'] = 'LIMIT 1';
        $sql = implode(' ', $this->_sql);
        $this->_clear();
        return $this->_db->getOne($sql);
    }

    /**
     * 获取多条记录
     * @return array
     */
    protected function getAll() {
        if (!$this->_sql['limit']) {
            $this->_sql['limit'] = 'LIMIT 5000';
        }
        $sql = implode(' ', $this->_sql);
        $this->_clear();
        return $this->_db->getAll($sql);
    }

    /**
     * 删除一条或多条记录
     * @return false|int
     */
    protected function delete() {
        if (!$this->_sql['where']) { //必要条件
            return false;
        }
        $sql = sprintf('DELETE FROM %s %s %s',
                        $this->_map['table'], $this->_sql['where'], $this->_sql['limit']);
        $this->_clear();
        if (!$this->_db->query($sql)) {
            return false;
        }
        return $this->_db->affectedRows();
    }

    /**
     * 插入一条记录
     * @param array $data
     * @return false|int
     */
    protected function insert($data) {
        $field = $this->_map['field'];
        unset($field['_PK_']);
        $keys = $vals = [];
        foreach ($field as $k=>$t) {
            if (isset($data[$k])) { //指定值
                $v = ($t ? $this->_db->escape($data[$k]) : (int) $data[$k]);
            } else { //默认值
                $v = ($t == 1 ? '' : ($t == 2 ? 'null' : 0));
            }
            $keys[] = "`{$k}`";
            $vals[] = "'{$v}'";
        }
        $sql = sprintf('INSERT INTO %s(%s) VALUES(%s) %s',
                        $this->_map['table'], implode(',', $keys), implode(',', $vals),
                        $this->_sql['duplicate']);
        $this->_clear();
        if (!$this->_db->query($sql)) {
            return false;
        }
        return $this->_db->affectedRows();
    }

    /**
     * LAST INSERT ID
     */
    protected function insertId() {
        return $this->_db->insertId();
    }

    /**
     * 插入多条记录
     * @param array $data
     * @return false|int
     */
    protected function insertAll($data) {
        if (count($data) > 5000) { //限制数量
            $this->_clear();
            return false;
        }
        $field = $this->_map['field'];
        unset($field['_PK_']);
        $keys = $vals = [];
        foreach ($field as $k=>$v) {
            $keys[] = "`{$k}`";
        }
        foreach ($data as $d) {
            $tmp = [];
            foreach ($field as $k=>$t) {
                if (isset($d[$k])) { //指定值
                    $v = ($t ? $this->_db->escape($d[$k]) : (int) $d[$k]);
                } else { //默认值
                    $v = ($t == 1 ? '' : ($t == 2 ? 'null' : 0));
                }
                $tmp[] = "'{$v}'";
            }
            $vals[] = '(' . implode(',', $tmp) . ')';
        }
        $sql = sprintf('INSERT INTO %s(%s) VALUES%s %s',
                        $this->_map['table'], implode(',', $keys), implode(',', $vals),
                        $this->_sql['duplicate']);
        $this->_clear();
        if (!$this->_db->query($sql)) {
            return false;
        }
        return $this->_db->affectedRows();
    }

    /**
     * 更新一条或多条记录
     * @param array $data
     * @param array $incrs 累加/减字段值 ['k1'=>1,'k2'=>-1,...]
     * @return false|int
     */
    protected function update($data, $incrs=null) {
        if (!$this->_sql['where']) { //必要条件
            return false;
        }
        $tmp = [];
        $field = $this->_map['field'];
        $pk = $field['_PK_'];
        unset($field[$pk], $field['_PK_']);
        if ($data) {
            foreach ($data as $k=>$v) {
                if (isset($field[$k])) {
                    $v = ($field[$k] ? $this->_db->escape($v) : (int) $v);
                    $tmp[$k] = "`{$k}`='{$v}'";
                }
            }
        }
        if ($incrs) {
            foreach ($incrs as $k=>$v) {
                if (isset($field[$k])) {
                    $tmp[$k] = "`{$k}`=`{$k}`+" . (int)$v;
                }
            }
        }
        $sql = sprintf('UPDATE %s SET %s %s %s',
                        $this->_map['table'], implode(',', $tmp), $this->_sql['where'], $this->_sql['limit']);
        $this->_clear();
        if (!$this->_db->query($sql)) {
            return false;
        }
        return $this->_db->affectedRows();
    }

    /**
     * 执行指定SQL(注意SQL安全、耗资源等问题)
     * @param string $query
     */
    protected function execute($query, $fetch=0) {
        switch ($fetch) {
            case 1: return $this->_db->getOne($query);
            case 2: return $this->_db->getAll($query);
            default: return $this->_db->query($query);
        }
    }

    /**
     * 返回SQL数组
     * @param array
     */
    protected function getSql() {
        return $this->_sql;
    }

    /**
     * 业务方法 - 处理CMS发布数据(空实现,具体由子类重写)
     */
    public function setSendData($map, $list) {}
}
