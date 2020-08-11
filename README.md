# Super MySQL
Super MySQL is an easy-to-use powerful SQL query builder.

## Requirements
* PHP 5.6+
* MySQL
* PDO MySQL extension

## Examples
Let’s have a table “<strong>users</strong>” with 5 columns: `uid`, `username`, `password`, `sign_up_time` and `nickname`

```php
<?php
  include "smysql.php";

  //Connect with new Smysql($host, $user, $password[, $database])
  $smysql = new Smysql("localhost", "root", "root", "db");
  
  //To execute raw SQL query use $smysql->q($q[, $a])
  $smysql->q("SELECT * FROM users")->fetch(SMQ::FETCH_ALL);
  
  //You can use wildcards for escaping
  $smysql->q("SELECT * FROM users WHERE `username`=%0 OR `nickname`=%1", [$name, $nick])->fetch();
  
  //You can use queries as methods
  $smysql->getUser = "SELECT * FROM users WHERE `username`=%0 OR `nickname`=%1";
  $user = $smysql->getUser($name, $nick)->fetch();

  //For simple requests use $smysql->read($table[, $flags]) or $smysql->read($table, $cond[, $flags]), you need to use flag ALWAYS_ARRAY to return array even when there is only one result
  $users = $smysql->read("users", SMQ::ALWAYS_ARRAY);
  $user = $smysql->read("users", ['uid' => $id]);
  
  //You can use more conditions
  $user = $smysql->read("users", ['username' => $name, 'password' => $pass]);
  
  //You can use the COND_OR flag for OR operator instead of AND
  $user = $smysql->read("users", ['username' => $name, 'nickname' => $nick], SMQ::ALWAYS_ARRAY | SMQ::COND_OR)[0];
  
  //You can use custom conditions
  $users = $smyslq->read("users", "`sign_up_time` > " . bcsub(time(), 3600));
  
  //You can use more of them
  $users = $smyslq->read("users", ["`sign_up_time` > " . bcsub(time(), 3600), "`nickname` IS NOT NULL"], SMQ::ALWAYS_ARRAY);

  //For more complicated requests use $smysql->select($table[, $order[, $cols[, $limit[, $flags]]]]), you can use array keys for aliases
  $users = $smysql->select("users", "sign_up_time", ['id' => "uid", 'name' => "username", "sign_up_time", "nickname"], NULL, SMQ::ORDER_DESC)->fetch(SMQ::FETCH_ALL);

  //Or $smysql->selectWhere($table, $cond[, $order[, $cols[, $limit[, $flags]]]])
  $user = $smysql->selectWhere("users", ['uid' => $id], "name", ['id' => "uid", 'name' => "username", "sign_up_time", 'nick' => "nickname"])->fetch();
  
  //To quickly display tables use ->dump()
  $smysql->select("users")->dump();
  
  //Insert with $smysql->insert($table, $values[, $flags])
  $smysql->insert("users", [$id, $name, $pass, time(), NULL]);
  
  //You can use array keys as col names
  $smysql->insert("users", ['username' => $name, 'password' => $pass, 'sign_up_time' => time()]);
  
  //You can use INSERT_RETURN_ID flag for returning the first auto increment col value (depends on the database type)
  $id = $smysql->insert("users", ['username' => $name, 'password' => $pass, 'sign_up_time' => time()], SMQ::INSERT_RETURN_ID);
  
  //Use $smysql->update($table, $cond, $values[, $flags]) to update rows
  $smysql->update("users", ['id' => $id], ['nickname' => $nick]);
  
  //You can delete rows with $smysql->delete($table, $cond[, $flags])
  $smysql->delete("users", ['id' => $id]);
  
  //Use $smysql->truncate($table[, $flags]) to delete all rows in a table
  $smysql->truncate("users");
?>
```

## Flags
Here is list of all Super-MySQL flags:
* <i>ORDER_ASC</i>
* <b>ORDER_DESC</b>
* <i>JOIN_INNER</i>
* <b>JOIN_LEFT</b>
* <b>JOIN_RIGHT</b>
* <b>JOIN_FULL</b>
* <b>INSERT_RETURN_ID</b>
* <i>COND_AND</i>
* <b>COND_OR</b>
* <i>FETCH_OBJECT</i>
* <b>FETCH_ARRAY</b>
* <b>FETCH_ALL</b>
* <b>ALWAYS_ARRAY</b>
* <b>NO_ERROR</b>

The italic ones are not recognized at all because they are defaults.

## More examples

```php
<?php
  //To use the same database in more projects you can extend the Smysql class
  class mySmysql extends Smysql {
    protected $host = "localhost";
    protected $user = "root";
    protected $password = "root";
    protected $db = "db"; //Optional
  };
  //And then create an instance
  $smysql = new mySmysql();
  //If $db is not set, you can set it afterwards
  $smysql->changeDb("newDB");
  
  
  //Join
  
  
  //Use $smysql->selectJoin($table, $join, $on[, $order[, $cols[, $limit[, $flags]]]]) to execute a JOIN command
  $result = $smysql->selectJoin("users", "messages", ['from_user' => 'uid'], "time", "*", 5, SMQ::ORDER_DESC)->fetch(SMQ::FETCH_ALL);
  //Use JOIN_LEFT, JOIN_RIGHT and JOIN_FULL flags to other types of JOIN
  
  //To combine JOIN and WHERE use $smysql->selectJoinWhere($table, $join, $on, $cond[, $order[, $cols[, $limit[, $flags]]]])
  $result = $smysql->selectJoinWhere("users", "messages", ['from_user' => 'uid'], ['from_user' => $uid])->fetch(SMQ::FETCH_ALL);
?>
```
