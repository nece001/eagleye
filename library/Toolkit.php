<?php

namespace Eagleye;

class Toolkit
{

    /**
     * 检测端口是否开放
     * 
     * @param string $host
     * @param int $port
     * @return array
     */
    public static function checkServerPort($host, $port, $err_msg = '')
    {
        $socket = fsockopen($host, $port, $errno, $errstr, 5);
        $opened = $socket ? true : false;
        fclose($socket);
        return $opened 
            ? array('err_code'=> 0, 'err_message'=> '')
            : array('err_code'=> 2, 'err_message'=> $err_msg);
    }

    public static function checkDiskSpace($disk, $critical = '90%', $err_msg = '')
    {

    }

    /**
     * 检查系统负载情况
     * 
     * @param int $critical
     * @param string $err_msg
     * @return array
     */
    public static function checkLoadAverage($critical = 20, $err_msg = '')
    {
        $uptime = exec('uptime');
        $matched = preg_match('/load\s+averages?:\s+([\d\.]+)/', $uptime, $matches);
        if (!$matched || floatval($matches[1]) > $critical) {
            return array('err_code'=> 2, 'err_message'=> $err_msg. '<br/><br/>'. $uptime);
        } else {
            return array('err_code'=> 0, 'err_message'=> '');
        }
    }

    /**
     * 集群服务状态检测
     * 
     * @param string $err_str
     * @return array
     */
    public static function checkOracleCrsState($err_msg = '')
    {
        exec("su - oracle -c 'crs_stat'", $result);
        if ($result) {
            $failed = array();
            $tmp = '';
            foreach ($result as $key=> $val) {
                if (strpos($val, 'NAME=') !== false) {
                    $tmp = $val;
                } elseif (strpos($val, 'STATE=OFFLINE') !== false) {
                    $failed[] = $tmp;
                    $failed[] = $val;
                } elseif (trim($val) == '') {
                    $tmp = '';
                }
            }
            return $failed 
                ? array('err_code'=> 2, 'err_message'=> $err_msg. '<br/><br/>'. implode('<br/>', $failed))
                : array('err_code'=> 0, 'err_message'=> '');
        } else {
            return array('err_code'=> 2, 'err_message'=> '无法获取CRS服务状态');
        }
    }

    /**
     * 检查系mysql从库情况
     * 
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $err_msg
     * @return array
     */
    public static function checkMysqlSlaveStatus($host, $user, $pass, $err_msg = '')
    {
        $cmd = sprintf('mysql -h%s -u%s -p%s -e "show slave status\G" | grep -E "Seconds_Behind_Master|Slave_IO_Running|Slave_SQL_Running"', $host, $user, $pass);
	    exec($cmd, $result);
        if ($result) {
	        $result = array_map('trim', $result);
            foreach ($result as $k=> $v) {
                $v = array_map('trim', explode(':', $v));
                if ($v[0] == 'Seconds_Behind_Master' && intval($v[1]) > 0
                        || $v[0] == 'Slave_IO_Running' && $v[1] == 'No'
                        || $v[0] == 'Slave_SQL_Running' && $v[1] == 'No') {
                    return array('err_code'=> 2, 'err_message'=> $err_msg. '<br/><br/>'. implode('<br/>', $result));
                }
            }
        }
        return array('err_code'=> 0, 'err_message'=> '');
    }

}
