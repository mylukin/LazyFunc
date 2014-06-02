<?php

/**
 *  DataAccess.php
 *
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2014-05-30 17:21
 */
class DataAccess {
    // DataAccess instance
    private static $instance;

    /**
     * Returns DataAccess instance.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     * @return DataAccess
     */
    public static function &instance($dsn, $username = '', $password = '', $options = array()) {
        if (!(self::$instance instanceof DataAccess)) {
            self::$instance = new DataAccess($dsn, $username, $password, array_merge(array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            ), $options));
        }
        return self::$instance;
    }

    /**
     * @var PDO
     */
    private $dbh;
    /**
     * @var PDOStatement
     */
    private $sth;
    /**
     * @var string
     */
    private $delimiter = '`';
    /**
     * @var string
     */
    public $queryString = '';

    public function __construct($dsn, $username = '', $password = '', $options = array()) {
        // 连接PDO
        $this->dbh = new PDO($dsn, $username, $password, $options);
    }

    public function __destruct() {
        // 释放连接
        $this->dbh = null;
    }

    /**
     * 执行SQL，不返回执行结果
     *
     * @param string $query
     * @return bool
     */
    public function execute($query) {
        $params = func_get_args();
        // 兼容标签情况
        if (count($params) == 2 && is_array($params[1])) {
            $params = $params[1];
        } else {
            array_shift($params);
        }
        // 拼装SQL，为了Debugger
        $this->queryString = $this->buildQuery($query, $params);
        // 预编译SQL
        $this->sth = $this->dbh->prepare($query);
        // 执行SQL
        return $this->sth->execute($params);
    }

    /**
     * 执行SQL，并返回执行结果
     *
     * @param string $query
     * @return array
     */
    public function query($query) {
        if (call_user_func_array(array(&$this, 'execute'), func_get_args())) {
            return $this->fetchAll();
        } else {
            return array();
        }
    }

    /**
     * 获得一条记录
     *
     * @return bool|array
     */
    public function fetch() {
        if ($this->sth instanceof PDOStatement) {
            return call_user_func_array(array(&$this->sth, 'fetch'), func_get_args());
        } else {
            return false;
        }
    }

    /**
     * 获取执行的所有结果
     *
     * @return array
     */
    public function fetchAll() {
        if ($this->sth instanceof PDOStatement) {
            return call_user_func_array(array(&$this->sth, 'fetchAll'), func_get_args());
        } else {
            return array();
        }
    }

    /**
     * 从结果集中的下一行返回单独的一列
     *
     * @param int $column_number
     * @return bool|string
     */
    public function fetchColumn($column_number = 0) {
        if ($this->sth instanceof PDOStatement) {
            return call_user_func_array(array(&$this->sth, 'fetchColumn'), func_get_args());
        } else {
            return false;
        }
    }

    /**
     * 返回结果集中的列数
     *
     * @return int
     */
    public function columnCount() {
        if ($this->sth instanceof PDOStatement) {
            return $this->sth->columnCount();
        } else {
            return 0;
        }
    }

    /**
     * 返回影响行数
     *
     * @return int
     */
    public function rowCount() {
        if ($this->sth instanceof PDOStatement) {
            return $this->sth->rowCount();
        } else {
            return 0;
        }
    }

    /**
     * 插入数据
     *
     * @param string $table
     * @param array $data
     * @return bool|int
     */
    public function insert($table, $data) {
        $cols = array();
        $vals = array();
        foreach ($data as $col => $val) {
            $cols[] = $this->identifier($col);
            $vals[] = $val;
        }

        $sql = "insert into "
            . $this->identifier($table)
            . ' (' . implode(', ', $cols) . ') '
            . "values ('" . implode("', '", array_fill(0, count($vals), '?')) . "')";

        if ($this->execute($sql, $vals)) {
            return $this->lastInsertId();
        } else {
            return false;
        }
    }

    /**
     * 更新数据表
     *
     * @param string $table
     * @param array $data
     * @param string $conditions
     * @return int
     */
    public function update($table, $data, $conditions) {
        $params = func_get_args();
        $argLen = count($params);
        $isLabel = false;
        if ($argLen > 3 && is_array($params[3])) {
            $keys = implode(', ', array_keys($params[3]));
            // :id, :sn
            if (preg_match('/^\:[\w]+/', $keys)) {
                $isLabel = true;
            }
            $params = $params[3];
        } elseif ($argLen > 3) {
            $params = array_slice($params, 3);
        } else {
            $params = array();
        }
        // extract and quote col names from the array keys
        $sets = array();
        $vals = array();
        foreach ($data as $col => $val) {
            if (substr($col, -1) == '+') {
                $col = rtrim($col, '+');
                $icol = $this->identifier($col);
                $sets[] = $icol . ' = ' . $icol . ' + ' . ($isLabel ? ':' . $col : '?');
            } elseif (substr($col, -1) == '-') {
                $col = rtrim($col, '-');
                $icol = $this->identifier($col);
                $sets[] = $icol . ' = ' . $icol . ' - ' . ($isLabel ? ':' . $col : '?');
            } else {
                $sets[] = $this->identifier($col) . ' = ' . ($isLabel ? ':' . $col : '?');
            }
            if ($isLabel) {
                $vals[':' . $col] = $val;
            } else {
                $vals[] = $val;
            }
        }
        // build the statement
        $sql = "update "
            . $this->identifier($table)
            . ' set ' . implode(', ', $sets)
            . ' where ' . $conditions;

        $params = array_merge($vals, $params);
        if ($this->execute($sql, $params)) {
            return $this->rowCount();
        } else {
            return 0;
        }
    }

    /**
     * 返回最后插入行的ID或序列值
     *
     * @return int
     */
    public function lastInsertId() {
        if ($this->dbh instanceof PDO) {
            return $this->dbh->lastInsertId();
        } else {
            return 0;
        }
    }

    /**
     * Quotes a string for use in a query.
     *
     * @param string $value
     * @return string
     */
    public function quote($value) {
        if ($this->dbh instanceof PDO) {
            return $this->dbh->quote($value);
        } else {
            return addslashes($value);
        }
    }

    /**
     * 给字段加标识
     *
     * @param $filed
     * @return null|string
     */
    private function identifier($filed) {
        $result = null;
        // 检测是否是多个字段
        if (strpos($filed, ',') !== false) {
            // 多个字段，递归执行
            $fileds = explode(',', $filed);
            foreach ($fileds as $v) {
                if (empty($result)) {
                    $result = $this->identifier($v);
                } else {
                    $result .= ',' . $this->identifier($v);
                }
            }
            return $result;
        } else {
            // 解析各个字段
            if (strpos($filed, '.') !== false) {
                $fileds = explode('.', $filed);
                $_table = trim($fileds[0]);
                $_filed = trim($fileds[1]);
                $_as = chr(32) . 'as' . chr(32);
                if (stripos($_filed, $_as) !== false) {
                    $_filed = sprintf(($this->delimiter . '%s' . $this->delimiter . '%s' . $this->delimiter . '%s' . $this->delimiter), trim(substr($_filed, 0, stripos($_filed, $_as))), $_as, trim(substr($_filed, stripos($_filed, $_as) + 4)));
                }
                return sprintf($this->delimiter . '%s' . $this->delimiter . '.%s', $_table, $_filed);
            } else {
                return sprintf($this->delimiter . '%s' . $this->delimiter, $filed);
            }
        }
    }

    /**
     * 拼装SQL
     *
     * @param string $query
     * @param array $params
     * @return string
     */
    private function buildQuery($query, $params) {
        $keys = array();
        $values = array();

        # build a regular expression for each parameter
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/:' . ltrim($key, ':') . '/';
            } else {
                $keys[] = '/[?]/';
            }

            if (is_numeric($value)) {
                $values[] = $value;
            } else {
                $values[] = $this->quote($value);
            }
        }
        return preg_replace($keys, $values, $query, 1, $count);
    }
}