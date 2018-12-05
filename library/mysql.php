<?php
/**
 * MySQL客户端
 */
class Library_MySQL {
    protected $_host;
    protected $_port;
    protected $_username;
    protected $_passwd;

    protected $_connected = false;
    protected $_connectTime = 0;
    protected $_mysqli=null;

    public function __construct($host, $port, $username, $passwd) {
        $this->_host = $host;
        $this->_port = $port;
        $this->_username = $username;
        $this->_passwd = $passwd;
    }

    public function connect() {
        if ($this->_connected) {
            return true;
        }

        $this->_connected = true;
        $this->_connectTime = time();
        for ($try = 1; $try <= 2; $try++) {
            $this->_mysqli = mysqli_connect($this->_host, $this->_username, $this->_passwd, '', $this->_port);
            if ($this->_mysqli->connect_errno == 0) {
                $connected = true;
                break;
            } else {
                $connected = false;
                $this->_logError("connect({$try}):" . $this->_mysqli->connect_error);
            }
        }
        return ($this->_connected = $connected);
    }

    public function query($query) {
        try {
            $result = false;
            if ($this->connect()) {
                if (!$result = $this->_mysqli->query($query)) {
                    return $this->_logError('query error', $query);
                }
            }
            return $result;
        } catch (Exception $e) {
            return $this->_logError('query error:' . $e->getCode() . ',' . $e->getMessage(), $query);
        }
    }

    public function getOne($query, $type=MYSQLI_ASSOC) {
        $result = $this->query($query);
        return $this->fetchArray($result, $type);
    }

    public function getAll($query, $type=MYSQLI_ASSOC) {
        $result = $this->query($query);
        $rows = [];
        while ($row = $this->fetchArray($result, $type)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function fetchArray($result, $type=MYSQLI_ASSOC) {
        return $this->_connected && $result && ($row = $result->fetch_array($type)) ? $row : [];
    }

    public function insertId() {
        return $this->_connected ? $this->_mysqli->insert_id : false;
    }

    public function affectedRows() {
        return $this->_connected ? $this->_mysqli->affected_rows : false;
    }

    public function ping() {
        return $this->_connected && $this->_mysqli->ping();
    }

    public function errno() {
        return $this->_connected ? $this->_mysqli->errno : 0;
    }

    public function error() {
        return $this->_connected ? $this->_mysqli->error : '';
    }

    public function close() {
        return $this->_connected && (($this->_connected = false) || $this->_mysqli->close());
    }

    public function escape($str) {
        return $str && $this->connect() ? $this->_mysqli->real_escape_string($str) : '';
    }

    protected function _logError($data, $query=null) {
        if ($query && ($this->errno() == 2006) && ($this->_connectTime < time() - 30)) { //重试
            $this->close();
            return $this->query($query);
        }
        $data = [$data, $this->_host, $this->_port, $query, $this->error(), $_SERVER['PHP_SELF']];
        M::log()->error(implode('#', $data), 'mysql-error');
        die('MYSQL_ERR');
    }
}
