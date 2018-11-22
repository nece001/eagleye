<?php

namespace Eagleye;

use PDO;
use PDOException;

class Database extends PDO
{
    
    /**
     * SQL语句执行后影响到的行数
     * 
     * @access protected
     * @var integer
     */
    private $_row_count = 0;

    /**
     * 执行一个SQL语句
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return int
     */
    public function query($sql, array $bind = array())
    {
        $smt = $this->_query($sql, $bind);
        $result = $smt->rowCount();
        $smt = null;
        return $result;
    }

    /**
     * 返回所有数据
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     */
    public function fetchAll($sql, array $bind = array()) 
    {
        $smt = $this->_query($sql, $bind);
        $result = $smt->fetchAll(PDO::FETCH_ASSOC);
        $smt = null;
        return $result;
    }

    /**
     * 返回第一行第一列数据，一般用在聚合函数中
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     */
    public function fetchOne($sql, array $bind = array())
    {
        $smt = $this->_query($sql, $bind);
        $result = $smt->fetchColumn(0);
        $smt = null;
        return $result;
    }

    /**
     * 返回单行数据
     *
     * @access public
     * @param string $sql
     * @param array $bind
     * @return mixed
     */
    public function fetch($sql, array $bind = array()) 
    {
        $smt = $this->_query($sql, $bind);
        $result = $smt->fetch(PDO::FETCH_ASSOC);
        $smt = null;
        return $result;
    }

	/**
	 * 返回受影响的行数
	 *
	 * @access public
	 * @return integer
	 */
	public function getLastRowCount()
	{
		return $this->_row_count;
    }
    
    /**
     * 执行SQL语句并返回一个PDOStatement对象
     *
     * @param string $sql
     * @param array $bind
     * @return PDOStatement
     */
    private function _query($sql, $bind = array()) 
    {
        $smt = $this->prepare($sql);
        if ($bind) {
            foreach ($bind as $key=> $val) {
                is_string($key) || $key += 1;
                $smt->bindParam($key, $val);
            }
        }
        $smt->execute();
        $smt->setFetchMode(PDO::FETCH_ASSOC);
        $this->_row_count = $smt->rowCount();
        return $smt;
    }
    
}