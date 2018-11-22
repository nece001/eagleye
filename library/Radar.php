<?php

namespace Eagleye;

use Eagleye\Probe;

class Radar
{

    /**
     * 日志级别
     *
     * @var string
     */
    const LOG_LEVEL_NOTICE      = 'notice';
    const LOG_LEVEL_WARNING     = 'warning';
    const LOG_LEVEL_ERROR       = 'error';

    /**
     * 主控端服务器地址
     * 
     * @var string
     */
    private $_server_host;
    
    /**
     * 主控端服务器端口
     * 
     * @var int
     */
    private $_server_port;

    /**
     * 探针列表
     * 
     * @var array
     */
    private $_probes = array();

    /**
     * 通信密钥
     * 
     * @var string
     */
    private $_secret_key;

    /**
     * 探针进程日志文件 
     * 
     * @var string
     */
    private $_log_file = '/tmp/probe.log';

    /**
     * 是否守护进程 
     * 
     * @var bool
     */
    private $_daemonize = false;

    /**
     * 构造器
     * 
     */
    public function __construct()
    {
        if (!extension_loaded('pcntl')) {
            throw new \Exception('无法加载[pcntl]扩展');
        } elseif (!extension_loaded('sockets')) {
            throw new \Exception('无法加载[sockets]扩展');
        }
    }

    /**
     * 设置主控端服务器地址
     * 
     * @param string $host
     * @param int $port
     * @return Radar
     */
    public function setServer($host, $port)
    {
        $this->_server_host = $host;
        $this->_server_port = $port;
        return $this;
    }

    /**
     * 添加探针
     * 
     * @param Probe $probe
     * @return Radar
     */
    public function addProbe(Probe $probe) 
    {
        if (!$probe->isReady()) {
            throw new \Exception('探针对象数据不完整');
        }
        $name = $probe->getName();
        $this->_probes[$name] = array(
            'probe'=> $probe
        );
        return $this;
    }

    /**
     * 设置通信密钥
     * 
     * @param string $key
     * @return Radar
     */
    public function setSecretKey($key)
    {
        $this->_secret_key = $key;
        return $this;
    }

    /**
     * 设置是否守护进程方式运行
     * 
     * @param bool $daemonize
     * @return Radar
     */
    public function setDaemonize($daemonize)
    {
        $this->_daemonize = (bool)$daemonize;
        return $this;
    }

    /**
     * 设置日志文件路径
     * 
     * @param string $log_file
     * @return Radar
     */
    public function setLogFile($log_file)
    {
        $this->_log_file = $log_file;
        return $this;
    }

    /**
     * 启动雷达检测
     * 
     * @return null
     */
    public function start()
    {
        if (!$this->_server_host || !$this->_server_port) {
            throw new \Exception('鹰眼主控端服务器未设置');
        } elseif (!$this->_secret_key) {
            throw new \Exception('鹰眼主控端通信密钥未设置');
        }
        $this->_show('鹰眼监控客户端启动中.....', self::LOG_LEVEL_NOTICE);
        if ($this->_daemonize) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                $this->_show('启动失败，无法创建客户端主进程', self::LOG_LEVEL_ERROR);
            } elseif ($pid == 0) {  //创建成功
                $this->_mainProcess();
            }
        } else {
            $this->_mainProcess();
        }
    }

    /**
     * 主进程逻辑
     * 
     * @return null
     */
    private function _mainProcess()
    {
        //设置主进程名称
        cli_set_process_title('eagleye:probe');
        //循环创建子进程
        foreach ($this->_probes as $key=> $val) {
            $probe = $this->_probes[$key]['probe'];
            $pid = $this->_createWorker($probe);
            $this->_probes[$key]['pid'] = $pid;
            $log = sprintf('探针进程[%s]已启动, pid=%s', $probe->getName(), $pid);
            $this->_show($log, self::LOG_LEVEL_NOTICE);
        }
        $this->_show('鹰眼监控客户端启动完成', self::LOG_LEVEL_NOTICE);
        while(true) {
            foreach ($this->_probes as $key=> $val) {
                $result = pcntl_waitpid($val['pid'], $status, WNOHANG);
                if ($result > 0) {
                    $exited = pcntl_wifexited($status);     //是否程序自己退出，自动退出则不重建
                    $signaled = pcntl_wifsignaled($status); //是否接收到信号退出
                    $signal = pcntl_wtermsig($status);      //接收到信号退出时，仅USR1信号不重建进程
                    if (!$exited && $signaled && $signal != 30) { //自动重建探针进程
                        $probe = $this->_probes[$key]['probe'];
                        $pid = $this->_createWorker($probe);
                        $this->_probes[$key]['pid'] = $pid;
                        $log = sprintf('探针进程[%s]已重启, pid=%s', $probe->getName(), $pid);
                        $this->_show($log, self::LOG_LEVEL_NOTICE);
                    }
                }
            }
            sleep(3);
        }
    }

    /**
     * 创建监控进程
     * 
     * @param Probe $probe
     * @return int
     */
    private function _createWorker(Probe $probe)
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            $this->_show(sprintf('探针进程[%s]创建失败', $probe->getName()), self::LOG_LEVEL_ERROR);
            return $pid;
        } elseif ($pid == 0) {
            cli_set_process_title('eagleye:probe['. $probe->getName(). ']');
            while (true) {
                //执行当前探针的监控脚本
                if (is_callable($probe->getMethod())) {
                    $method = $probe->getMethod();
                    $result = call_user_func_array($method, array());
                    if (!is_array($result) 
                            || !array_key_exists('err_code', $result) 
                            || !array_key_exists('err_message', $result)) {
                        $log = sprintf('探针程序[%s]返回数据结构异常, 进程已退出', $probe->getName());
                        $this->_show($log, self::LOG_LEVEL_ERROR);
                        exit;
                    }
                    $data = array(
                        'action'    => 'report',
                        'data'      => array(
                            'err_code'      => $result['err_code'],     //code[0]: 正常，code[1]：警告，code[2]：致命问题
                            'err_message'   => $result['err_message'],
                        ),
                        'client'    => array(
                            'service_name'  => $probe->getName(),
                            'client_name'   => $probe->getClientName(),
                            'client_addr'   => $probe->getClientAddr(),
                            'run_interval'  => $probe->getInterval(),
                            'max_failed'    => $probe->getMaxFailed(),
                            'notify_group'  => implode(',', $probe->getNotifyGroup()),
                            'auth_key'      => md5($probe->getClientName(). $probe->getClientAddr(). $this->_secret_key),
                        ),
                    );
                    if ($result = $this->_report(json_encode($data))) {
                        if ($result['err_code'] == 9) {
                            $log = sprintf('无法注册监控服务[%s][%s], 进程已退出', $probe->getName(), $result['err_message']);
                            $this->_show($log, self::LOG_LEVEL_ERROR);
                            exit;
                        } elseif ($result['err_code'] > 0) {
                            $log = sprintf('监控服务[%s]上报出错：%s', $probe->getName(), $result['err_message']);
                            $this->_show($log, self::LOG_LEVEL_ERROR);
                        } else {
                            $log = sprintf('监控服务[%s]结果[%s]已上报', $probe->getName(), $result['err_code']);
                            $this->_show($log, self::LOG_LEVEL_NOTICE);
                        }
                    }
                } else {
                    $log = sprintf('探针程序[%s]监控代码无法被调用, 进程已退出', $probe->getName());
                    $this->_show($log, self::LOG_LEVEL_ERROR);
                    exit;
                }
                sleep(intval($probe->getInterval()));
            }
        } else {
            return $pid;
        }
    }

    /**
     * 上报数据并获取返回值
     * 
     * @param string $data
     * @return string
     */
    private function _report($data)
    {
        while(true) {
            $socket = socket_create(AF_INET, SOCK_STREAM, 0);
            $host = $this->_server_host;
            $port = $this->_server_port;
            if (!$socket || !$result = socket_connect($socket, $host, $port)) {
                $log = sprintf('无法连接至鹰眼主控端服务器[%s]', $this->_server_host. ':'. $this->_server_port);
                $this->_show($log, self::LOG_LEVEL_ERROR);
                sleep(5);
                continue;
            }
            socket_write($socket, $data);
            $result = socket_read($socket, 1024);
            socket_close($socket);
            return json_decode($result, true);
        }
    }

    /**
     * 打印日志
     *
     * @param string $log
     * @param string $level
     */
    private function _show($log, $level = self::LOG_LEVEL_NOTICE)
    {
        $content = date('Y-m-d H:i:s'). ' / '. $level. ' / '. $log. PHP_EOL;
        if (!$this->_daemonize) {
            echo $content;
        } else {
            file_put_contents($this->_log_file, $content, FILE_APPEND);
        }
    }

}