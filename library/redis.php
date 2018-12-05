<?php
/**
 * https://github.com/phpredis/phpredis
 */
class Library_Redis {
    protected $_host;
    protected $_port;
    protected $_passwd;

    protected $_connected = false;
    protected $_connectTime = 0;
    protected $_connectTimeout = 3;
    protected $_redis=null;

    public function __construct($host, $port, $passwd='') {
        $this->_host = $host;
        $this->_port = $port;
        $this->_passwd = $passwd;
    }

    public function connect() {
        if ($this->_connected && $this->_connectTime && ($this->_connectTime < time() - 30)) {
            return true;
        }

        $this->_connected = true;
        $this->_connectTime = time();
        $this->_redis = new Redis();

        for ($try = 1; $try <= 2; $try++) {
            try {
                $connected = $this->_redis->connect($this->_host, $this->_port, $this->_connectTimeout);
                if ($connected) {
                    $this->_passwd && $this->_redis->auth($this->_passwd);
                    break;
                }
            } catch (RedisException $e) {
                $connected = false;
                $this->_logError("connect({$try}):" . $e->getCode() . ',' . $e->getMessage());
            }
        }

        return ($this->_connected = $connected);
    }

    public function setOption($opt, $val) {
        return $this->connect() && $this->_redis->setOption((int)$opt, $val);
    }

    public function info($section='all') {
        return $this->connect() ? $this->_redis->info($section) : [];
    }

    public function delete($keys) {
        try {
            $ret = $this->connect() ? $this->_redis->delete($keys) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('delete:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function set($key, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->set($key, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('set:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function setNx($key, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->setNx($key, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('set:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function get($key) {
        try {
            $ret = $this->connect() ? $this->_redis->get($key) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('get:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function mSet($keyVals) {
        try {
            $ret = $this->connect() ? $this->_redis->mSet($keyVals) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('mSet:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function mGet($keys) {
        try {
            $ret = $this->connect() ? $this->_redis->mGet($keys) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('mGet:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function scan($pattern, $perCount=10, $maxCount=0) {
        $keys = [];
        try {
            if (!$this->connect()) {
                return false;
            }
            $it = null;
            do {
                if ($maxCount > 0) {
                    $count = count($keys);
                    if ($count >= $maxCount) {
                        break;
                    }
                    $newCount = $maxCount - $count;
                    if ($newCount < $perCount) {
                        $perCount = $newCount;
                    }
                }
                $ret = $this->_redis->scan($it, $pattern, $perCount);
                ($ret !== false) && ($keys = array_merge($keys, $ret));
            } while ($it > 0);
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('scan:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $keys;
    }

    public function incrBy($key, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->incrBy($key, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('incrBy:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function hSet($key, $field, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->hSet($key, $field, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('hSet:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function hSetNx($key, $field, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->hSetNx($key, $field, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('hSet:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function hIncrBy($key, $field, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->hIncrBy($key, $field, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('hSet:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function hGet($key, $field) {
        try {
            $ret = $this->connect() ? $this->_redis->hGet($key, $field) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('hGet:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function hDel($key, $field) {
        try {
            $ret = $this->connect() ? $this->_redis->hDel($key, $field) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('hDel:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function hMset($key, $vals) {
        try {
            $ret = $this->connect() ? $this->_redis->hMset($key, $vals) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('hMset:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function hMget($key, $fields) {
        try {
            $ret = $this->connect() ? $this->_redis->hMget($key, $fields) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('hMget:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function hGetAll($key) {
        try {
            $ret = $this->connect() ? $this->_redis->hGetAll($key) : [];
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('hGetAll:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function zAdd($key, $score, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->zAdd($key, $score, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('zAdd:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function zRem($key, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->zRem($key, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('zRem:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function zIncrBy($key, $score, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->zIncrBy($key, $score, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('zIncrBy:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function zRevRange($key, $start, $end, $withscores=false) {
        try {
            $ret = $this->connect() ? $this->_redis->zRevRange($key, $start, $end, $withscores) : [];
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('zRevRange:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function zRevRank($key, $member) {
        try {
            $ret = $this->connect() ? $this->_redis->zRevRank($key, $member) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('zRevRank:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function zDeleteRangeByRank($key, $start, $end) {
        try {
            $ret = $this->connect() ? $this->_redis->zDeleteRangeByRank($key, $start, $end) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('zDeleteRangeByRank:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function lIndex($key, $idx) {
        try {
            $ret = $this->connect() ? $this->_redis->lIndex($key, $idx) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('lIndex:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function lPop($key) {
        try {
            $ret = $this->connect() ? $this->_redis->lPop($key) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('lPop:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function rPush($key, $val) {
        try {
            $ret = $this->connect() ? $this->_redis->rPush($key, $val) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('rPush:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function expire($key, $ttl) {
        try {
            $ret = $this->connect() ? $this->_redis->expire($key, $ttl) : false;
        } catch (RedisException $e) {
            $this->close();
            $this->_logError('expire:' . $e->getCode() . ',' . $e->getMessage());
        }
        return $ret;
    }

    public function close() {
        return $this->_connected && (($this->_connected = false) || $this->_redis->close());
    }

    protected function _logError($data) {
        $data = [$data, $this->_host, $this->_port, $_SERVER['PHP_SELF']];
        M::log()->error(implode('#', $data), 'redis-error');
        die('REDIS_ERR');
    }
}