# SuperSQL
SuperSQL is an easy-to-use, yet powerful SQL query builder for SQLite and MySQL.

## Requirements
* PHP 7+
* PDO SQLite / MySQL driver

## Setup
* Download the <em>ssql.php</em> file ([https://raw.githubusercontent.com/ratajs/SuperSQL/2.3/ssql.php](https://raw.githubusercontent.com/ratajs/SuperSQL/2.3/ssql.php))
* Include the file with `include` / `require`

## Examples
Let’s have a table ‘<strong>users</strong>’ with 5 columns: `uid`, `username`, `password`, `sign_up_time` and `nickname`

```php
<?php
    include "ssql.php";

    //Connect to MySQL with new SSQL($host[, $user[, $password[, $database]]])
    $ssql = new SSQL("localhost", "root", "root", "db");

    //Connect to SQLite with just the first argument
    $ssql = new SSQL("db.sqlite3");

    //Use $ssql->q($q[, $a]) to execute a raw SQL query
    $ssql->q("SELECT * FROM `users`");

    //You can use wildcards for escaping
    $ssql->q("SELECT * FROM `users` WHERE `username`=%0 OR `nickname`=%1", [$name, $nick]);

    //You can use queries as methods
    $ssql->getUser = "SELECT * FROM `users` WHERE `username` = '%0' OR `nickname` = '%1'";
    $user = $ssql->getUser($name, $nick);

    //Use $ssql->read($table[, $cond][, $flags]) for simple reading
    $users = $ssql->read("users"); // Access the username of a specific user with $users[$index]->username
    $users = $users->data(); // If you need a raw array, for example for json_encode($users)
    $user = $ssql->read("users", ['uid' => $id]); // You can access the username with $user->username if only one result is returned

    //You can use the DEBUG flag to print this query before execution
    $user = $ssql->read("users", ['uid' => $id], SQ::DEBUG);

    //Or you can set the object‐wide $debug property to print all queries
    $ssql->debug = true;

    //You can use more conditions
    $user = $ssql->read("users", ['username' => $name, 'password' => $pass]);

    //You can use the COND_OR flag to use the OR operator instead of AND
    $user = $ssql->read("users", ['username' => $name, 'nickname' => $nick], SQ::COND_OR)[0];

    //You can use custom conditions
    $users = $ssql->read("users", "`sign_up_time` > " . bcsub(time(), 3600));

    //You can use more of them
    $users = $ssql->read("users", ["`sign_up_time` > " . bcsub(time(), 3600), "`nickname` IS NOT NULL"]);

    //Use $ssql->get($table[, $options][, $flags]) for more complex data retrieval
    $users = $ssql->get("users", ['order' => "sign_up_time", 'cols' => ['id' => "uid", 'name' => "username", "sign_up_time", "nickname"], 'limit' => 10], SQ::ORDER_DESC);
    $user = $ssql->get("users", ['cond' => ['uid' => $id], 'order' => "name", 'cols' => ['id' => "uid", 'name' => "username", "sign_up_time", 'nick' => "nickname"]]);

    //You can print the result of a ->read() or ->get() call as an HTML table
    print $ssql->read("users");

    //Insert with $ssql->put($table, $values[, $flags])
    $ssql->put("users", [$id, $name, $pass, time(), NULL]);

    //You can use array keys as col names
    $ssql->put("users", ['username' => $name, 'password' => $pass, 'sign_up_time' => time()]);

    //You can use INSERT_RETURN_ID flag for returning the first auto increment col value (depends on the database type)
    $id = $ssql->put("users", ['username' => $name, 'password' => $pass, 'sign_up_time' => time()], SQ::INSERT_RETURN_ID);

    //Use $ssql->put($table, $values, $cond[, $flags]) to update rows
    $ssql->put("users", ['nickname' => $nick], ['uid' => $id]);

    //You can delete rows by supplying NULL to the $values argument
    $ssql->put("users", NULL, ['uid' => $id]);
    $ssql->put("users", NULL); //Delete all users
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
* <b>CASE_INSENSITIVE</b>
* <b>NO_ERROR</b>
* <b>DEBUG</b>

## More examples

```php
<?php
    //You can extend the SSQL class
    class MySSQL extends SSQL {
        protected $host = "localhost";
        protected $user = "root";
        protected $password = "root";
        protected $db = "db"; //Optional
    };
    //And then create an instance
    $ssql = new MySSQL();
    //If $db is not set, you can set it afterwards
    $ssql->changeDb("newDB");


    //Join


    $result = $ssql->get("users", ['join' => "messages", 'on' => ['from_user' => 'uid'], 'order' => "time", 'limit' => 5], SQ::ORDER_DESC);
    //Use JOIN_LEFT, JOIN_RIGHT and JOIN_FULL flags for other types of JOIN

    $result = $ssql->get("users", ['join' => "messages", 'on' => ['from_user' => 'uid'], 'cond' => ['from_user' => $uid]]);



    //Table creation and deletion


    //For basic table creation use $ssql->createTable($table, $params[, $primary[, $flags]])
    $ssql->createTable("myTable", [
        'number' => [
            'type' => "int",
            'NULL' => false
        ],
        'text' => [
            'type' => "varchar",
            'size' => 64,
            'NULL' => true
        ]
    ], "number"); // Primary key

    //Use $ssql->deleteTable($table[, $flags]) to delete a table
    $ssql->deleteTable("myTable");

    //To see a list of all tables in your database use $ssql->tableList([$flags])
    print_r($ssql->tableList());


    //Advanced conditions


    //You can use more advanced conditions with $ssql->cond(), this will select users that have the same username and nickname signed up in the last hour
    $ssql->read("users", $ssql->cond()->eq("username", "nickname")->gte("sign_up_time", time() - 3600));

    //Use arrays to differentiate string values from column names and to specify more alternatives, the COND_OR flag is also supported
    //This will select users that have username either "ratajs" or "admin" or that have nickname "RatajS"
    $ssql->read("users", $ssql->cond()->eq("username", ["ratajs", "admin"])->eq("nickname", ["RatajS"], SQ::COND_OR));

    //->not() negates the condition, this will select users with usernames other than admin
    $ssql->read("users", $ssql->cond()->eq("username", ["admin"])->not());

    //You can also pass another condition to it, this will select users that don’t have any nickname with username other than "admin" or "root".
    $ssql->read("users", $ssql->cond()->eq("nickname", [""])->not($ssql->cond()->eq("username", ["admin", "root"])));

    //Supported conditions: ->eq(), ->lt(), ->gt(), ->lte(), ->gte(), ->like(), ->between(), ->begins(), ->ends(), ->contains() and ->in()

    //Transactions

    $ssql->beginTransaction();

    //...

    $ssql->endTransaction(); //Or $ssql->rollBackTransaction()
?>
```
