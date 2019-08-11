# query-builder
Simple Query Builder in PHP 7.x

### Why?
* small (**2class**), simple, easy to use, for fun uses :)
* it use mysqli param binding, flexible and so on
* i would write a longer list but why? better if i show examples:


## Examples:

##### Example for **insert** new record into table **without** User model validation
```php
$res[] = $Log->builder()
    ->insert([
        'user_id' => 1,
        'type' => 1,
        'action' => '234234234',
        'data' => 'asdasdasdasd'
    ])
    ->run();
```

##### Example for **insert** new record into table **with** validation (*if you use id then this will be update*)
```php
// insert with validation
$res[] = $Log->save([
        'user_id' => 1,
        'type' => 1,
        'action' => '234234234',
        'data' => 'asdasdasdasd'
    ]);
```


##### Example for **update** an user
```php
// insert with validation
$res[] = $User->save([
        'id' => 16,
        'name' => 'New name'
    ]);
```
##### Example for **delete** a row fromlogs table
$res[] = $Log->builder()
    ->delete()
    ->where(['id',$id])
    ->run();

##### Example **aggregation** and **join** with condition and count 
```php
$res[] = $User->builder()
        ->count()
        ->join('albums', 'inner', ['user_id', 'id'])
        ->join('images', 'inner', ['album_id', 'albums.id'])
        ->where(['images.status', 0])
        ->run();
```

### Final queries look like:
```mysql
   SELECT COUNT(users.id) as aggr FROM users;
   SELECT albums.id FROM users INNER JOIN albums ON albums.user_id=users.id LIMIT 3;
   SELECT COUNT(users.id) as aggr FROM users INNER JOIN albums ON albums.user_id=users.id INNER JOIN images ON images.album_id=albums.id WHERE images.status = ?;
   INSERT INTO logs (user_id, type, action, data, ip, created) VALUES (?, ?, ?, ?, ?, ?);
```

## Information:

### Model methods (only the importants):
* **getCon(*Array*)** - static method which create connection if not exist
* **save(*Array*)** - validate informationor add default values based on data in model (this step is optional) then call the builder then insert or update
* **builder()** - create a new builder instance (*new QueryBuilder*)

### Querybuilder methods:
* **nameUpdate(*Array|String*)** - concat field name with table name if its needed
* **select(*Array|string*)** - create the base string *(ex. "SELECT albums.id FROM users")*
* **insert(*Array*)** - create the base string *(ex. "INSERT INTO logs (user_id, data, created) VALUES (?, ?, ?)")*
* **update(*Array*)** - create the base string *similiar like insert just with **UPDATE***
* **delete()** - create the base string *similiar like above just with **DELETE FROM***
* **getTypes(*Array*)** - create type string for mysqli bind based on provided data *(ex. 'sssd')*
* **where(Array|String, String)** - append condition to an internal array 
    * **arg0:** array or string example: ['id', 1] or ['score', '>', 2.3] or 'table.column=2'
    * **arg1:** (default: 'AND', insert *AND* or *OR* before the new condition)
                        
* **whereOr(Array|String)** - Like above but it use *OR* before the new condition

* **join(String|String|[String|String])** - join the main table with another table
    * **arg0:** - *table* name which will joined, example: *users*
    * **arg1:** - join type: *LEFT|RIGHT|INNER*, example: *inner*
    * **arg2:** - array with 2 element
        * **0:** - column name from join table, example: *id* or *images.id*
        * **1:** - column name from initial table, example: *user_id* or *users.id* 
* **group(Array)** - column names which used for group the select
* **order(Array, string)** - column names which used for order the select and direction *('ASC|DESC')*
* **limit(Integer,Integer?)** - limit the select query
* **build()** - create the final query string from given data *(where, groups etc)*
* **aggregation methods**: *avg/min/max/count/sum($field)*
* **run()** - build query and execute


