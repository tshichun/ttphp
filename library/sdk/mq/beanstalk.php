<?php
/**
 * Beanstalkd客户端代理
 * https://github.com/davidpersson/beanstalk
 *
 * https://github.com/kr/beanstalkd/blob/master/doc/protocol.zh-CN.md
 */

require_once 'beanstalk/Client.php';

class Library_Sdk_Mq_Beanstalk {
    protected $_client;
    protected $_tube;
    protected $_server;
    protected $_connected;

    public function __construct($tube) {
        if ($tube && ($tube != 'default')) {
            $server = C::get('mq.' . $tube, '@env');
            $this->_server = $server ? $server : C::get('mq.main', '@env');
            $this->_client = new Beanstalk\Client([
                'host' => $this->_server[0],
                'port' => $this->_server[1],
                'timeout' => 2, //连接超时
                'persistent' => false,
                'logger' => M::log(4),
            ]);
            $this->_tube = $tube;
        }
    }

    public function connect() {
        if ($this->_connected) {
            return true;
        }

        for ($try = 1; $try <= 2; $try++) {
            if ($this->_connected = $this->_client->connect()) {
                break;
            }
		}

        if (!$this->_connected) {
            $this->_logError('connect');
            return false;
        }

        try {
            $this->_client->useTube($this->_tube) or $this->_logError('useTube');  
        } catch (Exception $e) {
            $this->_logError('useTube ' . $e->getMessage());
        }

        return true;
    }

    public function disconnect() {
        $this->_client->disconnect();
    }

    public function put($data, $pri=1024, $ttr=120, $delay=0) {
        try {
            is_string($data) or ($data = json_encode($data, JSON_UNESCAPED_UNICODE));
            return $this->_client->put($pri, $delay, $ttr, $data);
        } catch (Exception $e) {
            $this->_logError('put ' . $e->getMessage());
            return false;
        }
    }

    public function watch() {
        try {
            if (!$ret = $this->_client->watch($this->_tube)) {
                $this->_logError('watch');
            }
            $this->_client->ignore('default');
            return $ret;
        } catch (Exception $e) {
            $this->_logError('watch ' . $e->getMessage());
        }
	}

    public function reserve($timeout = null) {
        try {
            return $this->_client->reserve($timeout);
        } catch (Exception $e) {
            $this->_logError('reserve ' . $e->getMessage());
            return false;
        }
    }

    public function peekReady() {
        try {
            return $this->_client->peekReady();
        } catch (Exception $e) {
            $this->_logError('peekReady ' . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            return $this->_client->delete($id);
        } catch (Exception $e) {
            $this->_logError('delete ' . $e->getMessage());
            return false;
        }
    }

    public function release($id, $pri=0, $delay=0) {
        try {
            return $this->_client->release($id, $pri, $delay);
        } catch (Exception $e) {
            $this->_logError('release ' . $e->getMessage());
            return false;
        }
    }

    public function stats() {
        return $this->_client->stats();
    }

    public function listTubes() {
        return $this->_client->listTubes();
    }

    protected function _logError($data) {
		$data = [$data, $_SERVER['PHP_SELF']] + (array)$this->_server;
		M::log()->error(implode('#', $data), 'beanstalk');
		die('Beanstalk Error');
	}
}
