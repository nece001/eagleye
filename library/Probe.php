<?php

namespace Eagleye;

class Probe
{

    /**
     * 探针名称
     * 
     * @var string
     */
    private $_name;

    /**
     * 当前主机名称
     * 
     * @var string
     */
    private $_client_name;

    /**
     * 当前主机IP地址
     * 
     * @var string
     */
    private $_client_addr;

    /**
     * 重复执行时间间隔
     * 
     * @var int
     */
    private $_interval = 180;

    /**
     * 最大允许错误次数
     * 
     * @var int
     */
    private $_max_failed = 3;

    /**
     * 控制执行方法
     * 
     * @var \Clusore
     */
    private $_method;

    /**
     * 探针结果通知邮件组
     * 
     * @var array
     */
    private $_notify_group = array();

    /**
     * 构造器
     * 
     * @param string $name
     * @return null
     */
    public function __construct($name = null)
    {
        is_null($name) || $this->_name = $name;
    }

    /**
     * 设置探针名称
     * 
     * @param string $name
     * @return Probe
     */
    public function setName($name)
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * 获取探针名称
     * 
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * 设置客户机名称
     * 
     * @param string $name
     * @return Probe
     */
    public function setClientName($name)
    {
        $this->_client_name = $name;
        return $this;
    }

    /**
     * 获取客户机名称
     * 
     * @return string
     */
    public function getClientName()
    {
        return $this->_client_name;
    }

    /**
     * 设置客户机IP地址
     * 
     * @param string $addr
     * @return Probe
     */
    public function setClientAddr($addr)
    {
        $this->_client_addr = $addr;
        return $this;
    }

    /**
     * 获取客户机IP地址
     * 
     * @return string
     */
    public function getClientAddr()
    {
        return $this->_client_addr;
    }

    /**
     * 设置重复检查时间
     * 
     * @param int $interval
     * @return Probe
     */
    public function setInterval($interval = 180)
    {
        $this->_interval = $interval;
        return $this;
    }

    /**
     * 获取重复检查时间设置
     * 
     * @return int
     */
    public function getInterval()
    {
        return $this->_interval;
    }

    /**
     * 设置最大允许失败次数
     * 
     * @param int $number
     * @return Probe
     */
    public function setMaxFailed($number = 3)
    {
        $this->_max_failed = $number;
        return $this;
    }

    /**
     * 获取最大允许失败次数
     * 
     * @return int
     */
    public function getMaxFailed($number)
    {
        return $this->_max_failed;
    }

    /**
     * 设置探针方法
     * 
     * @param \Closure $method
     * @return Probe
     */
    public function setMethod($method)
    {
        $this->_method = $method;
        return $this;
    }

    /**
     * 获取探针方法
     * 
     * @return \Closure
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * 设置通知邮件组
     * 
     * @param array $mailgroup
     * @return Probe
     */
    public function setNotifyGroup($mailgroup)
    {
        if (!is_array($mailgroup)) {
            $mailgroup = array($mailgroup);
        }
        $this->_notify_group = $mailgroup;
        return $this;
    }

    /**
     * 获取通知邮件组
     * 
     * @return array
     */
    public function getNotifyGroup()
    {
        return $this->_notify_group;
    }

    /**
     * 自查属性值
     * 
     * @return bool
     */
    public function isReady()
    {
        if (!$this->_name || !$this->_client_name
                || !$this->_client_addr 
                || !$this->_method || !is_callable($this->_method)
                || !$this->_notify_group) {
            return false;
        }
        return true;
    }

}