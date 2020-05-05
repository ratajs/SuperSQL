# Super-MySQL
Super MySQL is an easy-to-use powerful SQL query builder.

## Requirements
* PHP 7.0+
* MySQL
* PDO MySQL extension

## Example
Let’s have a table “<strong>users</strong>” with 5 columns: `uid`, `username`, `password`, `sign_up_time` and `nickname`

```php
<?php
  include "smysql.php";

  //Connect with new Smysql($host, $user, $password, $database)
  $smysql = new Smysql("localhost", "root", "root", "db");
  
  //To execute raw SQL query use $smysql->q($q[, $a])
  $smysql->q("SELECT * FROM users");
  
  //You can use wildcards for escaping
  $smysql->q("SELECT * FROM users WHERE `username`=%1 OR `nickname`=%2", [$name, $nick]);

  //For simple requests use $smysql->read($table[, $flags]) or $smysql->read($table, $cond[, $flags]), you need to use flag ALWAYS_ARRAY to return array even when there is only one result
  $users = $smysql->read("users", SMQ::ALWAYS_ARRAY);
  $user = $smysql->read("users", ['uid' => $id]);
  
  //You can use more conditions
  $user = $smysql->read("users", ['username' => $name, 'password' => $pass]);
  
  //You can use the COND_OR for OR operator instead of AND
  $user = $smysql->read("users", ['sign_up_time' => $time, 'nickname' => $nick], SMQ::ALWAYS_ARRAY | SMQ::COND_OR)[0];
  
  //You can use custom conditions
  $users = $smyslq->read("users", "`sign_up_time` > " . bcsub(time(), 3600));
  
  //You can use more of them
  $users = $smyslq->read("users", ["`sign_up_time` > " . bcsub(time(), 3600), "`nickname` IS NOT NULL"], SMQ::ALWAYS_ARRAY);

  //For more complicated requests use $smysql->select($table[, $order[, $cols[, $flags]]]), you can use array keys for aliases
  $users = $smysql->select("users", "registered", ['id' => "uid", 'name' => "username", "sign_up_time", "nickname"], SMQ::ORDER_DESC)->fetch(SMQ::FETCH_ALL);

  //Or $smysql->selectWhere($table, $cond[, $order[, $cols[, $flags]]])
  $user = $smysql->selectWhere("users", ['uid' => $id], ['id' => "uid", 'name' => "username", "sign_up_time", 'nick' => "nickname"])->fetch();
  
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
?>
```
