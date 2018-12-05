<?php
/**
 * TCP/UDP 客户端封装
 */
class Library_Socket {
    protected $_socks = [];
	protected $_errno;
	protected $_error;

    public function tcpSend($host, $port, $data, $recvRet=false, $recvLen=2048, $timeout=2, $close=true) {
        if (($sendLen = strlen($data)) <= 0) {
            return false;
        }
        if (!$sock = $this->_tcpConnect($host, $port, $timeout)) {
            return false;
        }
        $sent = 0;
        $buff = $data;
        while ($sent < $sendLen) {
            $bytes = socket_send($sock, $buff, $sendLen, 0);
            if ($bytes === false) {
                $this->_errno = socket_last_error($sock);
                $this->_error = socket_strerror($this->_errno);
                socket_close($sock);
                return false;
            }
            $sent += $bytes;
            if ($sent < $sendLen) {
                $buff = substr($buff, $bytes);
            }
        }
        if ($recvRet === true) {
            $ret = '';
            socket_recv($sock, $ret, $recvLen, 0);
        } else {
            $ret = true;
        }

        $close && $this->close($host, $port);

        return $ret;
	}

    public function udpSend($host, $port, $data, $timeout=2) {
        if (($sendLen = strlen($data)) <= 0) {
            return false;
        }
        if (!$sock = $this->_udpCreate($timeout)) {
            return false;
        }
        $this->_socks[$host][$port] = $sock;
        if (socket_sendto($sock, $data, $sendLen, MSG_EOF, $host, $port) === false) {
            $this->_errno = socket_last_error($sock);
            $this->_error = socket_strerror($this->_errno);
            M::log()->error(implode('#', [$this->error(), $host, $port]), 'socket-error');
            return false;
        }

        $this->close($host, $port);

        return true;
    }

    public function close($host, $port) {
        if (isset($this->_socks[$host][$port])) {
            socket_close($this->_socks[$host][$port]);
            unset($this->_socks[$host][$port]);
        }
    }

	public function error() {
        return $this->_errno ? sprintf("(%d): %s\n", $this->_errno, $this->_error) : '';
	}

    protected function _udpCreate($timeout) {
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock === false) {
            $this->_errno = socket_last_error($sock);
            $this->_error = socket_strerror($this->_errno);
            M::log()->error($this->error(), 'socket');
            return false;
        }
        if (!socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, ['sec'=>$timeout, 'usec'=>0])) {
            $this->_errno = socket_last_error($sock);
            $this->_error = socket_strerror($this->_errno);
            socket_close($sock);
            return false;
        }

        return $sock;
    }

    protected function _tcpConnect($host, $port, $timeout) {
        $this->_resetError();
        if (isset($this->_socks[$host][$port])) {
            return $this->_socks[$host][$port];
        }
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($sock === false) {
            $this->_errno = socket_last_error($sock);
            $this->_error = socket_strerror($this->_errno);
            M::log()->error(implode('#', [$this->error(), $host, $port]), 'socket-error');
            return false;
        }
        if (!socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, ['sec'=>$timeout, 'usec'=>0])) {
            $this->_errno = socket_last_error($sock);
            $this->_error = socket_strerror($this->_errno);
            socket_close($sock);
            return false;
        }
        if (!socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec'=>$timeout, 'usec'=>0])) {
            $this->_errno = socket_last_error($sock);
            $this->_error = socket_strerror($this->_errno);
            socket_close($sock);
            return false;
        }
        if (socket_connect($sock, $host, $port) === false) {
            $this->_errno = socket_last_error($sock);
            $this->_error = socket_strerror($this->_errno);
            M::log()->error(implode('#', [$this->error(), $host, $port]), 'socket-error');
            return false;
        }
        socket_set_block($sock);

        return ($this->_socks[$host][$port] = $sock);
    }

    protected function _resetError() {
        $this->_errno = 0;
        $this->_error = '';
    }
}
