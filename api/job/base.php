<?php
class Api_Job_Base extends CliApi {
    /**
     * 检查/添加进程锁
     */
    protected function _checkRun($name) {
        $arg = $_SERVER['argv'][1] or die; //第1个命令行参数可区别不同进程
        $pno = (int) Wio::get('pno'); //自定义进程序号
        $run = App::$path . "var/run/{$name}.{$pno}.pid";

        $ret = $out = null;
        $arg = escapeshellarg(strrchr(substr(App::$path, 0, -1), '/') . '/index.php ' . $arg); //业务PHP进程由api/job/do.sh.php启动
        $cmd = "ps ax | grep {$arg} | grep -v grep | awk '{print \$1}'";
        exec($cmd, $out, $ret);
        if ($ret !== 0) { //命令执行失败
            M::log()->error($cmd, 'job-run-error');
            die;
        }

        $lck = file_exists($run);
        $num = count($out);
        if ($num > 2) { //正常情况最多只有2个进程,其1为之前启动并正在运行中的,其2为当前进程
            exec('kill ' . implode(' ', $out), $out, $ret); //异常情况杀掉退出下次重来
            ($ret === 0) && $lck && (unlink($run) or M::log()->error("unlink {$run}", 'job-run-error'));
            die;
        }
        if ($lck && ($num == 2)) { //锁和进程都在
            $die = rtrim($run, '.pid') . '.die';
            if (file_exists($die)) { //重启文件存在则向进程发重启信号
                $pid = file_get_contents($run);
                posix_kill($pid, SIGUSR1);
                unlink($die) or M::log()->error("unlink {$die}", 'job-run-error');
            }
            die;
        }
        if (!file_put_contents($run, posix_getpid())) { //加锁失败
            M::log()->error($run, 'job-run-error');
            die;
        }
    }
}
