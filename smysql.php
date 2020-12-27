<?php
  define("SQ_ORDER_ASC", 1);
  define("SQ_ORDER_DESC", 2);
  define("SQ_JOIN_INNER", 4);
  define("SQ_JOIN_LEFT", 8);
  define("SQ_JOIN_RIGHT", 16);
  define("SQ_JOIN_FULL", 32);
  define("SQ_INSERT_RETURN_ID", 64);
  define("SQ_COND_AND", 128);
  define("SQ_COND_OR", 256);
  define("SQ_FETCH_OBJECT", 512);
  define("SQ_FETCH_ARRAY", 1024);
  define("SQ_FETCH_ALL", 2048);
  define("SQ_FETCH_SMART", 4096);
  define("SQ_NO_ERROR", 8192);
  class SmysqlException extends Exception {
    public function __construct($e, $code = 0, Throwable $previous = NULL) {
      $gt = $this->getTrace();
      $egt = end($gt);
      if($gt[0]['file']==__FILE__) {
        foreach($gt as $k => $v) {
          if($v['file']!=__FILE__) {
            $egt = $v;
            break;
          };
        };
      };
      $this->message = $e;
      $this->file = $egt['file'];
      $this->line = $egt['line'];
    }
    public function __toString() {
      ob_start();
      trigger_error("<strong>Super-MySQL error</strong> " . $this->message . "in <strong>" . $this->file . "</strong> on line <strong>" . $this->line . "</strong>");
      ob_clean();
      die("<br /><strong>Super-MySQL error</strong> " . $this->message . "in <strong>" . $this->file . "</strong> on line <strong>" . $this->line . "</strong>");
      return "<strong>Super-MySQL error</strong> " . $this->message;
    }
  };

  abstract class SMQ {
    const ORDER_ASC = 1;
    const ORDER_DESC = 2;
    const JOIN_INNER = 4;
    const JOIN_LEFT = 8;
    const JOIN_RIGHT = 16;
    const JOIN_FULL = 32;
    const INSERT_RETURN_ID = 64;
    const COND_AND = 128;
    const COND_OR = 256;
    const FETCH_OBJECT = 512;
    const FETCH_ARRAY = 1024;
    const FETCH_ALL = 2048;
    const FETCH_SMART = 4096;
    const NO_ERROR = 8192;

    static function open($host = "", $user = "", $password = "", $db = "", &$object = "return") {
      if($object=="return")
        return new Smysql($host, $user, $password, $db);
      else
        $object = new Smysql($host, $user, $password, $db);
    }
  };
  class Smysql extends SMQ {
    protected $connect;
    protected $result;
    protected $host;
    protected $user;
    protected $password;
    protected $db;
    protected $fncs = [];
    public function __construct($host = "", $user = "", $password = "", $database = "") {
      if(empty($host) && empty($user) && empty($password) && empty($database)) {
        if(!empty($this->host)) {
          $host = $this->host;
          $user = $this->user;
          $password = $this->password;
          $database = $this->db;
        };
        if(empty($host) || empty($user) || empty($password)) {
          throw new SmysqlException("(__construct): Host, user and password parameters are required");
        };
      };
      $this->host = $host;
      $this->user = $user;
      $this->password = $password;
      $this->db = $database;
      if(str_replace("create[", "", $database)!=$database && end(str_split($database))=="]") {
        $new = str_replace("]", "", str_replace("create[", "", $database));
        $this->connect = @new PDO("mysql:host=" . $host, $user, $password);
        if(!$this->query("
          CREATE DATABASE $new
          ", "__construct"))
          throw new SmysqlException("(__construct): Can’t create database " . $new);
        $this->connect = NULL;
      };
      try {
        $this->connect = @new PDO("mysql:" . (empty($database) ? "" : "dbname=" . $database . ";") . "host=" . $host . ";charset=utf8", $user, $password);
      } catch(PDOException $e) {
        throw new SmysqlException("(__construct): Can’t connect to MySQL: " . $e->getMessage());
      }
      if($this->connect->errorCode() && $this->connect) {
        throw new SmysqlException("(__construct): Can’t select MySQL database (" . $this->connect->errorInfo()[2] . ")");
        $this->connect->close();
      };
    }

    public function escape($string) {
      if(is_array($string)) {
        foreach($string as $k => $v) {
          $r[$k] = $this->escape($v);
        };
        return $r;
      };
      $quote = $this->connect->quote($string);
      $quoteA = str_split($quote);
      unset($quoteA[0]);
      unset($quoteA[count($quoteA)]);
      $quote = "";
      foreach($quoteA as $k => $v) {
        $quote.= $v;
      };
      return $quote;
    }

    public function reload() {
      $this->__construct();
    }

    public function query($query, $flags = 0, $fnc = "Query") {
      if(empty($this->db) && !in_array($fnc, ["__construct", "changeDB", "dbList"]))
        throw new SmysqlException("(" . $fnc . "): No database selected");
      $qr = $this->connect->query($query);
      if(!$qr && !($flags & self::NO_ERROR))
        throw new SmysqlException("(" . $fnc . "): Error in MySQL: <em>" . $this->connect->errorInfo()[2] . "</em> <strong>SQL command:</strong> <code>" . (empty($query) ? "<em>Missing</em>" : $query) . "</code>");
      $this->result = new SmysqlResult($qr);
      return $this->result;
    }

    public function queryf($q, $a, $flags = 0, $fnc = "Queryf") {
      for($i = bcsub(count($a), 1); $i>=0; $i--) {
        $k = array_keys($a)[$i];
        $v = $a[$k];
        $q = str_replace("%" . $k, $this->escape($v), $q);
      };
      return $this->query($q, $flags, $fnc);
    }

    public function __set($name, $query) {
      $this->fncs[$name] = $query;
    }

    public function __get($name) {
      return $this->fncs[$name];
    }

    public function __call($name, $params) {
      if(isset($params[0]) && is_array($params[0]))
        $this->execFnc($name, $params[0]);
      else
        $this->execFnc($name, $params);
    }

    public function __isset($name) {
      return isset($this->fncs[$name]);
    }

    public function __unset($name) {
      if(isset($this->fncs[$name]))
        unset($this->fncs[$name]);
      return true;
    }

    public function setFnc($name, $query) {
      $this->fncs[$name] = $query;
    }

    public function execFnc($name, $params = [], $flags = 0) {
      if(isset($this->fncs[$name]))
        $this->queryf($this->fncs[$name], $params, $flags, $name);
      else
        throw new SmysqlException("(" . $name . "): This function isn’t defined");
    }

    public function dbList($flags = 0) {
      $result = $this->result;
      $this->query("SHOW DATABASES", $flags, "dbList");
      $r = [];
      while($f = $this->fetch()) {
        $r[] = $f->Database;
      };
      $this->result = $result;
      return $r;
    }

    public function tableList($flags = 0) {
      if(empty($this->db))
        throw new SmysqlException("(tableList): No database selected");
      $result = $this->result;
      $this->query("SHOW TABLES FROM `$this->db`", "tableList", $flags);
      $r = [];
      while($f = $this->fetch(SMQ::FETCH_ARRAY)) {
        $r[] = $f[0];
      };
      $this->result = $result;
      return $r;
    }

    public function result() {
      return $this->result;
    }

    public function charset($charset, $flags = 0) {
      return $this->query("SET NAMES " . $charset, $flags, "charset");
    }

    public function fetch($flags = 512) {
      $id = $this->result;
      return $id->fetch($flags);
    }

    public function deleteDB($db, $flags) {
      if($this->query("DROP DATABASE `$db`", $flags, "deleteDB")) {
        return true;
      }
      else {
        throw new SmysqlException("(deleteDB): Can’t delete database " . $db);
        return false;
      };
    }

    public function changeDB($newDB) {
      if($this->result instanceof SmysqlResult)
        $this->result->__destruct();
      $this->connect = NULL;
      $this->db = $newDB;
      $this->reload();
    }

    public function select($table, $order = "", $cols = "*", $limit = NULL, $flags = 129, $name = "select") {
      if(is_array($cols)) {
        foreach($cols as $k => $v) {
  				if(gettype($k)!="integer")
  					$cols[$k] = "`" . $v . "`" . " AS " . "`" . $k . "`";
          else
            $cols[$k] = "`" . $v . "`";
  			};
        $colsValue = implode(", ", $cols);
      }
      else
        $colsValue = strval($cols);
			$limitString = ($limit===NULL || $limit==="") ? "" : " LIMIT " . intval($this->escape($limit));
			if(!empty($order))
        $order = " ORDER BY `" . $order . "`" . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue FROM `$table`$limitString$order
      ", $flags, $name);
    }

    public function selectWhere($table, $cond, $order = "", $cols = "*", $limit = NULL, $flags = 129, $name = "selectWhere") {
      $all = !boolval($cond & self::COND_OR);
      if(is_array($cols)) {
        foreach($cols as $k => $v) {
  				if(gettype($k)!="integer")
  					$cols[$k] = "`" . $v . "`" . " AS " . "`" . $k . "`";
          else
            $cols[$k] = "`" . $v . "`";
  			};
        $colsValue = implode(", ", $cols);
      }
      else
        $colsValue = strval($cols);
      $condString = $this->getCondString($cond, $all);
			$limitString = ($limit===NULL || $limit==="") ? "" : " LIMIT " . intval($this->escape($limit));
      if(!empty($order))
        $order = " ORDER BY `" . $order . "`" . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue FROM `$table` WHERE $condString$limitString$order
      ", $flags, $name);
    }

    public function selectJoin($table, $join, $on, $order = "", $cols = "*", $limit = NULL, $flags = 133, $name = "selectJoin") {
      $all = !boolval($flags & self::COND_OR);
      switch(true) {
        case boolval($flags & self::JOIN_LEFT): $jt = "LEFT OUTER"; break;
        case boolval($flags & self::JOIN_RIGHT): $jt = "RIGHT OUTER"; break;
        case boolval($flags & self::JOIN_FULL): $jt = "FULL OUTER"; break;
        default: $jt = "INNER";
      };
      if(is_array($cols)) {
        foreach($cols as $k => $v) {
  				if(gettype($k)!="integer")
  					$cols[$k] = "`" . $v . "`" . " AS " . "`" . $k . "`";
          else
            $cols[$k] = "`" . $v . "`";
  			};
        $colsValue = implode(", ", $cols);
      }
      else
        $colsValue = strval($cols);
      $onString = $this->getCondString($on, $all, true);
			$limitString = ($limit===NULL || $limit==="") ? "" : " LIMIT " . intval($this->escape($limit));
			if(!empty($order))
        $order = " ORDER BY `" . $order . "` " . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue
        FROM `$table`
        $jt JOIN `$join` ON $onString$limitString$order
      ", $flags, $name);
    }

    public function selectJoinWhere($table, $join, $on, $cond, $order = "", $cols = "*", $limit = NULL, $flags = 133, $name = "selectJoinWhere") {
      $all = !boolval($flags & self::COND_OR);
      switch(true) {
        case boolval($flags & self::JOIN_LEFT): $jt = "LEFT OUTER"; break;
        case boolval($flags & self::JOIN_RIGHT): $jt = "RIGHT OUTER"; break;
        case boolval($flags & self::JOIN_FULL): $jt = "FULL OUTER"; break;
        default: $jt = "INNER";
      };
      if(is_array($cols)) {
        foreach($cols as $k => $v) {
  				if(gettype($k)!="integer")
  					$cols[$k] = "`" . $v . "`" . " AS " . "`" . $k . "`";
          else
            $cols[$k] = "`" . $v . "`";
  			};
        $colsValue = implode(", ", $cols);
      }
      else
        $colsValue = strval($cols);
      $onString = $this->getCondString($on, $all, true);
      $condString = $this->getCondString($cond, $all);
			$limitString = ($limit===NULL || $limit==="") ? "" : " LIMIT " . intval($this->escape($limit));
			if(!empty($order))
        $order = " ORDER BY `" . $order . "` " . (boolval($flags & self::ORDER_DESC) ? "DESC" : "ASC");
      return $this->query("
        SELECT $colsValue
        FROM `$table`
        $jt JOIN `$join` ON $onString
        WHERE $condString$limitString$order
      ", $flags, $name);
    }

    public function exists($table, $cond, $flags = 129, $name = "exists") {
      $all = !boolval($flags & self::COND_OR);
      $this->selectWhere($table, $cond, "", "*", NULL, $flags, $name);
      $noFetch = !$this->fetch();
      return !$noFetch;
    }

    public function truncate($table, $flags = 0) {
      return $this->query("
        TRUNCATE `$table`
      ", $flags, "truncate");
    }

    public function insert($table, $values, $flags = 0) {
      $cols = [""];
      $useCols = true;
      foreach($values as $k => $v) {
        if(gettype($k)=="integer")
          $useCols = false;
      };
      if($useCols)
        $cols = array_values(array_flip($values));
      $values = array_values($values);
      if($cols==[""] || $cols=="")
        $colString = "";
      else {
        $colString = " (";
        foreach($cols as $key => $value) {
          if($key!=0) $colString.= ", ";
          $colString.= "`" . $this->escape($value) . "`";
        };
        $colString.= ")";
      };
      $valueString = "";
      foreach($values as $key => $value) {
        if($key!=array_keys($values, array_values($values)[0])[0]) $valueString.= ", ";
        $valueString.= $value===NULL ? "NULL" : ("'" . $this->escape($value) . "'");
      };
      $r = $this->query("
        INSERT INTO $table$colString VALUES ($valueString)
      ", $flags, "insert");
      return (boolval($flags & self::INSERT_RETURN_ID) ? $this->connect->lastInsertId() : $r);
    }

    public function delete($table, $cond, $flags = 128) {
      $all = !boolval($flags & self::COND_OR);
      $condString = $this->getCondString($cond, $all);
      return $this->query("
        DELETE FROM `$table` WHERE $condString
      ", $flags, "delete");
    }

    public function update($table, $cond, $values, $flags = 128) {
      $condString = $this->getCondString($cond, $flags & 128);
      $string = "";
      foreach($values as $key => $value) {
        if($string!="")
          $string.= ", ";
        $string.= "`" . $this->escape($key) . "`=" . ($value===NULL ? "NULL" : ("'" . $this->escape($value) . "'"));
      };
      return $this->query("
        UPDATE `$table` SET $string WHERE $condString
      ", $flags, "update");
    }

    public function add($table, $name, $type, $length, $null, $where, $key, $data = "") {
      if(!empty($data))
        $data = " " . $data;
      $type = strtoupper($type);
      $where = strtoupper($where);
      return $this->query("
        ALTER TABLE `$table` ADD '$name' $type($length) " . ($null ? "NULL" : "NOT NULL") . "$data $where '$key'
      ", $flags, "drop");
    }

    public function drop($table, $name) {
      return $this->query("
        ALTER TABLE `$table` DROP '$name'
      ", $flags, "drop");
    }

    public function change($table, $name, $newname, $type, $length, $null, $data = "", $flags = 0) {
      if(!empty($data))
        $data = " " . $data;
      $type = strtoupper($type);
      $where = strtoupper($where);
      return $this->query("
        ALTER TABLE `$table` CHANGE '$name' $newname $type($length) " . ($null ? "NULL" : "NOT NULL") . $data
      , $flags, "change");
    }

    public function selectAll($table, $flags = 129) {
      $r = $this->result;
      $this->select($table, "", "*", NULL, $flags);
      $f = $this->fetch($flags | self::FETCH_ALL);
      $this->result = $r;
      return $f;
    }

    public function fetchWhere($table, $cond, $flags = 129) {
      $r = $this->result;
      $this->selectWhere($table, $cond, "", "*", NULL, $flags);
      $f = $this->fetch();
      $this->result = $r;
      return $f;
    }

    public function read($table, $cond = [], $flags = 4225) {
      $r = $this->result;
      if(gettype($cond)=="integer" && $flags==4225) {
        $flags = $cond;
        $cond = [];
      };
      if($cond===[])
        $this->select($table);
      else
        $this->selectWhere($table, $cond, "", "*", NULL, $flags);
      return $this->fetch($flags);
    }

    public function createTable($table, $params, $primary = "", $flags = 0) {
      $parameters = $this->getParameters($params);
      $valueString = implode(",\n", $parameters);
      return $this->query("
        CREATE TABLE `$table` ($valueString)
        " . empty($primary) ? "" : ", PRIMARY KEY ($primary)" . "
      ", $flags);
    }

    public function renameTable($table, $newname, $flags = 0) {
      return $this->query("
        ALTER TABLE `$table` RENAME TO $newname
      ", $flags, "renameTable");
    }

    public function deleteTable($table, $flags = 0) {
      return $this->query("
        DROP TABLE `$table`
      ", $flags, "deleteTable");
    }

    private function getParameters($params) {
      foreach($names as $k => $v) {
        $t = $v['type'];
        $l = $v['length'];
        $n = $v['NULL'] ? "NULL" : "NOT NULL";
        $o = $v['other'];
        if(empty($l))
          $r[] = "$v $t $n $o";
        else
          $r[] = "$v $t($v) $n $o";
      };
      return $r;
    }

    public function q($q, ...$a) {
      if($a==[])
        return $this->query($q);
      else
        return $this->queryf($q, $a);
    }

    public function qs(...$qs) {
      $r = [];
      foreach($qs as $k => $v) {
        $r[] = $this->query($v);
      };
      if(count($r)==1)
        $r = $r[0];
      return $r;
    }

    public function p($q) {
      return new SmysqlQuery($this, $q);
    }

    public function get($table, $options = [], $flags = 4096) {
      if(gettype($options)=="integer" && $flags==4096) {
        $flags = $options;
        $options = [];
      };
      if(empty($options['fetch']))
        $fetch = boolval($flags & self::FETCH_SMART) ? self::FETCH_SMART : (boolval($flags & self::FETCH_ALL) ? self::FETCH_ALL : (boolval($flags & self::FETCH_ARRAY) ? self::FETCH_ARRAY : (boolval($flags & self::FETCH_OBJECT) ? self::FETCH_OBJECT : self::FETCH_SMART)));
      else
        $fetch = $options['fetch'];
      if(($fetch==self::FETCH_ALL || $fetch==self::FETCH_SMART) && boolval($flags & self::FETCH_ARRAY))
        $fetch = $fetch | self::FETCH_ARRAY;
      if(empty($options['cond_type']))
        $condtype = boolval($flags & self::COND_OR) ? self::COND_OR : self::COND_AND;
      else
        $condtype = $options['cond_type'];
      if(empty($options['order_type']))
        $ordertype = boolval($flags & self::ORDER_DESC) ? self::ORDER_DESC : self::ORDER_ASC;
      else
        $ordertype = $options['order_type'];
      if(empty($options['join_type']))
        $jointype = boolval($flags & self::JOIN_FULL) ? self::JOIN_FULL : (boolval($flags & self::JOIN_RIGHT) ? self::JOIN_RIGHT : (boolval($flags & self::JOIN_LEFT) ? self::JOIN_LEFT : self::JOIN_INNER));
      else
        $jointype = $options['join_type'];
      if($flags & self::FETCH_OBJECT)
        $flags-= self::FETCH_OBJECT;
      if($flags & self::FETCH_ARRAY)
        $flags-= self::FETCH_ARRAY;
      if($flags & self::FETCH_ALL)
        $flags-= self::FETCH_ALL;
      if($flags & self::FETCH_SMART)
        $flags-= self::FETCH_SMART;
      if($flags & self::COND_AND)
        $flags-= self::COND_AND;
      if($flags & self::COND_OR)
        $flags-= self::COND_OR;
      if($flags & self::ORDER_ASC)
        $flags-= self::ORDER_ASC;
      if($flags & self::ORDER_DESC)
        $flags-= self::ORDER_DESC;
      if($flags & self::JOIN_INNER)
        $flags-= self::JOIN_INNER;
      if($flags & self::JOIN_LEFT)
        $flags-= self::JOIN_LEFT;
      if($flags & self::JOIN_RIGHT)
        $flags-= self::JOIN_RIGHT;
      if($flags & self::JOIN_FULL)
        $flags-= self::JOIN_FULL;
      $cond = empty($options['cond']) ? false : $options['cond'];
      $join = empty($options['join']) ? false : $options['join'];
      $on = empty($options['on']) ? false : $options['on'];
      $cols = empty($options['cols']) ? "*" : $options['cols'];
      $order = empty($options['order']) ? "" : $options['order'];
      $limit = empty($options['limit']) ? NULL : $options['limit'];
      if(!$cond && (!$join || !$on))
        $result = $this->select($table, $order, $cols, $limit, $flags | $ordertype, "get");
      if($cond && (!$join || !$on))
        $result = $this->selectWhere($table, $cond, $order, $cols, $limit, $flags | $condtype | $ordertype, "get");
      if(!$cond && $join)
        $result = $this->selectJoin($table, $join, $on, $order, $cols, $limit, $flags | $jointype | $ordertype, "get");
      if($cond && $join)
        $result = $this->selectJoinWhere($table, $join, $on, $cond, $order, $cols, $limit, $flags | $jointype | $condtype | $ordertype, "get");
      return $this->fetch($flags | $fetch);
    }

    private function getCondString($a, $and, $on = false) {
      if(!is_array($a))
        return $a;
      $r = "";
      foreach($a as $k => $v) {
        if(is_array($v)) {
          foreach($v as $k2 => $v2) {
            $col = false;
            if(!is_numeric($k) && str_split($v2)[0]=="`" && end(str_split($v2))=="`") {
              $va = str_split($v);
              unset($va[0]);
              unset($va[count($va-1)]);
              $col = true;
            };
            if(!is_numeric($v2))
              $v3 = $this->escape($v2);
						if(is_numeric($k))
							$r.= $v;
						else {
							$r.= "`" . $this->escape($k) . "`";
							$r.= " = ";
							if(is_numeric($v3))
								$v3 = intval($v3);
							if($v3===NULL)
								$r.= "IS NULL";
							elseif(is_numeric($v3))
								$r.= $v;
							else
								$r.= ($on || $col) ? "`$v3`" : "'$v3'";
						};
            $r.= $and ? " AND " : " OR ";
          };
          return rtrim($r, $and ? " AND " : " OR ");
        }
        else {
          $col = false;
          if(!is_numeric($k) && str_split($v)[0]=="`" && end(str_split($v))=="`") {
            $va = str_split($v);
            unset($va[0]);
            unset($va[count($va)]);
            $col = true;
          };
          if(!is_numeric($v))
            $v = $this->escape($v);
					if(is_numeric($k))
						$r.= $v;
					else {
						$r.= "`" . $this->escape($k) . "`";
						$r.= " = ";
						if(is_numeric($v))
							$v = intval($v);
						if(is_numeric($v))
							$r.= $v;
						else
							$r.= ($on || $col) ? "`$v`" : "'$v'";
					};
          $r.= $and ? " AND " : " OR ";
        };
      };
      return rtrim($r, $and ? " AND " : " OR ");
    }
    public function __debugInfo() {
      return ['host' => $this->host, 'user' => $this->user, 'password' => preg_replace("/./", "*", $this->password), 'database' => $this->db, 'lastError' => $this->connect->errorInfo()];
    }
    public function __sleep() {
      if($this->result instanceof PDOStatement)
        $this->result->__destruct();
      $this->connect = NULL;
    }

    public function __wakeup() {
      $this->__construct($this->host, $this->user, $this->password, $this->db);
    }

    public function __destruct() {
      if($this->result instanceof PDOStatement)
        $this->result->__destruct();
      $this->connect = NULL;
    }
  };
  class SmysqlResult {
    private $pdos;
    public $columnCount = 0;
    public function __construct($pdos) {
      $this->pdos = $pdos;
      $this->columnCount = $this->pdos->columnCount();
    }
    public function getColumns() {
      $columns = [];
      $i = 0;
      while($i++<$this->columnCount) {
        $meta = $this->pdos->getColumnMeta(bcsub($i, 1));
        $columns[bcsub($i, 1)] = new StdClass();
        $columns[bcsub($i, 1)]->name = $meta['name'];
        $columns[bcsub($i, 1)]->table = $meta['table'];
        if(isset($meta['mysql:decl_type']))
          $columns[bcsub($i, 1)]->type = $meta['mysql:decl_type'];
        else {
          switch($meta['pdo_type']) {
            case PDO::PARAM_BOOL: $columns[bcsub($i, 1)]->type = "BOOL"; break;
            case PDO::PARAM_INT: $columns[bcsub($i, 1)]->type = "INT"; break;
            case PDO::PARAM_STR: $columns[bcsub($i, 1)]->type = $meta['native_type']=="VAR_STRING" ? "VARCHAR" : "CHAR"; break;
            default: $columns[bcsub($i, 1)]->type = NULL;
          };
          if($meta['native_type']=="TINY")
            $columns[bcsub($i, 1)]->type = "TINYINT";
          if($meta['native_type']=="DOUBLE")
            $columns[bcsub($i, 1)]->type = "DOUBLE";
          if($meta['native_type']=="DATE")
            $columns[bcsub($i, 1)]->type = "DATE";
        };
        $columns[bcsub($i, 1)]->length = $meta['len'];
        $columns[bcsub($i, 1)]->flags = $meta['flags'];
        if(isset(array_flip($meta['flags'])['blob'])) {
          $columns[bcsub($i, 1)]->type = "BLOB";
          unset($columns[bcsub($i, 1)]->flags[array_flip($meta['flags'])['blob']]);
        };
      };
      return $columns;
    }
    public function fetch($flags = 512) {
      $id = $this->pdos;
      if(boolval($flags & SMQ::FETCH_ALL) || boolval($flags & SMQ::FETCH_SMART)) {
        $return = [];
        while($row = (boolval($flags & SMQ::FETCH_ARRAY) ? $id->fetch() : $id->fetchObject())) {
          $return[] = $row;
        };
        if(boolval($flags & SMQ::FETCH_SMART) && count($return)<2)
          return count($return)<1 ? false : $return[0];
      }
      elseif(boolval($flags & SMQ::FETCH_ARRAY))
        $return = $id->fetch();
      else
        $return = $id->fetchObject();
      return $return;
    }
    public function dump($limit = 256, $columntypes = true, $print = true) {
      $limit = $limit ? intval($limit) : false;
      $f = $this->fetch(SQ_FETCH_ALL);
      $h = "";
      $h.= "<table style=\"border-collapse: collapse; margin: 10px;\">";
      if($this->columnCount>0) {
        $columns = $this->getColumns();
        $tables = [];
        foreach($columns as $k => $v) {
          if(!in_array($v->table, $tables))
            $tables[] = $v->table;
        };
        $h.= "<caption>";
        $h.= implode(", ", $tables);
        $h.= "</caption><tr>";
        $i = 0;
        while($i++<$this->columnCount) {
          $h.= "<th style=\"border: 1px solid black; background-color: #ccf; padding: 5px;\">";
          if(count($tables) > 1)
            $h.= $columns[bcsub($i, 1)]->table . ".";
          $h.= $columns[bcsub($i, 1)]->name;
          $h.= "<small style=\"opacity: .75\">&nbsp;&ndash;&nbsp;<em>";
          $h.= $columns[bcsub($i, 1)]->type;
          $h.= "</em></small>";
          $h.= "</th>";
        };
        $h.= "</tr>";
      };
      if(count($f)==0 && $this->columnCount>0)
        $h.= "<tr><td colspan=\"" . $this->columnCount . "\" style=\"border: 1px solid black; padding: 5px;\"><em>No rows returned</em></td></tr>";
      elseif($this->columnCount>0) {
        foreach($f as $k => $v) {
          $h.= "<tr>";
          $i = 0;
          foreach($v as $key => $val) {
            $h.= "<td style=\"border: 1px solid black; padding: 5px; text-align: center;\">" . ($val===NULL ? "<em>NULL</em>" : ($limit && (strlen($val)>$limit) ? "<em>Value longer than " . $limit . " bytes</em>" : ("<pre>" . ($columns[$i]->type=="BLOB" ? ("<strong>HEX: </strong><em>" . bin2hex($val) . "</em>") : str_replace(["\n", "\r"], "", nl2br(htmlspecialchars($val, ENT_QUOTES, "UTF-8")))) . "</pre>"))) . "</td>";
            $i++;
          };
          $h.= "<tr>";
        };
      }
      else
        $h = "<em>Empty response</em>";
      if(str_replace("<table", "", $h)!=$h)
        $h.= "</table>";
      if($print)
        print $h;
      return $h;
    }
    public function __toString() {
      return $this->dump(256, true, false);
    }
    public function __destruct() {
      $this->pdos = NULL;
    }
  };

  class SmysqlQuery {
    private $c;
    private $q = "";
    private $p = [];
    public function __construct($c, $q) {
      $this->c = $c;
      $this->q = $q;
    }
    public function pair($k, $v) {
      $this->p[$k] = $v;
      return $this;
    }
    public function set($p) {
      foreach($p as $k => $v) {
        $this->pair($k, $v);
      };
      return $this;
    }
    public function __set($k, $v) {
      $this->pair($k, $v);
    }
    public function __get($k) {
      return $this->p[$k];
    }
    public function __unset($k) {
      unset($this->p[$k]);
    }
    public function __isset($k) {
      isset($this->p[$k]);
    }
    public function s(...$qs) {
      $this->set($qs);
      return $this;
    }
    public function run(...$qs) {
      $this->set($qs);
      return $this->c->queryf($this->q, $this->p);
    }
  };


  function Smysql($host, $user, $password, $db, &$object = "return") {
    if($object=="return")
      return new Smysql($host, $user, $password, $db);
    else
      $object = new Smysql($host, $user, $password, $db);
  };
?>
