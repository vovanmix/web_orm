#Simple MySQL ORM
This project is a simple lightweight ORM for MySQL written on PHP
It works with PHP 5.3+ and MySQL with PDO_MYSQL for PHP enabled

To use it, simply include the class file and create an instance:
```
require_once("orm.pdo.php");
$config = array(
	'host' => $HOST,
	'base' => $DATABASE_NAME,
	'user' => $USER,
	'password' => $PASSWORD,
	'socket' => $SOCKET //optional
);
$ORM = new ormPDOClass($config);
```
##Searching
After that, you can use the object to search:
```
$foundRecords = $ORM->find('all', 'table1', [
	'conditions' => [
		['table1.field1', '>' 12],
		['table1.field2', '=' [1, 2, 3]],
		'OR' => [
			['table1.field3', '!=', NULL],
			['table1.field4', '.=', 'table1.field5']
		]
	],
	'joins' => [
		['table2', [
			['table2.field1', '.=', 'table1.field5']
		]]
	],
	'fields' => [
		'table2.field1' => 'Name',
		'table1.field5' => 'Value'
	],
	'limit' => 10,
	'order' => [
		'table1.field1' => 'asc',
		'table1.field2' => 'desc'
	]
]);
```
This will execute the following SQL query:
```
SELECT table2.field1 as Name, table1.field5 as Value FROM table1
LEFT JOIN table2 ON table2.field1 = table1.field5
WHERE table1.field1 > 12 AND table1.field2 IN (1, 2, 3) AND (table1.field3 IS NOT NULL OR table1.field4 = table1.field5)
ORDER BY table1.field1 asc, table1.field2 desc
limit 10
```
You can use several types of find queries:
`all` will return array of all found records,
`first` will return first found record,
`list` will return all records in the following format: first value in the "fields" setting as key, and the second as value

Also you can use a magic functions to run Find:
```
$user = $ORM->getById('users', 1);
//is equal to $ORM->find('first', 'users', ['conditions' => [['id', '=', 1]]]);
$users = $ORM->findByCity('users', 'Los Angeles');
//is equal to $ORM->find('all', 'users', ['conditions' => [['city', '=', 'Los Angeles']]]);
 ```
##Other operations
You can use the object to insert, it will return the ID of the inserted record
```
$ID = $ORM->save('advert_logs', [
	'advert_id' => $id,
	'price' => $advert['price'],
	'time' => date('Y-m-d H:i:s'),
	'operation' => $operation,
	'comment' => $comment,
	'state' => $new_state,
]);
```
You can use the object to update, it will return count of updated records
```
$updatedCount = $ORM->update('user', [
		'last_activity' => date('Y-m-d H:i:s')
	],
	[
		['id', '=', $user_id]
	]
);
```
You can use the object to delete, it will return count of deleted records
```
$ORM->remove('variants', [
	['user_id', '=', $user_id],
	['advert_id', '=', $id],
	['demand_id', '=', $current_demand]
]);
```
##Models
You can simplify your code by omitting table names with the help of Models. You need to include a model class and create a new instance:
```
require_once("model.php");
$userModel = new Model( false, 'user', $ORM->connection );
//or
$userModel = new Model( $config, 'user' );
$user = $userModel->getById(1);
```


##Debugging
There are several parameters for debugging:
```
$ORM->debug = true;
```
Is used to output all queries that will be executed
```
$ORM->print_errors = true;
```
Is used for printing all errors found while executing
```
$ORM->fictive = true;
```
Is used for just printing, but not executing queries