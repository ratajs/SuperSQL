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
	define("SQ_CASE_INSENSITIVE", 512);
	define("SQ_NO_ERROR", 1024);
	define("SQ_DEBUG", 2048);
	class SSQLException extends Exception {
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
		public function __toString(): string {
			ob_start();
			trigger_error("<strong>SuperSQL error:</strong> " . $this->message . " in <strong>" . $this->file . "</strong> on line <strong>" . $this->line . "</strong>");
			ob_clean();
			if(ini_get("display_errors"))
				die((empty(ini_get("error_prepend_string")) ? "" : ini_get("error_prepend_string")) . "<br /><strong>SuperSQL error:</strong> " . $this->message . " in <strong>" . $this->file . "</strong> on line <strong>" . $this->line . "</strong><br />" . (empty(ini_get("error_append_string")) ? "" : ini_get("error_append_string")));
			else
				exit;
			return "<strong>SuperSQL error</strong> " . $this->message;
		}
	};

	abstract class SQ {
		const ORDER_ASC = 1;
		const ORDER_DESC = 2;
		const JOIN_INNER = 4;
		const JOIN_LEFT = 8;
		const JOIN_RIGHT = 16;
		const JOIN_FULL = 32;
		const INSERT_RETURN_ID = 64;
		const COND_AND = 128;
		const COND_OR = 256;
		const CASE_INSENSITIVE = 512;
		const NO_ERROR = 1024;
		const DEBUG = 2048;

		static function open(string $host = "", string $user = "", string $password = "", string $db = "", &$object = "return") {
			if($object=="return")
				return new SSQL($host, $user, $password, $db);
			else
				$object = new SSQL($host, $user, $password, $db);
		}
	};
	class SSQL extends SQ {
		protected $connect;
		protected $result;
		protected $host;
		protected $user;
		protected $password;
		protected $db;
		public $SQLite = false;
		protected $fncs = [];
		public $debug = false;
		public function __construct(string $host = "", string $user = "", string $password = "", string $database = "") {
			if(empty($host)) {
				if(!empty($this->host)) {
					$host = $this->host;
					$user = $this->user;
					$password = $this->password;
					$database = $this->db;
				};
				if(empty($host)) {
					throw new SSQLException("(__construct): Host parameter is required");
				};
			};
			$ex = explode(".", $host);
			if((count($ex) > 1 && in_array(strtolower(end($ex)), ["sqlite3", "sqlite", "db"])) || $this->SQLite) {
				$this->host = $host;
				$this->SQLite = true;
				if(!file_exists($host) && !@touch($host))
					throw new SSQLException("(__construct): Cannot create SQLite database");
				try {
					$this->connect = @new PDO("sqlite:" . $host);
				} catch(PDOException $e) {
					throw new SSQLException("(__construct): Cannot connect to SQLite: " . $e->getMessage());
				};
				return true;
			};
			$this->host = $host;
			$this->user = $user;
			$this->password = $password;
			$this->db = $database;
			if(str_replace("create[", "", $database)!=$database && ($split = str_split($database)) && end($split)=="]") {
				$new = str_replace("]", "", str_replace("create[", "", $database));
				$this->connect = @new PDO("mysql:host=" . $host, $user, $password);
				if(!$this->query("
					CREATE DATABASE $new
					", "__construct"))
					throw new SSQLException("(__construct): Cannot create database " . $new);
				$this->connect = NULL;
			};
			try {
				$this->connect = @new PDO("mysql:" . (empty($database) ? "" : "dbname=" . $database . ";") . "host=" . $host . ";charset=utf8", $user, $password);
			} catch(PDOException $e) {
				throw new SSQLException("(__construct): Cannot connect to MySQL: " . $e->getMessage());
			};
			if($this->connect->errorCode() && $this->connect) {
				throw new SSQLException("(__construct): Cannot select MySQL database:" . $this->connect->errorInfo()[2]);
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
			$this->connect = NULL;
			$this->__construct();
		}

		public function beginTransaction() {
			return $this->connect->beginTransaction();
		}

		public function endTransaction() {
			return $this->connect->commit();
		}

		public function rollBackTransaction() {
			return $this->connect->rollBack();
		}

		public function query(string $query, int $flags = 0, string $fnc = "query") {
			$query = trim($query);
			if($flags & self::DEBUG || $this->debug)
				print "<pre><strong>" . ucwords($fnc) . ":</strong> " . htmlentities($query) . "</pre>";
			if(empty($this->db) && !$this->SQLite && !in_array($fnc, ["__construct", "changeDB", "dbList"]))
				throw new SSQLException("(" . $fnc . "): No database selected");
			try {
				$qr = $this->connect->query($query);
			} catch(PDOException $e) {
				if($flags & self::NO_ERROR)
					return false;
				else
					throw new SSQLException("(" . $fnc . "): Database error: <em>{$e->getMessage()}</em> <strong>SQL query:</strong> <code>" . (empty($query) ? "<em>Missing</em>" : $query) . "</code>");
			};
			if(!$qr && !($flags & self::NO_ERROR))
				throw new SSQLException("(" . $fnc . "): Database error: <em>{$this->connect->errorInfo()[2]}</em> <strong>SQL query:</strong> <code>" . (empty($query) ? "<em>Missing</em>" : $query) . "</code>");
			elseif(!$qr)
				return false;
			$this->result = new SSQLResult($qr);
			return $this->result;
		}

		public function queryf(string $q, array $a, int $flags = 0, string $fnc = "queryf") {
			for($i = (count($a) - 1); $i>=0; $i--) {
				$k = array_keys($a)[$i];
				$v = $a[$k];
				$q = str_replace("%" . $k, $this->escape($v), $q);
			};
			return $this->query($q, $flags, $fnc);
		}

		public function __set(string $name, string $query) {
			$this->fncs[$name] = $query;
		}

		public function __get(string $name) {
			return $this->fncs[$name];
		}

		public function __call(string $name, array $params) {
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

		public function setFnc(string $name, string $query) {
			$this->fncs[$name] = $query;
		}

		public function execFnc(string $name, array $params = [], int $flags = 0) {
			if(isset($this->fncs[$name]))
				$this->queryf($this->fncs[$name], $params, $flags, $name);
			else
				throw new SSQLException("(" . $name . "): This function is not defined");
		}

		public function dbList(int $flags = 0) {
			if($this->SQLite)
				throw new SSQLException("(dbList): Not supported in SQLite");
			$this->query("SHOW DATABASES", $flags, "dbList");
			$r = [];
			foreach($this->result as $k => $v) {
				$r[] = $v->Database;
			};
			return $r;
		}

		public function tableList(int $flags = 0) {
			if(empty($this->db) && !$this->SQLite)
				throw new SSQLException("(tableList): No database selected");
			$this->query(($this->SQLite ? "SELECT `name` FROM `sqlite_master` WHERE type = 'table' ORDER BY `name` ASC" : ("SHOW TABLES FROM `{$this->escape($this->db)}`")), $flags, "tableList");
			$r = [];
			foreach($this->result as $k => $v) {
				$r[] = $v->name;
			};
			return $r;
		}

		public function charset(string $charset, int $flags = 0) {
			return $this->query("SET NAMES {$this->escape($charset)}", $flags, "charset");
		}

		public function deleteDB(string $db, int $flags) {
			if($this->SQLite)
				throw new SSQLException("Not supported in SQLite");
			if($this->query("DROP DATABASE `{$this->escape($db)}`", $flags, "deleteDB")) {
				return true;
			}
			else {
				throw new SSQLException("(deleteDB): Cannot delete database " . $db);
				return false;
			};
		}

		public function changeDB(string $newDB) {
			$this->connect = NULL;
			if($this->SQLite)
				$this->host = $newDB;
			else
				$this->db = $newDB;
			$this->reload();
		}

		public function select(string $table, string $order = "", $cols = "*", $limit = NULL, int $flags = 129, string $name = "select") {
			$callbacks = [];
			if(is_array($cols)) {
				$colsValue = "";
				foreach($cols as $k => $v) {
					if($v instanceof Closure) {
						$callbacks[$k] = $v;
						continue;
					};
					if(!empty($colsValue))
						$colsValue.= ", ";
					if(is_int($k))
						$colsValue.= "`{$this->escape($v)}`";
					else
						$colsValue.= "`{$this->escape($v)}` AS `{$this->escape($k)}`";
				};
			}
			else
				$colsValue = strval($cols);
			$limitString = ($limit===NULL || $limit==="") ? "" : " LIMIT " . intval($this->escape($limit));
			if(!empty($order))
				$order = " ORDER BY `{$this->escape($order)}`" . (boolval($flags & self::ORDER_DESC) ? " DESC" : " ASC");
			$r =	$this->query("
				SELECT {$colsValue} FROM `{$this->escape($table)}`{$order}{$limitString}
			", $flags, $name);
			$r->callbacks = $callbacks;
			return $r;
		}

		public function selectWhere(string $table, $cond, string $order = "", $cols = "*", $limit = NULL, int $flags = 129, string $name = "selectWhere") {
			$callbacks = [];
			$all = !boolval($flags & self::COND_OR);
			if(is_array($cols)) {
				$colsValue = "";
				foreach($cols as $k => $v) {
					if($v instanceof Closure) {
						$callbacks[$k] = $v;
						continue;
					};
					if(!empty($colsValue))
						$colsValue.= ", ";
					if(is_int($k))
						$colsValue.= "`{$this->escape($v)}`";
					else
						$colsValue.= "`{$this->escape($v)}` AS `{$this->escape($k)}`";
				};
			}
			else
				$colsValue = strval($cols);
			$condString = $this->getCondString($cond, $all);
			$limitString = ($limit===NULL || $limit==="") ? "" : " LIMIT " . intval($this->escape($limit));
			if(!empty($order))
				$order = " ORDER BY `{$this->escape($order)}`" . (boolval($flags & self::ORDER_DESC) ? " DESC" : " ASC");
			$r = $this->query("
				SELECT {$colsValue} FROM `{$this->escape($table)}` WHERE {$condString}{$order}{$limitString}
				", $flags, $name);
			$r->callbacks = $callbacks;
			return $r;
		}

		public function selectJoin(string $table, string $join, $on, string $order = "", $cols = "*", $limit = NULL, int $flags = 133, string $name = "selectJoin") {
			$callbacks = [];
			$all = !boolval($flags & self::COND_OR);
			switch(true) {
				case boolval($flags & self::JOIN_LEFT): $jt = "LEFT OUTER"; break;
				case boolval($flags & self::JOIN_RIGHT): $jt = "RIGHT OUTER"; break;
				case boolval($flags & self::JOIN_FULL): $jt = "FULL OUTER"; break;
				default: $jt = "INNER";
			};
			if(is_array($cols)) {
				$colsValue = "";
				foreach($cols as $k => $v) {
					if($v instanceof Closure) {
						$callbacks[$k] = $v;
						continue;
					};
					if(!empty($colsValue))
						$colsValue.= ", ";
					if(is_int($k))
						$colsValue.= "`{$this->escape($v)}`";
					else
						$colsValue.= "`{$this->escape($v)}` AS `{$this->escape($k)}`";
				};
			}
			else
				$colsValue = strval($cols);
			$onString = $this->getCondString($on, $all, true);
			$limitString = ($limit===NULL || $limit==="") ? "" : " LIMIT " . intval($this->escape($limit));
			if(!empty($order))
				$order = " ORDER BY `{$this->escape($order)}` " . (boolval($flags & self::ORDER_DESC) ? " DESC" : " ASC");
			$r = $this->query("
				SELECT {$colsValue}
				FROM `{$this->escape($table)}`
				{$jt} JOIN `{$this->escape($join)}` ON {$onString}{$order}{$limitString}
				", $flags, $name);
			$r->callbacks = $callbacks;
			return $r;
		}

		public function selectJoinWhere(string $table, string $join, $on, $cond, string $order = "", $cols = "*", $limit = NULL, int $flags = 133, string $name = "selectJoinWhere") {
			$callbacks = [];
			$all = !boolval($flags & self::COND_OR);
			switch(true) {
				case boolval($flags & self::JOIN_LEFT): $jt = "LEFT OUTER"; break;
				case boolval($flags & self::JOIN_RIGHT): $jt = "RIGHT OUTER"; break;
				case boolval($flags & self::JOIN_FULL): $jt = "FULL OUTER"; break;
				default: $jt = "INNER";
			};
			if(is_array($cols)) {
				$colsValue = "";
				foreach($cols as $k => $v) {
					if($v instanceof Closure) {
						$callbacks[$k] = $v;
						continue;
					};
					if(!empty($colsValue))
						$colsValue.= ", ";
					if(is_int($k))
						$colsValue.= "{$this->escape($v)}";
					else
						$colsValue.= "`{$this->escape($v)}` AS `{$this->escape($k)}`";
				};
			}
			else
				$colsValue = strval($cols);
			$onString = $this->getCondString($on, $all, true);
			$condString = $this->getCondString($cond, $all);
			$limitString = ($limit===NULL || $limit==="") ? "" : " LIMIT " . intval($this->escape($limit));
			if(!empty($order))
				$order = " ORDER BY `{$this->escape($order)}` " . (boolval($flags & self::ORDER_DESC) ? " DESC" : " ASC");
			$r = $this->query("
				SELECT {$colsValue}
				FROM `{$this->escape($table)}`
				{$jt} JOIN `{$this->escape($join)}` ON {$onString}
				WHERE {$condString}{$order}{$limitString}
				", $flags, $name);
			$r->callbacks = $callbacks;
			return $r;
		}

		public function exists(string $table, $cond, int $flags = 129, string $name = "exists") {
			$this->selectWhere($table, $cond, "", "*", NULL, $flags, $name);
			return isset($this->result[0]);
		}

		public function truncate(string $table, int $flags = 0, string $name = "truncate") {
			if($this->SQLite)
				$query = "DELETE FROM `{$this->escape($table)}`";
			else
				$query = "TRUNCATE `{$this->escape($table)}`";
			return $this->query($query, $flags, $name);
		}

		public function insert(string $table, array $values, int $flags = 0, string $name = "insert") {
			$cols = [""];
			$useCols = true;
			foreach($values as $k => $v) {
				if(gettype($k)=="integer")
					$useCols = false;
			};
			if($useCols)
				$cols = array_keys($values);
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
				if($value===NULL) {
					$valueString.= "NULL";
					continue;
				};
				$value = strval($value);
				if(preg_match("/^[\x{0020}-\x{007E}\x{00A0}-ſ]*$/", $value) && !preg_match("/\[x{0027}\x{005C}]/", $value))
					$valueString.= "'" . $this->escape($value) . "'";
				elseif($this->SQLite)
					$valueString.= "x'" . bin2hex($value) . "'";
				else
					$valueString.= "0x" . bin2hex($value);
			};
			$r = $this->query("
				INSERT INTO `{$this->escape($table)}`{$colString} VALUES ({$valueString})
			", $flags, $name);
			return (boolval($flags & self::INSERT_RETURN_ID) ? $this->connect->lastInsertId() : $r);
		}

		public function delete(string $table, $cond, int $flags = 128, string $name = "delete") {
			$all = !boolval($flags & self::COND_OR);
			$condString = $this->getCondString($cond, $all);
			return $this->query("
				DELETE FROM `{$this->escape($table)}` WHERE {$condString}
			", $flags, $name);
		}

		public function update(string $table, $cond, array $values, int $flags = 128, string $name = "update") {
			$all = !boolval($flags & self::COND_OR);
			$condString = $this->getCondString($cond, $all);
			$string = "";
			foreach($values as $key => $value) {
				if($string!="")
					$string.= ", ";
				$string.= "`" . $this->escape($key) . "` = ";
				if($value===NULL)
					$string.= "NULL";
				elseif(is_int($value) || is_float($value))
					$string.= strval($value);
				elseif(is_bool($value))
					$string.= $value ? "1" : "0";
				elseif(preg_match("/^[\x{0020}-\x{007E}\x{00A0}-ſ]*$/", $value) && !preg_match("/[\x{0027}\x{005C}]/", $value))
					$string.= "'" . $this->escape($value) . "'";
				elseif($this->SQLite)
					$string.= "x'" . bin2hex($value) . "'";
				else
					$string.= "0x" . bin2hex($value);
			};
			return $this->query("
				UPDATE `{$this->escape($table)}` SET {$string} WHERE {$condString}
			", $flags, $name);
		}

		public function addColumn(string $table, string $name, array $params, int $flags = 0) {
			$valueString = $this->getParameters([$name => $params], "addColumn")[0];
			return $this->query("
				ALTER TABLE `{$this->escape($table)}`\nADD {$valueString}
			", $flags, "addColumn");
		}

		public function removeColumn(string $table, string $name, int $flags = 0) {
			return $this->query("
				ALTER TABLE `{$this->escape($table)}` DROP COLUMN `{$this->escape($name)}`
			", $flags, "removeColumn");
		}

		public function renameColumn(string $table, string $oldName, string $newName, int $flags = 0) {
			return $this->query("
				ALTER TABLE `{$this->escape($table)}` RENAME COLUMN `{$this->escape($oldName)}` TO `{$this->escape($newName)}`
			", $flags, "renameColumn");
		}

		public function read(string $table, $cond = [], int $flags = 129) {
			$r = $this->result;
			if(is_int($cond) && $flags==129) {
				$flags = $cond;
				$cond = [];
			};
			if($cond===[])
				$this->select($table, "", "*", NULL, $flags, "read");
			else
				$this->selectWhere($table, $cond, "", "*", NULL, $flags, "read");
			return $this->result;
		}

		public function createTable(string $table, array $columns, $primary = NULL, int $flags = 0) {
			$parameters = $this->getParameters($columns, "createTable", $primary);
			$valueString = implode(",\n", $parameters);
			$query = "CREATE TABLE `{$this->escape($table)}` ({$valueString})";
			return $this->query($query, $flags, "createTable");
		}

		public function renameTable(string $table, string $newname, int $flags = 0) {
			return $this->query("
				ALTER TABLE `{$this->escape($table)}` RENAME TO `{$this->escape($newname)}`
			", $flags, "renameTable");
		}

		public function deleteTable(string $table, int $flags = 0) {
			return $this->query("
				DROP TABLE `{$this->escape($table)}`
			", $flags, "deleteTable");
		}

		private function getParameters(array $columns, string $name, $primary = NULL) {
			foreach($columns as $k => $v) {
				$cname = $this->escape($k);
				$t = $this->escape($v['type']);
				$s = (isset($v['size']) && is_int($v['size'])) ? strval($v['size']) : NULL;
				$n = ($v['NULL'] ?? false) ? "NULL" : "NOT NULL";
				$d = ($v['default'] ?? NULL)!==NULL ? (" DEFAULT " . ((is_int($v['default']) || is_float($v['default']) || is_bool($v['default'])) ? ($v['default']===false ? 0 : strval($v['default'])) : "\"" . $this->escape(strval($v['default'])) . "\"")) : "";
				$o = empty($v['other']) ? "" : (" " . $v['other']);
				$a = (!$this->SQLite && $name=="addColumn" && !empty($v['after'])) ? (" AFTER `{$this->escape($v['after'])}`") : "";
				if(!$s)
					$r[] = "`{$cname}` {$t} {$n}{$d}{$o}{$a}";
				else
					$r[] = "`{$cname}` {$t}({$s}) {$n}{$d}{$o}{$a}";
			};
			if($name!="add" && $primary!==NULL) {
				if(is_array($primary)) {
					if(count($primary)==0)
						return $r;
					$keyString = "PRIMARY KEY (";
					$keyString.= $this->escape(array_shift($primary));
					foreach($primary as $k2 => $v2)
						$keyString.= ", `" . $this->escape($v2) . "`";
					$keyString.= ")";
				}
				else
					$r[] = "PRIMARY KEY (`{$this->escape($primary)}`)";
			};
			return $r;
		}

		public function q(string $q, ...$a) {
			if($a==[])
				return $this->query($q, 0, "q");
			else
				return $this->queryf($q, $a, 0, "q");
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

		public function p(string $q) {
			return new SSQLQuery($this, $q);
		}

		public function get(string $table, $options = [], int $flags = 0) {
			if(is_int($options) && $flags==0) {
				$flags = $options;
				$options = [];
			};
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
			$cond = $options['cond'] ?? false;
			$join = $options['join'] ?? false;
			$on = $options['on'] ?? false;
			$cols = $options['cols'] ?? "*";
			$order = $options['order'] ?? "";
			$limit = $options['limit'] ?? NULL;
			if(!$cond && (!$join || !$on))
				$result = $this->select($table, $order, $cols, $limit, $flags | $ordertype, "get");
			if($cond && (!$join || !$on))
				$result = $this->selectWhere($table, $cond, $order, $cols, $limit, $flags | $condtype | $ordertype, "get");
			if(!$cond && $join)
				$result = $this->selectJoin($table, $join, $on, $order, $cols, $limit, $flags | $jointype | $ordertype, "get");
			if($cond && $join)
				$result = $this->selectJoinWhere($table, $join, $on, $cond, $order, $cols, $limit, $flags | $jointype | $condtype | $ordertype, "get");
			return $this->result;
		}

		public function put(string $table, $data, $cond = NULL, int $flags = 0) {
			if(is_int($cond) && $flags==0) {
				$flags = $cond;
				$cond = NULL;
			};
			if(empty($cond)) {
				if($data==NULL)
					return $this->truncate($table, $flags, "put");
				else
					return $this->insert($table, $data, $flags, "put");
			};
			if($data===NULL)
				return $this->delete($table, $cond, $flags, "put");
			return $this->update($table, $cond, $data, $flags, "put");
		}

		public function cond($cond = "") {
			return new SSQLCond($this, $cond);
		}

		private function getCondString($a, bool $and, bool $on = false) {
			if(!is_array($a))
				return strval($a);
			$r = "";
			foreach($a as $k => $v) {
				if(is_array($v)) {
					$r.= "(";
					foreach($v as $k2 => $v2) {
						if(is_numeric($k))
							$r.= $v;
						else {
							$r.= "`{$this->escape($k)}`";
							$r.= $v===NULL ? " IS " : " = ";
							if(is_numeric($v2))
								$v2 = intval($v2);
							if(is_numeric($v2))
								$r.= $v2;
							elseif($on)
								$r.= "`{$this->escape($v2)}`";
							elseif($v2===NULL)
								$r.= "NULL";
							elseif(preg_match("/^[\x{0020}-\x{007E}\x{00A0}-ſ]*$/", $v2) && !preg_match("/[\x{0027}\x{005C}]/", $v2))
								$r.= "'" . $this->escape($v2) . "'";
							elseif($this->SQLite)
								$r.= "x'" . bin2hex($v2) . "'";
							else
								$r.= "0x" . bin2hex($v2);
						};
						$r.= " OR ";
					};
					$r = rtrim($r, " OR ");
					$r.= $and ? ") AND " : ") OR ";
				}
				else {
					if(is_numeric($k))
						$r.= $v;
					else {
						$r.= "`{$this->escape($k)}`";
						$r.= $v===NULL ? " IS " : " = ";
						if(is_numeric($v))
							$v = intval($v);
						if(is_numeric($v))
							$r.= $v;
						elseif($on)
							$r.= "`{$this->escape($v)}`";
						elseif($v===NULL)
							$r.= "NULL";
						elseif(preg_match("/^[\x{0020}-\x{007E}\x{00A0}-ſ]*$/", $v) && !preg_match("/[\x{0027}\x{005C}]/", $v))
							$r.= "'" . $this->escape($v) . "'";
						elseif($this->SQLite)
							$r.= "x'" . bin2hex($v) . "'";
						else
							$r.= "0x" . bin2hex($v);
					};
					$r.= $and ? " AND " : " OR ";
				};
			};
			return rtrim($r, $and ? " AND " : " OR ");
		}
		public function __debugInfo() {
			return ['db' => ($this->SQLite ? "SQLite" : "MySQL"), ($this->SQLite ? 'file' : 'host') => $this->host, 'user' => $this->user, 'password' => preg_replace("/./", "*", $this->password), 'database' => $this->db, 'lastError' => $this->connect->errorInfo()];
		}
		public function __sleep() {
			$this->connect = NULL;
		}

		public function __wakeup() {
			$this->__construct($this->host, $this->user, $this->password, $this->db);
		}
	};
	class SSQLResult implements ArrayAccess, Iterator, Countable {
		private $pdos;
		private $data = NULL;
		private $columnCount = 0;
		private $columns = NULL;
		private $callbacks = [];
		private $secondLevel = false;
		private $pos = 0;
		public function __construct(PDOStatement $pdos, $row = NULL) {
			$this->pdos = $pdos;
			$this->columnCount = $this->pdos->columnCount();
			if($row!==NULL) {
				$this->secondLevel = true;
				$this->data = [$row];
			};
		}
		public function getColumns() {
			if($this->columns!==NULL)
				return $this->columns;
			$columns = [];
			$i = 0;
			while($i++ < $this->columnCount) {
				$meta = $this->pdos->getColumnMeta($i - 1);
				if(!$meta) {
					$columns[$i - 1] = NULL;
					continue;
				};
				$columns[$i - 1] = new StdClass();
				$columns[$i - 1]->name = $meta['name'];
				if(isset($meta['table']))
					$columns[$i - 1]->table = $meta['table'];
				else
					$columns[$i - 1]->table = NULL;
				if(isset($meta['mysql:decl_type']))
					$columns[$i - 1]->type = strtoupper($meta['mysql:decl_type']);
				elseif(isset($meta['sqlite:decl_type'])) {
					$columns[$i - 1]->type = strtoupper(explode("(", $meta['sqlite:decl_type'])[0]);
					if(count(explode("(", $meta['sqlite:decl_type'])) > 1)
						$columns[$i - 1]->size = intval(rtrim(explode("(", $meta['sqlite:decl_type'])[1], ")"));
					else
						$columns[$i - 1]->size = NULL;
				}
				else {
					switch($meta['pdo_type']) {
						case PDO::PARAM_BOOL: $columns[$i - 1]->type = "BOOL"; break;
						case PDO::PARAM_INT: $columns[$i - 1]->type = "INT"; break;
						case PDO::PARAM_STR: $columns[$i - 1]->type = $meta['native_type']=="VAR_STRING" ? "VARCHAR" : ($meta['native_type']=="LONGLONG" ? "BIGINT" : "CHAR"); break;
						default: $columns[$i - 1]->type = NULL;
					};
					if($meta['native_type']=="TINY")
						$columns[$i - 1]->type = "TINYINT";
					if($meta['native_type']=="DOUBLE")
						$columns[$i - 1]->type = "DOUBLE";
					if($meta['native_type']=="DATE")
						$columns[$i - 1]->type = "DATE";
				};
				if($columns[$i - 1]->type=="INTEGER")
					$columns[$i - 1]->type = "INT";
				$columns[$i - 1]->flags = $meta['flags'];
				if(isset(array_flip($meta['flags'])['blob'])) {
					$columns[$i - 1]->type = "BLOB";
					unset($columns[$i - 1]->flags[array_flip($meta['flags'])['blob']]);
				};
			};
			$this->columns = $columns;
			return $columns;
		}
		private function fetch() {
			if($this->data!==NULL)
				return $this->data;
			$data = [];
			while(($row = $this->pdos->fetchObject())!==false) {
				if(!empty($row) && !empty($this->callbacks)) {
					foreach($this->callbacks as $k => $v) {
						if($k==="")
							$v($row);
						else
							$row->$k = $v($row);
					};
				};
				$data[] = $row;
			};
			$this->data = $data;
			return $data;
		}
		public function data() {
			return $this->fetch();
		}
		public function dump($limit = 256, bool $columntypes = true, bool $print = true) {
			$f = $this->fetch();
			$limit = $limit ? intval($limit) : false;
			$h = "";
			$h.= "<table style=\"margin: 10px; border-collapse: collapse;\">";
			if($this->columnCount + count($this->callbacks) > 0) {
				$columns = $this->getColumns();
				$tables = [];
				if($columns[0]) {
					foreach($columns as $k => $v) {
						if($v->table===NULL) {
							$tables = NULL;
							break;
						};
						if(!in_array($v->table, $tables))
							$tables[] = $v->table;
					};
					if($tables!==NULL && count($tables) > 0) {
						$h.= "<caption>";
						$h.= implode(", ", $tables);
						$h.= "</caption>";
					};
					$h.= "<thead><tr>";
				};
				$i = 0;
				while($i++ < $this->columnCount) {
					$h.= "<th style=\"border: 1px solid black; padding: 5px; background-color: #ccf;\">";
					if($tables!==NULL && count($tables) > 1)
						$h.= $columns[$i - 1]->table . ".";
					if($columns[$i - 1]) {
						$h.= $columns[$i - 1]->name;
						if($columntypes && $columns[$i - 1]->type!==NULL) {
							$h.= "<small style=\"opacity: .75\">&nbsp;&ndash;&nbsp;<em>";
							$h.= $columns[$i - 1]->type;
							$h.= "</em></small>";
						};
						$h.= "</th>";
					}
					elseif(count($f) > 0) {
						$h.= array_keys(get_object_vars($f[0]))[$i - 1];
						$h.= "</th>";
					}
					else
						$h.= "</th>";
				};
				foreach($this->callbacks as $k => $v) {
					$h.= "<th style=\"border: 1px solid black; padding: 5px; color: #404A77;\">";
					$h.= $k;
					$h.= "</th>";
				};
				$h.= "</tr></thead>";
			};
			if(count($f)==0 && $this->columnCount + count($this->callbacks) > 0)
				$h.= "<tr><td colspan=\"" . $this->columnCount . "\" style=\"border: 1px solid black; padding: 5px;\"><em>No rows returned</em></td></tr>";
			elseif($this->columnCount + count($this->callbacks) > 0) {
				foreach($f as $k => $v) {
					$h.= "<tr>";
					$i = 0;
					foreach($v as $key => $val) {
						$h.= "<td style=\"border: 1px solid black; padding: 5px; text-align: center;\">" . ($val===NULL ? "<em>NULL</em>" : ($limit && (strlen($val) > $limit) ? "<em>Value longer than " . $limit . " bytes</em>" : ("<pre>" . (((!empty($columns[$i]) && $columns[$i]->type=="BLOB") || !preg_match("/^[\x{0020}-\x{007E}\x{00A0}-ſΑ-ωА-я‐- ⁰-₿℀-⋿①-⓿]*$/", $val)) ? ("<strong>HEX: </strong><em>" . bin2hex($val) . "</em>") : str_replace(["\n", "\r"], "", nl2br(htmlspecialchars($val, ENT_QUOTES, "UTF-8")))) . "</pre>"))) . "</td>";
						$i++;
					};
					$h.= "</tr>";
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
		public function __toString(): string {
			return $this->dump(256, true, false);
		}
		public function __get($col) {
			return $this->fetch()[0]->$col;
		}
		public function __isset($col) {
			return isset($this->fetch()[0]->$col);
		}
		public function count() {
			return count($this->fetch());
		}
		public function offsetGet($row) {
			if($this->secondLevel) {
				$columnName = $this->getColumns()[0]->name;
				return $this->fetch()[0]->$columnName;
			}
			else
				return new SSQLResult($this->pdos, $this->fetch()[$row]);
		}
		public function offsetExists($index) {
			if(is_int($index))
				return $this->secondLevel ? ($this->columnCount > $index) : (count($this->fetch()) > $index);
			else
				return isset($this->fetch()[0]->$index);
		}
		public function offsetSet($index, $val) {}
		public function offsetUnset($index) {}
		public function current() {
			return $this->offsetGet($this->pos);
		}
		public function key() {
			return $this->pos;
		}
		public function next() {
			$this->pos++;
		}
		public function rewind() {
			$this->pos = 0;
		}
		public function valid() {
			return $this->offsetExists($this->pos);
		}
		public function __set(string $name, $val) {
			if($name=="callbacks")
				$this->callbacks = $val;
		}
		public function __debugInfo() {
			return $this->fetch();
		}
		public function __serialize() {
			return ['secondLevel' => $this->secondLevel, 'data' => $this->fetch()];
		}
		public function __unserialize($data) {
			$this->secondLevel = $data->secondLevel;
			$this->data = $data->data;
		}
	};

	class SSQLQuery {
		private $c;
		private $q = "";
		private $p = [];
		public function __construct(SSQL $c, string $q) {
			$this->c = $c;
			$this->q = $q;
		}
		public function pair(string $k, $v) {
			$this->p[$k] = $v;
			return $this;
		}
		public function set(array $p) {
			foreach($p as $k => $v) {
				$this->pair($k, $v);
			};
			return $this;
		}
		public function __set(string $k, $v) {
			$this->pair($k, $v);
		}
		public function __get(string $k) {
			return $this->p[$k];
		}
		public function __unset(string $k) {
			unset($this->p[$k]);
		}
		public function __isset(string $k) {
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

	class SSQLCond {
		private $c;
		private $cond;
		public function __construct(SSQL $c, $cond = "") {
			$this->c = $c;
			$this->cond = $cond;
		}
		public function quote($v, int $flags = 0) {
			if(is_int($v))
				return $v;
			elseif($v===NULL)
				return "NULL";
			elseif(preg_match("/^[\x{0020}-\x{007E}\x{00A0}-ſ]*$/", $v) && !preg_match("/[\x{0027}\x{005C}]/", $v))
				return (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "'" . $this->c->escape($v) . "'" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "");
			elseif($this->c->SQLite)
				return (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "x'" . bin2hex($v) . "'" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "");
			else
				return (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "0x" . bin2hex($v) . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "");
		}
		public function eq(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			$quotes = "'";
			if(is_int($v) || is_float($v))
				$v = [$v];
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ((($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " = " . $this->quote($v[0], $flags)) : ("(" . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " = " . $this->quote(array_shift($v), $flags) . ") OR (" . $this->c->cond()->eq($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " = " . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($v) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "")) . ($append ? ")" : "");
			return $this;
		}
		public function lt(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(is_int($v) || is_float($v))
				$v = [$v];
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ("`" . $this->c->escape($k) . "` < " . floatval($v[0])) : ("(`" . $this->c->escape($k) . "` < " . floatval(array_shift($v)) . ") OR (" . $this->c->cond()->lt($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ("`" . $this->c->escape($k) . "` < `" . $this->c->escape($v) . "`") . ($append ? ")" : "");
			return $this;
		}
		public function gt(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(is_int($v) || is_float($v))
				$v = [$v];
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ("`" . $this->c->escape($k) . "` > " . floatval($v[0])) : ("(`" . $this->c->escape($k) . "` > " . floatval(array_shift($v)) . ") OR (" . $this->c->cond()->gt($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ("`" . $this->c->escape($k) . "` > `" . $this->c->escape($v) . "`") . ($append ? ")" : "");
			return $this;
		}
		public function lte(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(is_int($v) || is_float($v))
				$v = [$v];
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ("`" . $this->c->escape($k) . "` <= " . floatval($v[0])) : ("(`" . $this->c->escape($k) . "` <= " . floatval(array_shift($v)) . ") OR (" . $this->c->cond()->lte($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ("`" . $this->c->escape($k) . "` <= `" . $this->c->escape($v) . "`") . ($append ? ")" : "");
			return $this;
		}
		public function gte(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(is_int($v) || is_float($v))
				$v = [$v];
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ("`" . $this->c->escape($k) . "` >= " . floatval($v[0])) : ("(`" . $this->c->escape($k) . "` >= " . floatval(array_shift($v)) . ") OR (" . $this->c->cond()->gte($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ("`" . $this->c->escape($k) . "` >= `" . $this->c->escape($v) . "`") . ($append ? ")" : "");
			return $this;
		}
		public function like(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ("`" . $this->c->escape($k) . "` LIKE '" . $this->c->escape($v[0]) . "'") : ("(`" . $this->c->escape($k) . "` LIKE '" . $this->c->escape(array_shift($v)) . "') OR (" . $this->c->cond()->like($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ("`" . $this->c->escape($k) . "` LIKE `" . $this->c->escape($v) . "`") . ($append ? ")" : "");
			return $this;
		}
		public function between(string $k, $a, $b, int $flags = 128) {
			$append = !empty($this->cond);
			if((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
				$a = [$a];
				$b = [$b];
			};
			if(is_array($a) && is_array($b) && (count($a) < 1 || count($b) < 1))
				return false;
			elseif(is_array($a) && is_array($b) && (count($a) < 2 || count($b) < 2))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ("`" . $this->c->escape($k) . "` BETWEEN " . floatval($a[0]) . " AND " . floatval($b[0])) . ($append ? ")" : "");
			elseif(is_array($a) && is_array($b))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ("(" . $this->c->escape($k) . "` BETWEEN " . floatval(array_shift($a)) . " AND " . floatval(array_shift($b)) . ") " . ($flags & SQ::COND_AND ? "AND" : "OR") . " (" . $this->c->cond()->between($k, $a, $b) . ")") . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ("`" . $this->c->escape($k) . "` BETWEEN `" . $this->c->escape($a) . "` AND `" . $this->c->escape($b) . "`") . ($append ? ")" : "");
			return $this;
		}
		public function in(string $k, array $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(count($v) < 1)
				return false;
			$values = array_map(function($n) use ($flags) {
				if(is_int($n) || is_float($n))
					return strval($n);
				if(is_array($n))
					return implode(", ", array_map(function($n2) use ($flags) {
						if(is_int($n2) || is_float($n2))
							return strval($n2);
						return $this->quote(($flags & SQ::CASE_INSENSITIVE) ? strtolower($n2) : $n2, $flags);
					}, $n));
				return ($flags & SQ::CASE_INSENSITIVE ? "LOWER(" : "") . "`" . $this->c->escape($n) . "`" . ($flags & SQ::CASE_INSENSITIVE ? ")" : "");
			}, $v);
			$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . (($flags & SQ::CASE_INSENSITIVE ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . ($flags & SQ::CASE_INSENSITIVE ? ")" : "") . " IN (" . implode(", ", $values) . ")") . ($append ? ")" : "");
			return $this;
		}
		public function begins(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(is_int($v) || is_float($v)) {
				$v = [$v];
			};
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ((($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "(" : "CONCAT(") . $this->quote($v[0], $flags) . ($this->c->SQLite ? " || '%')" : ", '%')")) : ("(" . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "(" : "CONCAT(") . $this->quote(array_shift($v), $flags) . ($this->c->SQLite ? " || '%')" : ", '%')") . ") OR (" . $this->c->cond()->like($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "(" : "CONCAT(") . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($v) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . ($this->c->SQLite ? " || '%')" : ", '%')")) . ($append ? ")" : "");
			return $this;
		}
		public function ends(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(is_int($v) || is_float($v)) {
				$v = [$v];
				$quotes = "";
			};
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ((($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "('%' || " : "CONCAT('%', ") . $this->quote($v[0], $flags) . ")") : ("(" . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "('%' || " : "CONCAT('%', ") . $this->quote(array_shift($v), $flags) . ")" . ") OR (" . $this->c->cond()->like($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "('%' || " : "CONCAT('%', ") . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($v) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . ")") . ($append ? ")" : "");
			return $this;
		}
		public function contains(string $k, $v, int $flags = 128) {
			$append = !empty($this->cond);
			if(is_int($v) || is_float($v)) {
				$v = [$v];
				$quotes = "";
			};
			if(is_array($v) && count($v) < 1)
				return false;
			if(is_array($v))
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((count($v) < 2) ? ((($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "('%' || " : "CONCAT('%', ") . $this->quote($v[0], $flags) . ($this->c->SQLite ? " || '%')" : ", '%')")) : ("(" . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "('%' || " : "CONCAT('%', ") . $this->quote(array_shift($v), $flags) . ($this->c->SQLite ? " || '%')" : ", '%')") . ") OR (" . $this->c->cond()->like($k, $v) . ")")) . ($append ? ")" : "");
			else
				$this->cond = ($append ? ("({$this->cond}) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " (") : "") . ((($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($k) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . " LIKE " . ($this->c->SQLite ? "('%' || " : "CONCAT('%', ") . (($flags & SQ::CASE_INSENSITIVE) ? "LOWER(" : "") . "`" . $this->c->escape($v) . "`" . (($flags & SQ::CASE_INSENSITIVE) ? ")" : "") . ($this->c->SQLite ? " || '%')" : ", '%')")) . ($append ? ")" : "");
			return $this;
		}

		public function not($cond = "", int $flags = 128) {
			if(!empty($cond) && empty($this->cond))
				$this->cond = "NOT ({$cond})";
			elseif(!empty($cond))
				$this->cond = "($this->cond) " . ($flags & SQ::COND_OR ? "OR" : "AND") . " NOT ({$cond})";
			else
				$this->cond = "NOT ({$this->cond})";
			return $this;
		}

		public function __toString(): string {
			return $this->cond;
		}
	}


	function SSQL($host, $user, $password, $db, &$object = "return") {
		if($object=="return")
			return new SSQL($host, $user, $password, $db);
		else
			$object = new SSQL($host, $user, $password, $db);
	};
?>

