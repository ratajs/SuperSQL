# SuperSQL
SuperSQL is an easy-to-use powerful SQL query builder for SQLite and MySQL.

## Requirements
* PHP 5.6+
* PDO SQLite / MySQL driver

## Examples
Let’s have a table “<strong>users</strong>” with 5 columns: `uid`, `username`, `password`, `sign_up_time` and `nickname`

```php
<?php
  include "ssql.php";

  //Connect with new Ssql($host[, $user[, $password[, $database]]])
  $ssql = new Ssql("localhost", "root", "root", "db"); // MySQL
  
  //Connect to SQLite with just the first parameter
  $ssql = new Ssql("db.sqlite");
  
  //To execute raw SQL query use $ssql->q($q[, $a]), FETCH_ALL returns an array of rows, FETCH_OBJECT and FETCH_ARRAY return one row per call
  $ssql->q("SELECT * FROM users")->fetch(SQ::FETCH_ALL);
  
  //You can use wildcards for escaping
  $ssql->q("SELECT * FROM users WHERE `username`=%0 OR `nickname`=%1", [$name, $nick])->fetch();
  
  //You can use queries as methods
  $ssql->getUser = "SELECT * FROM users WHERE `username`=%0 OR `nickname`=%1";
  $user = $ssql->getUser($name, $nick)->fetch();

  //For simple requests use $ssql->read($table[, $flags]) or $ssql->read($table, $cond[, $flags])
  //Read function uses FETCH_SMART as default (FETCH_OBJECT for one row, FETCH_ALL for more), so you need to use the FETCH_ALL flag to return an array even when there is only one result
  $users = $ssql->read("users", SQ::FETCH_ALL);
  $user = $ssql->read("users", ['uid' => $id]);
  
  //You can use more conditions
  $user = $ssql->read("users", ['username' => $name, 'password' => $pass]);
  
  //You can use the COND_OR flag for OR operator instead of AND
  $user = $ssql->read("users", ['username' => $name, 'nickname' => $nick], SQ::FETCH_ALL | SQ::COND_OR)[0];
  
  //You can use custom conditions
  $users = $ssql->read("users", "`sign_up_time` > " . bcsub(time(), 3600));
  
  //You can use more of them
  $users = $ssql->read("users", ["`sign_up_time` > " . bcsub(time(), 3600), "`nickname` IS NOT NULL"], SQ::FETCH_ALL);

  //For more complicated requests use $ssql->select($table[, $order[, $cols[, $limit[, $flags]]]]), you can use array keys for aliases
  $users = $ssql->select("users", "sign_up_time", ['id' => "uid", 'name' => "username", "sign_up_time", "nickname"], NULL, SQ::ORDER_DESC)->fetch(SQ::FETCH_ALL);

  //Or $ssql->selectWhere($table, $cond[, $order[, $cols[, $limit[, $flags]]]])
  $user = $ssql->selectWhere("users", ['uid' => $id], "name", ['id' => "uid", 'name' => "username", "sign_up_time", 'nick' => "nickname"])->fetch();
  
  //To quickly display tables use ->dump()
  $ssql->select("users")->dump();
  
  //Insert with $ssql->insert($table, $values[, $flags])
  $ssql->insert("users", [$id, $name, $pass, time(), NULL]);
  
  //You can use array keys as col names
  $ssql->insert("users", ['username' => $name, 'password' => $pass, 'sign_up_time' => time()]);
  
  //You can use INSERT_RETURN_ID flag for returning the first auto increment col value (depends on the database type)
  $id = $ssql->insert("users", ['username' => $name, 'password' => $pass, 'sign_up_time' => time()], SQ::INSERT_RETURN_ID);
  
  //Use $ssql->update($table, $cond, $values[, $flags]) to update rows
  $ssql->update("users", ['id' => $id], ['nickname' => $nick]);
  
  //You can delete rows with $ssql->delete($table, $cond[, $flags])
  $ssql->delete("users", ['id' => $id]);
  
  //Use $ssql->truncate($table[, $flags]) to delete all rows in a table
  $ssql->truncate("users");
?>
```

## Flags
Here is list of all SuperSQL flags:
* <b>ORDER_ASC</b>
* <b>ORDER_DESC</b>
* <b>JOIN_INNER</b>
* <b>JOIN_LEFT</b>
* <b>JOIN_RIGHT</b>
* <b>JOIN_FULL</b>
* <b>INSERT_RETURN_ID</b>
* <b>COND_AND</b>
* <b>COND_OR</b>
* <b>FETCH_OBJECT</b>
* <b>FETCH_ARRAY</b>
* <b>FETCH_ALL</b>
* <b>FETCH_SMART</b>
* <b>NO_ERROR</b>

## More examples

```php
<?php
  //To use the same database in more projects you can extend the Ssql class
  class mySsql extends Ssql {
    protected $host = "localhost";
    protected $user = "root";
    protected $password = "root";
    protected $db = "db"; //Optional
  };
  //And then create an instance
  $ssql = new mySsql();
  //If $db is not set, you can set it afterwards
  $ssql->changeDb("newDB");
  
  
  //Join
  
  
  //Use $ssql->selectJoin($table, $join, $on[, $order[, $cols[, $limit[, $flags]]]]) to execute a JOIN command
  $result = $ssql->selectJoin("users", "messages", ['from_user' => 'uid'], "time", "*", 5, SQ::ORDER_DESC)->fetch(SQ::FETCH_ALL);
  //Use JOIN_LEFT, JOIN_RIGHT and JOIN_FULL flags to other types of JOIN
  
  //To combine JOIN and WHERE use $ssql->selectJoinWhere($table, $join, $on, $cond[, $order[, $cols[, $limit[, $flags]]]])
  $result = $ssql->selectJoinWhere("users", "messages", ['from_user' => 'uid'], ['from_user' => $uid])->fetch(SQ::FETCH_ALL);
  
  
  
  //Table creation and deletion
  
  
  //For basic table creation use $ssql->createTable($table, $params[, $primary[, $flags]])
  $ssql->createTable("myTable", [
    'number' => [         // Column name
      'type' => "int",    // Column type
      'NULL' => false     // NULL
    ],
    'text' => [
      'type' => "varchar",
      'length' => 64,     // Max length
      'NULL' => true
    ]
  ], "number");
  
  //Use $ssql->deleteTable($table[, $flags]) to delete a table
  $c->deleteTable("myTable");
  
  //To see a list of all tables in your database use $ssql->tableList([$flags])
  $c->tableList();
?>
```
