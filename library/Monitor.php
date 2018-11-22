<?php

namespace Eagleye;

use swoole_server as SwooleServer;

class Monitor
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
     * 通知级别
     * 
     * @var int
     */
    const NOTIFY_LEVEL_NORMAL   = 0;    //ok
    const NOTIFY_LEVEL_WARNING  = 1;    //warning
    const NOTIFY_LEVEL_CRITICAL = 2;    //critical

    /**
     * 监控端主服务地址
     *
     * @var string
     */
    private $_host              = '127.0.0.1';

    /**
     * 监控端主服务端口
     *
     * @var int
     */
    private $_port              = 9501;

    /**
     * 日志文件地址
     *
     * @var string
     */
    private $_log_file          = '/tmp/monitor.log';

    /**
     * 是否守护进程模式运行
     *
     * @var bool
     */
    private $_daemonize         = 0;

    /**
     * 监控信息数据中心
     * 
     * @var array
     */
    private $_storage           = array();

    /**
     * 通知消息队列
     * 
     * @var array
     */
    private $_notify_queue      = array();

    /**
     * 消息通知级别，默认critical
     * 
     * @var int
     */
    private $_notify_level      = 2;

    /**
     * 邮件通知组
     */
    private $_notify_group      = array();

    /**
     * 邮件发送服务器地址
     * 
     * @var string
     */
    private $_mail_server_host;

    /**
     * 邮件发送服务器端口
     * 
     * @var int
     */
    private $_mail_server_port;

    /**
     * 邮件发送帐户用户名
     * 
     * @var string
     */
    private $_mail_server_user;

    /**
     * 邮件发送帐户密码
     * 
     * @var string
     */
    private $_mail_server_pass;

    /**
     * 邮件发送者帐户
     * 
     * @var string
     */
    private $_mail_sender;

    /**
     * 通信密钥
     * 
     * @var string
     */
    private $_secret_key;

    /**
     * 邮件发送队列检查时间间隔
     * 
     * @var int
     */
    private $_queue_check_interval  = 60;

    /**
     * 客户机通信状态检查时间间隔
     * 
     * @var int
     */
    private $_client_check_interval = 60;

    /**
     * 系统服务状态
     * 
     * @var array
     */
    private $_status = array();

    /**
     * 构造器
     *
     * @param string $host
     * @param int $port
     * @throws \Exception
     */
    public function __construct($host, $port)
    {
        $this->_status['init_memory'] = memory_get_usage();
        $this->_host = $host;
        $this->_port = $port;
        if (!extension_loaded('swoole')) {
            throw new \Exception('无法加载[swoole]扩展');
        }
    }

    /**
     * 设置是否守护进程模式
     *
     * @param bool $daemonize
     * @return Monitor
     */
    public function setDaemonize($daemonize)
    {
        $this->_daemonize = (bool)$daemonize;
        return $this;
    }

    /**
     * 设置日志文件存储位置
     *
     * @param string $file
     * @return Monitor
     */
    public function setLogFile($file)
    {
        $this->_log_file = $file;
        return $this;
    }

    /**
     * 设置通信密钥
     * 
     * @param string $key
     * @return Monitor
     */
    public function setSecretKey($key)
    {
        $this->_secret_key = $key;
        return $this;
    }

    /**
     * 设置邮件发送服务器配置
     * 
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $pass
     * @param string $sender
     */
    public function setSmtpServer($host, $port, $user, $pass, $sender)
    {
        $this->_mail_server_host = $host;
        $this->_mail_server_port = $port;
        $this->_mail_server_user = $user;
        $this->_mail_server_pass = $pass;
        $this->_mail_sender = $sender;
        return $this;
    }

    /**
     * 设置邮件提醒级别，默认只提醒critical级别
     * 
     * @param int $level
     * @return Monitor
     */
    public function setNotifyLevel($level)
    {
        $this->_notify_level = $level;
        return $this;
    }

    /**
     * 设置邮件队列检测发送时间间隔(单位:s)
     * 
     * @param int $time
     * @return Monitor
     */
    public function setQueueCheckInterval($time)
    {
        $this->_queue_check_interval = $time;
        return $this;
    }

    /**
     * 设置客户机通信状态检测时间间隔(单位:s)
     * 
     * @param int $time
     * @return Monitor
     */
    public function setClientCheckInterval($time)
    {
        $this->_client_check_interval = $time;
        return $this;
    }

    /**
     * 设置邮件通知组
     * 
     * @param string $group_name
     * @param array $email_list
     * @return Monitor
     */
    public function addNotifyGroup($group_name, array $email_list = array())
    {
        $this->_notify_group[$group_name] = $email_list;
        return $this;
    }

    /**
     * 启动监控
     *
     */
    public function start()
    {
        $socket = new SwooleServer($this->_host, $this->_port, SWOOLE_BASE, SWOOLE_SOCK_TCP);
        $socket->set(array(
            'daemonize' => $this->_daemonize,
            'log_file'  => $this->_log_file
        ));
        $socket->addlistener($this->_host, $this->_port + 1, SWOOLE_SOCK_TCP);
        //监控进程启动时,初始化
        $socket->on('WorkerStart', function(SwooleServer $server, $worker_id) {
            cli_set_process_title('eagleye:server');
            $this->_status['start_time'] = time();
            $this->_status['notify_count'] = 0;
            //邮件服务配置
            $mail = new Mail();
            $mail->set('host', $this->_mail_server_host);
            $mail->set('port', $this->_mail_server_port);
            $mail->set('from', $this->_mail_sender);
            $mail->set('user', $this->_mail_server_user);
            $mail->set('pass', $this->_mail_server_pass);
            if (!$mail->get('host') || !$mail->get('port')
                    || !$mail->get('from') || !$mail->get('user')
                    || !$mail->get('pass')) {
                $this->_show('鹰眼监控服务初始化失败：邮件发送服务配置不完整', self::LOG_LEVEL_ERROR);
                exit();
            }
            $log = sprintf('鹰眼监控服务已就绪, pid=%s', $server->master_pid);
            $this->_show($log, self::LOG_LEVEL_NOTICE);
            //检查邮件发送队列发送邮件
            swoole_timer_tick($this->_queue_check_interval*1000, function($timer_id) use ($mail) {
                if (count($this->_notify_queue) > 0) {
                    while (true) {
                        if (!$task = array_shift($this->_notify_queue)) {
                            break;
                        }
                        $notify_group = array_map('trim', explode(',', $task['notify_group']));
                        foreach ($notify_group as $val) {
                            if ($email_list = $this->_notify_group[$val]) {
                                foreach ($email_list as $k=> $v) {
                                    $content = '***** Eagleye监控报告 *****<br/><br/>';
                                    $content.= '服务名：'. $task['service_name']. '<br/>';
                                    $content.= '客户机名称：'. $task['client_name']. '<br/>';
                                    $content.= '客户机IP：'. $task['client_addr']. '<br/>';
                                    $content.= '当前服务状态：Critical<br/>';
                                    $content.= '报告时间：'. date('Y-m-d H:i:s'). '<br/><br/>';
                                    $content.= $task['err_message'];
                                    $mail->set('to', $v);
                                    $mail->set('subject', sprintf('** PROBLEM Service Alert [%s] is CRITICAL **', $task['client_name']. ':'. $task['service_name']));
                                    $mail->set('content', $content);
                                    if ($result = $mail->send()) {
                                        $this->_status['notify_count'] += 1;
                                        $this->_show(sprintf('监控项[%s]报警邮件已发送到[%s]', $task['client_name']. ':'. $task['service_name'], $v));
                                    } else {
                                        $this->_show(sprintf('监控项[%s]报警邮件发送失败，邮件内容为: %s', $task['client_name']. ':'. $task['service_name'], $content));
                                    }
                                    $client_name = $task['client_name'];
                                    $service_name = $task['service_name'];
                                    if (isset($this->_storage[$client_name])) {
                                        $this->_storage[$client_name][$service_name]['last_notify_time'] = time();
                                        $this->_storage[$client_name][$service_name]['last_notify_datetime'] = date('Y-m-d H:i:s');
                                        $this->_storage[$client_name][$service_name]['last_notify_result'] = $result ? 'ok' : 'failed';
                                    }
                                }
                            }
                        }
                    }
                }
                $this->_show('邮件队列检查完毕');
            });
            //客户机连接状态检测
            swoole_timer_tick($this->_client_check_interval*1000, function($timer_id) use ($mail) {
                foreach ($this->_storage as $key=> $val) {
                    foreach ($val as $k=> $v) {
                        $last_report_time = $v['last_report_time'];
                        $interval = $v['run_interval'];
                        $max_failed = $v['max_failed'];
                        //如果当前时间大于上次客户机报告时间+最大允许延迟告警时间，则报告客户异常(允许延迟120秒后再报警)
                        if (time() - ($last_report_time + ($interval*$max_failed) + 120) > 0) {
                            if ($email_list = $this->_notify_group['sys_master']) {
                                foreach ($email_list as $email) {
                                    $service_summary = $v['client_name']. ':'. $v['service_name'];
                                    $content = '***** Eagleye监控报告 *****<br/><br/>';
                                    $content.= '服务名：客户机通信状态<br/>';
                                    $content.= '客户机名称：'. $v['client_name']. '<br/>';
                                    $content.= '客户机IP：'. $v['client_addr']. '<br/>';
                                    $content.= '当前服务状态：Critical<br/>';
                                    $content.= '报告时间：'. date('Y-m-d H:i:s'). '<br/><br/>';
                                    $content.= sprintf('监控服务[%s]超过[%s]秒无报告上传，请检查鹰眼客户端探针工作状态.', $service_summary, time() - $last_report_time);
                                    $mail->set('to', $email);
                                    $mail->set('subject', sprintf('** PROBLEM Service Alert [%s] connection is CRITICAL **', $service_summary));
                                    $mail->set('content', $content);
                                    if ($result = $mail->send()) {
                                        $this->_show(sprintf('监控项[%s]报警邮件已发送到[%s]', $service_summary, $email));
                                    } else {
                                        $this->_show(sprintf('监控项[%s]报警邮件发送失败，邮件内容为: %s', $service_summary, $content));
                                    }
                                    $client_name = $v['client_name'];
                                    $service_name = $v['service_name'];
                                    if (isset($this->_storage[$client_name])) {
                                        $this->_storage[$client_name][$service_name]['last_notify_time'] = time();
                                        $this->_storage[$client_name][$service_name]['last_notify_datetime'] = date('Y-m-d H:i:s');
                                        $this->_storage[$client_name][$service_name]['last_notify_result'] = $result ? 'ok' : 'failed';
                                    }
                                }
                            }
                        }
                        $this->_storage[$key][$k]['last_check_time'] = time();
                        $this->_storage[$key][$k]['last_check_datetime'] = date('Y-m-d H:i:s');
                    }
                }
                $this->_show('客户机通信状态检查完毕');
            });
        });
        //客户端建立连接时，处理客户机数据
        $socket->on('Connect', function(SwooleServer $server, $fd, $from_id) {
            $conn = $server->connection_info($fd);
            if ($conn['server_port'] != $this->_port) {
                $response = '>>> ';
                $server->send($fd, $response);
            }
        });
        //客户端发送sql请求时
        $socket->on('Receive', function(SwooleServer $server, $fd, $from_id, $data) {
            $conn = $server->connection_info($fd);
            //远程连接
            if ($conn['server_port'] == $this->_port) {
                $data = json_decode($data, true);
                $remote_auth_key = $data['client']['auth_key'];
                $client_name = $data['client']['client_name'];
                $client_addr = $data['client']['client_addr'];
                $service_name = $data['client']['service_name'];
                $local_auth_key = md5($client_name. $client_addr. $this->_secret_key);
                if ($remote_auth_key != $local_auth_key) {
                    $response = array(
                        'err_code'=> 9,
                        'err_message'=> '无效的通信认证密钥'
                    );
                    $server->send($fd, json_encode($response));
                    $server->close($fd);
                    return;
                }
                //监控结果上报
                if ($data['action'] == 'report') {
                    //先查看是否注册
                    if (!isset($this->_storage[$client_name])) {
                        $this->_storage[$client_name] = array();
                    }
                    if (!isset($this->_storage[$client_name][$service_name])) {
                        $this->_storage[$client_name][$service_name] = array(
                            'state'             => 'on',
                            'service_name'      => $service_name,
                            'client_name'       => $client_name,
                            'client_addr'       => $client_addr,
                            'run_interval'      => $data['client']['run_interval'],
                            'max_failed'        => $data['client']['max_failed'],
                            'notify_group'      => $data['client']['notify_group'],
                            'register_time'     => time(),
                            'register_datetime' => date('Y-m-d H:i:s'),
                            'ignore_failed'     => 0,
                            'remote_ip'         => $conn['remote_ip'],
                        );
                    } else {
                        $this->_storage[$client_name][$service_name]['run_interval'] = $data['client']['run_interval'];
                        $this->_storage[$client_name][$service_name]['max_failed'] = $data['client']['max_failed'];
                        $this->_storage[$client_name][$service_name]['notify_group'] = $data['client']['notify_group'];
                    }
                    $code = $data['data']['err_code'];
                    $message = $data['data']['err_message'];
                    //记录本次上报监控结果
                    $this->_storage[$client_name][$service_name]['last_report_code'] = $code;
                    $this->_storage[$client_name][$service_name]['last_report_message'] = $message;
                    $this->_storage[$client_name][$service_name]['last_report_time'] = time();
                    $this->_storage[$client_name][$service_name]['last_report_datetime'] = date('Y-m-d H:i:s');
                    //如果需要发邮件，则写入发邮件队列
                    $response = array(
                        'err_code'      => 0,
                        'err_message'   => ''
                    );
                    $server->send($fd, json_encode($response));
                    $this->_show(sprintf('接收到客户端[%s]检测反馈, 当前结果为[%s]', $client_name. ':'. $service_name, $code));
                    //上报结果判断逻辑，确认是否发送邮件提醒
                    if ($code >= $this->_notify_level
                            && $this->_storage[$client_name][$service_name]['state'] == 'on') {
                        $ignore_failed = $this->_storage[$client_name][$service_name]['ignore_failed'];
                        $max_failed = $this->_storage[$client_name][$service_name]['max_failed'];
                        if ($ignore_failed >= $max_failed) {
                            $this->_notify_queue[] = array(
                                'err_code'      => $code,
                                'err_message'   => $message,
                                'service_name'  => $this->_storage[$client_name][$service_name]['service_name'],
                                'client_name'   => $this->_storage[$client_name][$service_name]['client_name'],
                                'client_addr'   => $this->_storage[$client_name][$service_name]['client_addr'],
                                'notify_group'  => $this->_storage[$client_name][$service_name]['notify_group'],
                            );
                            $this->_storage[$client_name][$service_name]['ignore_failed'] = 0;
                            $notify_group = $this->_storage[$client_name][$service_name]['notify_group'];
                            $this->_show(sprintf('客户端[%s]监控报告已写入邮件发送队列，发送邮件组为[%s]', $client_name. ':'. $service_name, $notify_group));
                        } else {
                            $this->_storage[$client_name][$service_name]['ignore_failed'] += 1;
                        }
                    }
                }
            } else {    //本地console控制台连接
                $command = strtolower(trim($data));
                if ($command == 'help') {
                    $response = "show status\t\t\t\t\t\t\t查看监控服务器状态". PHP_EOL;
                    $response.= "show parameter [parameter_name]\t\t\t\t\t查看某个服务器参数值". PHP_EOL;
                    $response.= "show client [client_name]\t\t\t\t\t查看监控客户机状态". PHP_EOL;
                    $response.= "alter client set [client].[service].state=[on|off]\t\t修改客户机监控项数据". PHP_EOL;
                    $response.= "shutdown\t\t\t\t\t\t\t关闭主控端服务器". PHP_EOL;
                    $response.= "quit\t\t\t\t\t\t\t\t退出当前会话". PHP_EOL;
                    $response.= "help\t\t\t\t\t\t\t\t显示本帮助". PHP_EOL;
                    $response.= '>>> ';
                    $server->send($fd, $response);
                } elseif ($command == 'quit' || $command == 'exit') {
                    $server->send($fd, 'bye' . PHP_EOL);
                    $server->close($fd);
                    return;
                } elseif ($command == 'shutdown') {
                    $server->send($fd, '正在停止鹰眼主控端服务...'. PHP_EOL);
                    $this->_show('鹰眼主控服务器已关闭', self::LOG_LEVEL_NOTICE);
                    exit();
                } elseif ($command == 'show status') {
                    //计算服务器运行时长
                    $run_days = floor((time() - $this->_status['start_time']) / 86400);
                    $remain = (time() - $this->_status['start_time']) % 86400;
                    $run_hours = floor($remain/3600);
                    $remain = $remain % 3600;
                    $run_minutes = floor($remain/60);
                    //end.
                    $response = "系统名称: 鹰眼监控系统". PHP_EOL;
                    $response.= "注册主机: ". count($this->_storage). " 台". PHP_EOL;
                    $response.= "监控项目: ";
                    $service_count = 0;
                    $state_on = 0;
                    foreach ($this->_storage as $key=> $val) {
                        $service_count += count($val);
                        foreach ($val as $v) {
                            if ($v['state'] == 'on') $state_on += 1;
                        }
                    }
                    $response.= $service_count. " 个". PHP_EOL;
                    $response.= "有效项目: ". $state_on. " 个". PHP_EOL;
                    $response.= "当前告警: ". count($this->_notify_queue). " 条". PHP_EOL;
                    $response.= "累计告警: ". $this->_status['notify_count']. " 次". PHP_EOL;
                    $response.= "内存占用: ". $this->formatSize(memory_get_usage() - $this->_status['init_memory']). PHP_EOL;
                    $response.= "运行时长: ". $run_days. ' 天 '. $run_hours. ' 小时 '. $run_minutes. ' 分'. PHP_EOL;
                    $response.= "启动时间: ". date('Y-m-d h:i:s', $this->_status['start_time']). PHP_EOL;
                    $response.= '>>> ';
                    $server->send($fd, $response);
                } elseif (preg_match('/^show\s+parameter(\s+([\w\d]+))?$/', $command, $matches)) {
                    $parameter_name = $matches[2];
                    if (!$parameter_name) {
                        $response = "支持以下参数: ". PHP_EOL. PHP_EOL;
                        $response.= "host\t\t\t\t当前服务器IP". PHP_EOL;
                        $response.= "port\t\t\t\t当前服务器监控端口". PHP_EOL;
                        $response.= "log_file\t\t\t日志文件保存地址". PHP_EOL;
                        $response.= "notify_level\t\t\t邮件告警级别". PHP_EOL;
                        $response.= "smtp_host\t\t\t邮件服务器IP地址". PHP_EOL;
                        $response.= "smtp_port\t\t\t邮件服务器端口". PHP_EOL;
                        $response.= "smtp_username\t\t\t邮件用户登录名". PHP_EOL;
                        $response.= "smtp_password\t\t\t邮件用户登录密码". PHP_EOL;
                        $response.= "smtp_sender\t\t\t投递人邮箱地址". PHP_EOL;
                        $response.= "queue_check_interval\t\t邮件队列定期检查并发邮件时间". PHP_EOL;
                        $response.= "client_check_interval\t\t监控目标客户机通信状态检查时间". PHP_EOL;
                        $response.= "daemonize\t\t\t是否守护进程". PHP_EOL;
                        $response.= '>>> ';
                        $server->send($fd, $response);
                    } else {
                        $response = '';
                        $list_parameter = array(
                            'host'                  => '_host',
                            'port'                  => '_port',
                            'log_file'              => '_log_file',
                            'notify_level'          => '_notify_level',
                            'smtp_host'             => '_mail_server_host',
                            'smtp_port'             => '_mail_server_port',
                            'smtp_username'         => '_mail_server_user',
                            'smtp_password'         => '_mail_server_pass',
                            'smtp_sender'           => '_mail_sender',
                            'queue_check_interval'  => '_queue_check_interval',
                            'client_check_interval' => '_client_check_interval',
                            'daemonize'             => '_daemonize',
                        );
                        if (isset($list_parameter[$parameter_name])) {
                            $name = $list_parameter[$parameter_name];
                            $response.= $this->$name. PHP_EOL;
                        } else {
                            $response.= '无效的参数名称'. PHP_EOL;
                        }
                        $response.= '>>> ';
                        $server->send($fd, $response);
                    }
                } elseif (preg_match('/^show\s+client(\s+(.*?)(\.(.*?))?)?$/', $command, $matches)) {
                    $client = $matches[2];
                    $service = $matches[4];
                    $response = '';
                    if (!$client) {
                        ksort($this->_storage);
                        $column_width = 0;
                        $column_count = 0;
                        foreach ($this->_storage as $key=> $val) {
                            foreach ($val as $k=> $v) {
                                if (strlen($v['service_name']) > $column_width) {
                                    $column_width = strlen($v['service_name']);
                                }
                            }
                            $tmp_count = count($val);
                            if ($tmp_count > $column_count) {
                                $column_count = $tmp_count;
                            }
                        }
                        $column_width = $column_width + 7;
                        $line_width = $column_width * $column_count + 30;
                        if ($this->_storage) {
                            $response.= str_pad('', $line_width, '-'). PHP_EOL;
                        }
                        foreach ($this->_storage as $key=> $val) {
                            $response.= $key. ': '.  str_pad(' ', 15 - strlen($key)). '|';
                            ksort($val);
                            foreach ($val as $k=> $v) {
                                $item = $k. '('. $v['state']. ')';
                                $response.= str_pad(' ', $column_width - strlen($item)). $item. '|';
                            }
                            $response.= PHP_EOL;
                            $response.= str_pad('', $line_width, '-'). PHP_EOL;
                        }
                    } elseif (isset($this->_storage[$client])) {
                        $response.= $client. ': '. PHP_EOL;
                        $response.= str_pad('', 80, '-'). PHP_EOL;
                        if ($service && isset($this->_storage[$client][$service])) {
                            $response.= $service. PHP_EOL. PHP_EOL;
                            foreach ($this->_storage[$client][$service] as $k=> $v) {
                                $response.= $k. str_pad(' ', 30-strlen($k)). $v. PHP_EOL;
                            }
                            $response.= str_pad('', 80, '-'). PHP_EOL;
                        } else {
                            foreach ($this->_storage[$client] as $key=> $val) {
                                $response.= $key. PHP_EOL. PHP_EOL;
                                foreach ($val as $k=> $v) {
                                    $response.= $k. str_pad(' ', 30-strlen($k)). $v. PHP_EOL;
                                }
                                $response.= str_pad('', 80, '-'). PHP_EOL;
                            }
                        }
                    } else {
                        $response.= '该主机名未注册'. PHP_EOL;
                    }
                    $response.= '>>> ';
                    $server->send($fd, $response);
                } elseif (preg_match('/^alter\s+client\s+set\s+(.*?)\.(.*?)\.state\s*?=\s*?(.*?)$/', $command, $matches)) {
                    $client = $matches[1];
                    $service = $matches[2];
                    $value = $matches[3];
                    if (!isset($this->_storage[$client][$service])) {
                        $response = '无效的主机服务名，请检查'. PHP_EOL;
                    } elseif (!in_array($value, array('on', 'off'))) {
                        $response = '无效的属性值，仅支持(on|off)之一'. PHP_EOL;
                    } else {
                        $this->_storage[$client][$service]['state'] = $value;
                        $response = '状态已更改'. PHP_EOL;
                    }
                    $response.= '>>> ';
                    $server->send($fd, $response);
                } elseif (preg_match('/^alter\s+client\s+unset\s+(.*?)$/', $command, $matches)) {
                    $client = str_replace(array(' ', ','), ',', $matches[1]);
                    $client = array_filter(array_map('trim', explode(',', $client)));
                    foreach ($client as $v) {
                        if (isset($this->_storage[$v])) {
                            unset($this->_storage[$v]);
                        }
                    }
                    $response = '目标主机已删除'. PHP_EOL;
                    $response.= '>>> ';
                    $server->send($fd, $response);
                } else {
                    $response = '';
                    if ($command != '') {
                        $response.= '无效的操作, 使用[help]查看可用命令'. PHP_EOL;
                    }
                    $response.= '>>> ';
                    $server->send($fd, $response);
                }
            }
        });
        //客户端断开连接
        $socket->on('Close', function(SwooleServer $server, $fd) {
            //$conn = $server->connection_info($fd);
        });
        $socket->start();
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
        echo $content;
    }

    /**
     * 格式化大小值
     *
     * @param $size
     * @return string
     */
    private function formatSize($size)
    {
        $prec = 3;
        $size = round(abs($size));
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        if (!$size) {
            return str_repeat(' ', $prec). '0'. $units[0];
        }
        $unit = min(4, floor(log($size)/log(2)/10));
        $size = $size*pow(2, -10*$unit);
        $digi = $prec-1- floor(log($size)/log(10));
        $size = round($size*pow(10, $digi))*pow(10, -$digi);
        return $size. ' '. $units[$unit];
    }

}