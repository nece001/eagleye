<?php

require_once __DIR__. '/../boot.php';

use Eagleye\Radar;
use Eagleye\Probe;
use Eagleye\Toolkit;

$radar = new Radar();
$radar->setServer('127.0.0.1', 9501);
$radar->setDaemonize(true);
$radar->setSecretKey(SECRET_KEY);

$client_name = 'app150';
$client_addr = '33.5.71.150';

//nginx
$probe = new Probe('nginx');
$probe->setMethod(function() {
    $err_msg = '服务器[app150]端口[80]无法连接';
    return Toolkit::checkServerPort('127.0.0.1', 80, $err_msg);
});
$probe->setClientName($client_name);
$probe->setClientAddr($client_addr);
$probe->setInterval(60);
$probe->setNotifyGroup(array('sys_master'));
$radar->addProbe($probe);

//php-fpm
$probe = new Probe('php-fpm');
$probe->setMethod(function() {
    $err_msg = '服务器[app150]端口[9000]无法连接';
    return Toolkit::checkServerPort('127.0.0.1', 9000, $err_msg);
});
$probe->setClientName($client_name);
$probe->setClientAddr($client_addr);
$probe->setInterval(60);
$probe->setNotifyGroup(array('sys_master'));
$radar->addProbe($probe);

//load avaerage
$probe = new Probe('load');
$probe->setMethod(function() {
    $err_msg = '服务器[app150]系统负载过高[>=20]';
    return Toolkit::checkLoadAverage(20, $err_msg);
});
$probe->setClientName($client_name);
$probe->setClientAddr($client_addr);
$probe->setInterval(60);
$probe->setNotifyGroup(array('sys_master'));
$radar->addProbe($probe);

$radar->start();