<?php

require_once __DIR__. '/../boot.php';

use Eagleye\Monitor;

$sys_master = array('guodalu@qq.com');

$smtp_host = 'example.smtp.mail.com';
$smtp_port = 25;
$smtp_user = 'username@mail.com';
$smtp_pass = 'password';
$smtp_sender = 'fromuser@mail.com';

$monitor = new Monitor('0.0.0.0', 9501);
$monitor->setDaemonize(true);
$monitor->setLogFile('/tmp/monitor.log');
$monitor->setNotifyLevel(Monitor::NOTIFY_LEVEL_CRITICAL);
$monitor->setSecretKey(SECRET_KEY);
$monitor->setSmtpServer($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_sender);
$monitor->addNotifyGroup('sys_master', $sys_master);
$monitor->start();
