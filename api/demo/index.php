<?php
/**
 * 辅助脚本
 * php -f index.php 'r=demo/index/usage'
 */
class Api_Demo_Index extends CliApi {
    public function usage() {
        echo "Demo Script\n";
        echo "Actions:\n";
        if ($methods = get_class_methods($this)) {
            foreach ($methods as $m) {
                if (($m{0} != '_') && ($m != __FUNCTION__)) {
                    echo "  php -f index.php 'r=demo/index/{$m}'\n";
                }
            }
        }
    }

    /**
     * 输出某表的字段名及其缓存键映射
     */
    public function showColumns() {
        $db = Wio::get('db');
        $table = Wio::get('table');
        
        $table or die("PARAM_ERR\n");

        $db = $db ? M::db($db) : M::db('main');
        $query = 'SHOW COLUMNS FROM ' . $table;
        $result = $db->query($query);
        $cols = $maps = $pris = [];
        $idx = 0;
		while ($row = $db->fetchArray($result)) {
            if (strcasecmp($row['Type'], 'JSON') == 0) {
                $type = 2; //json
            } else {
                $type = preg_match('/^TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT|BIT|BOOLEAN|SERIAL/i', $row['Type']) ? 0 : 1; //数值:字符串
            }
            $key = $row['Field'];
            $cols[] = "'{$key}'=>{$type}";
            $maps[] = "{$idx}=>'{$key}'";
            if ($row['Key'] == 'PRI') {
                $pris[] = $key;
            }
            ++$idx;
        }
        $cols[] = "'_PK_'=>'" . implode(',', $pris) . "'";
        $cols = implode(',', $cols);
        $maps = implode(',', $maps);
        
        die("[TABLE]\n{$table}\n[FIELD]\n[{$cols}]\n[CACHE]\n[{$maps}]\n");
	}

    /**
     * 输出建表语句,可指定分表数量
     */
    public function showCreateTable() {
        $db = Wio::get('db');
        $table = Wio::get('table');
        $scale = Wio::get('scale');

        ($table && ($name = explode('.', $table, 2)[1])) or die("parameter error\n");
        $db = $db ? M::db($db) : M::db('main');
        $query = 'SHOW CREATE TABLE ' . $table;
        $row = $db->getOne($query);
        $sql = $row['Create Table'];
        if (!$sql) {
            die("QUERY_ERR");
        }
        if ($scale <= 1) {
            die($sql . ";\n");
        }
        for ($i = 0; $i < $scale; $i++) {
            echo str_replace("`{$name}` (", "`{$name}{$i}` (", $sql);
            echo ";\n";
        }
    }
}
