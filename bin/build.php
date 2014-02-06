<?php
if (php_sapi_name() !== 'cli') {
	throw new \RuntimeException('Interface is not "cli".');
}

// php ./build.php --namespace "sandbox\moa" --database "moa" --clean --path "./sandbox/moa"

$parameters = getopt('', ['path:', 'host:', 'database:', 'user:', 'password:', 'namespace:', 'extends:', 'clean']);

if (empty($parameters['database'])) {
	throw new \RuntimeException('"database" parameter is empty.');
}

if (empty($parameters['namespace'])) {
	throw new \RuntimeException('"namespace" parameter is empty.');
}

if (empty($parameters['path'])) {
	throw new \RuntimeException('"path" parameter is empty.');
} else if (!is_dir($parameters['path'])) {
	throw new \RuntimeException('"path" does not refer to an existing directory.');
}

$parameters['path'] = realpath($parameters['path']);
$parameters['host'] = isset($parameters['host']) ? ';host=' . $parameters['host'] : '';
$parameters['user'] = isset($parameters['user']) ? $parameters['user'] : null;
$parameters['password'] = isset($parameters['password']) ? $parameters['password'] : null;

$db = new \PDO('mysql:dbname=' . $parameters['database'] . $parameters['host'], $parameters['user'], $parameters['password']);

// @todo This could include indexes. Then isUnique would become redundant.

$columns = $db
	->query("
	SELECT
		`table_name`,
		`column_name`,
		`column_type`,
		`column_default`,
		`column_key`,
		`is_nullable`,
		`extra`,
		`character_maximum_length`
	FROM
		`information_schema`.`columns`
	WHERE
		`table_schema` = DATABASE();
	")->fetchAll(PDO::FETCH_ASSOC);

$parameter_type_map = [
	'int' => PDO::PARAM_INT,
	'bigint' => PDO::PARAM_INT,
	'smallint' => PDO::PARAM_INT,
	'tinyint' => PDO::PARAM_INT,
	'mediumint' => PDO::PARAM_INT,
	'enum' => PDO::PARAM_STR,
	'varchar' => PDO::PARAM_STR,
	'date' => PDO::PARAM_STR,
	'datetime' => PDO::PARAM_STR,
	'timestamp' => PDO::PARAM_STR,
	'char' => PDO::PARAM_STR,
	'decimal' => PDO::PARAM_STR,
	'text' => PDO::PARAM_STR,
	'longtext' => PDO::PARAM_STR
];

foreach($columns as &$column) {
	$column['column_type'] = strpos($column['column_type'], '(') === false ? $column['column_type'] : strstr($column['column_type'], '(', true);
	
	if (!isset($parameter_type_map[$column['column_type']])) {
		throw new UnexpectedValueException('Unsupported column type "' . $column['column_type'] . '".');
	}
	 
	// Automatically convert MySQL timestamp/datetime representation to UNIX timestamp.
	$column['select_name'] = $column['column_type'] === 'datetime' || $column['column_type'] === 'timestamp' ? 'UNIX_TIMESTAMP(`' . $column['table_name'] .'`.`' . $column['column_name'] . '`) `' . $column['column_name'] . '`' : '`' . $column['table_name'] .'`.`' . $column['column_name'] . '`';
	
	// Parameter type is used later to bind parameters in the prepared statements.
	$column['parameter_type'] = $parameter_type_map[$column['column_type']];

	#unset($column['column_type']);
	
	unset($column);
}

$information_schema = [];

foreach ($columns as $column) {
	$information_schema[array_shift($column)][array_shift($column)] = $column;
}

unset($columns);

if (array_key_exists('clean', $parameters)) {
	$models = glob($parameters['path'] . '/*.php');

	foreach ($models as $model_file) {
		if(@unlink($model_file) === false) {
			throw new RuntimeException('Insufficient permissions.');
		}
	}
}

$model_template = file_get_contents(__DIR__ . '/template.php');

foreach ($information_schema as $table_name => $columns) {
	$first_column = current($columns);

	// Do not create model for joining tables. First column is expected to be the primary key.
	if ($first_column['extra'] !== 'auto_increment') {
		continue;
	}

	$properties = [];
	$properties['table_name'] = $table_name;
	$properties['primary_key_name'] = 'id';
	$properties['select_statement'] = implode(', ', array_map(function ($e) { return $e['select_name']; }, $columns));
	$properties['columns'] = $columns;

	$model = $model_template;
	$model = str_replace('namespace;', 'namespace ' . $parameters['namespace'] . ';', $model);
	
	if (isset($parameters['extends'])) {
		$model = str_replace('\gajus\moa\Mother', $parameters['extends'], $model);
	}

	$model = str_replace('Model_Name', $table_name, $model);
	$model = str_replace('$properties', '$properties = ' . var_export($properties, true), $model);

	file_put_contents($parameters['path'] . '/' . $table_name . '.php', $model);
}

echo 'Ok' . PHP_EOL;