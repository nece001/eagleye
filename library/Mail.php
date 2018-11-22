<?php

namespace Eagleye;

class Mail
{

    /**
     * 邮件头的分隔符
     *
     * @access private
     * @var int
     */
    private $_delimiter = "\r\n";

    /**
     * SMTP服务器地址
     *
     * @access private
     * @var string
     */
    private $_host = '';

    /**
     * smtp端口
     *
     * @access private
     * @var int
     */
    private $_port = 25;

    /**
     * SMTP服务器用户名
     *
     * @access private
     * @var string
     */
    private $_user = '';

    /**
     * SMTP服务器密码
     *
     * @access private
     * @var string
     */
    private $_pass = '';

    /**
     * 发布人邮件地址
     *
     * @access private
     * @var string
     */
    private $_from = '';

    /**
     * 收件人地址
     *
     * @access private
     * @var string
     */
    private $_to = '';

    /**
     * 邮件主题
     *
     * @access private
     * @var string
     */
    private $_subject = '';

    /**
     * 邮件内容
     *
     * @access private
     * @var string
     */
    private $_content = '';

    /**
     * 发送超时时间
     *
     * @access private
     * @var string
     */
    private $_timeout = 1;

    /**
     * 邮件编码
     *
     * @access private
     * @var string
     */
    private $_encoding = 'utf-8';

    /**
     * 日志记录
     *
     * @access private
     * @var array
     */
    private $_log = array();

    /**
     * 邮件是否准备就绪 
     * 
     * @return bool
     */
    public function isReady()
    {
        $items = array('_host', '_port', '_user', '_pass', '_from', '_to', '_subject');
        foreach ($items as $item) {
            $val = $this->$item;
            if (!$val) {
                return false;
            }
        }
        return true;
    }

    /**
     * 发送邮件
     *
     * @access public
     * @return boolean
     */
    public function send()
    {
        if (!$this->_host || !$this->_from
            || !$this->_to || !$this->_subject || !$this->_content
            || !$this->_encoding) {
            $this->log('CONNECT - Params missing.');
            return false;
        }

        $this->_timeout || $this->_timeout = 1;
        if (preg_match('/^(.+?)\s*\<(.+?)\>$/', $this->_from, $matches)) {
            $this->_from = '=?' . $this->_encoding . '?B?' . base64_encode($matches[1]) . "?= <" . $matches[2] . ">";
        }
        if (preg_match('/^(.+?)\s*\<(.+?)\>$/', $this->_to, $matches)) {
            $this->_to = '=?' . $this->_encoding . '?B?' . base64_encode($matches[1]) . "?= <" . $matches[2] . ">";
        }
        $this->_subject = '=?' . $this->_encoding . '?B?' . base64_encode($this->_subject) . '?=';
        $this->_content = chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", str_replace("\n\r", "\r", $this->_content))))));
        $headers = "From: {$this->_from}{$this->_delimiter}X-Priority: 3{$this->_delimiter}MIME-Version: 1.0{$this->_delimiter}Content-type: text/html; charset=" . $this->_encoding . "{$this->_delimiter}Content-Transfer-Encoding: base64{$this->_delimiter}";
        if (!$fp = fsockopen($this->_host, $this->_port, $errno, $errstr, $this->_timeout)) {
            $this->log("CONNECT - Unable to connect to the SMTP server");
            return false;
        }
        stream_set_blocking($fp, true);
        $lastmessage = fgets($fp, 512);
        $this->log("{$this->_host}:{$this->_port} CONNECT - $lastmessage");
        if (substr($lastmessage, 0, 3) != '220') {
            return false;
        }
        fputs($fp, ($this->_user ? 'EHLO' : 'HELO') . " ". $this->_user. "\r\n");
        $lastmessage = fgets($fp, 512);
        $this->log("HELO/EHLO - $lastmessage");
        if (substr($lastmessage, 0, 3) != 220 && substr($lastmessage, 0, 3) != 250) {
            return false;
        }
        while (true) {
            if (substr($lastmessage, 3, 1) != '-' || empty($lastmessage)) {
                break;
            }
            $lastmessage = fgets($fp, 512);
        }
        if ($this->_user) {
            fputs($fp, "AUTH LOGIN\r\n");
            $lastmessage = fgets($fp, 512);
            $this->log("AUTH LOGIN - $lastmessage");
            if (substr($lastmessage, 0, 3) != 334) {
                return false;
            }
            fputs($fp, base64_encode($this->_user) . "\r\n");
            $lastmessage = fgets($fp, 512);
            $this->log("USERNAME - $lastmessage");
            if (substr($lastmessage, 0, 3) != 334) {
                return false;
            }
            fputs($fp, base64_encode($this->_pass) . "\r\n");
            $lastmessage = fgets($fp, 512);
            $this->log("PASSWORD - $lastmessage");
            if (substr($lastmessage, 0, 3) != 235) {
                return false;
            }
        }
        fputs($fp, "MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $this->_from) . ">\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 250) {
            fputs($fp, "MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $this->_from) . ">\r\n");
            $lastmessage = fgets($fp, 512);
            $this->log("MAIL FROM - $lastmessage");
            if (substr($lastmessage, 0, 3) != 250) {
                echo $lastmessage;
                return false;
            }
        }
        fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $this->_to) . ">\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 250) {
            fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $this->_to) . ">\r\n");
            $lastmessage = fgets($fp, 512);
            $this->log("RCPT TO - $lastmessage");
            return false;
        }
        fputs($fp, "DATA\r\n");
        $lastmessage = fgets($fp, 512);
        $this->log("DATA - $lastmessage");
        if (substr($lastmessage, 0, 3) != 354) {
            return false;
        }
        $headers .= 'Message-ID: <' . gmdate('YmdHs') . '.' . substr(md5($this->_content . microtime()), 0, 6) . rand(100000, 999999) . '@' . $_SERVER['HTTP_HOST'] . ">{$this->_delimiter}";
        fputs($fp, "Date: " . gmdate('r') . "\r\n");
        fputs($fp, "To: " . $this->_to . "\r\n");
        fputs($fp, "Subject: " . $this->_subject . "\r\n");
        fputs($fp, $headers . "\r\n");
        fputs($fp, "\r\n\r\n");
        fputs($fp, "$this->_content\r\n.\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 250) { }
        fputs($fp, "QUIT\r\n");
        return true;
    }

    /**
     * 日志
     *
     * @access public
     * @param string $info
     * @return mixed
     */
    public function log($info = null)
    {
        if (is_null($info)) {
            return $this->_log;
        }
        $this->_log[] = trim($info);
        return $this;
    }

    /**
     * 设值
     *
     * @access public
     * @param string $name
     * @param mixed $value
     * @return null
     */
    public function set($name, $value)
    {
        $property = '_'. $name;
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
        return $this;
    }

    /**
     * 取值
     *
     * @access public
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        $property = '_'. $name;
        return property_exists($this, $property) ? $this->$property : false;
    }

}