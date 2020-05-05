# Super-MySQL
A powerful PHP SQL query builder

## Example
Let’s have a table users with 4 columns: uid, username, password, registered

```php
  <?php
    include "smysql.php";
    
    //Connect with new Smysql($host, $user, $password, $database)
    $smysql = new Smysql("localhost", "root", "root", "db");
    
    //For simple requests use $smysql->read($table[, $flags]) or $smysql->read($table, $cond[, $flags])
    $users = $smysql->read("users", SMQ::ALWAYS_ARRAY);
    $user = $smysql->read("users", ['uid' => $id]);
    
    //For more complicated requests use $smysql->select($table[, $order[, $cols[, $flags]]])
    $users = $smysql->select("users", "registered", ['id' => "uid", 'name' => "username", "registered"], SMQ::ORDER_DESC)->fetch(SMQ::FETCH_ALL | SMQ::ALWAYS_ARRAY);
    
    //Or $smysql->selectWhere($table, $cond[, $order[, $cols[, $flags]]])
    $user = $smysql->selectWhere("users", ['uid' => $id], ['id' => "uid", 'name' => "username", "registered"])->fetch();
  ?>
```
