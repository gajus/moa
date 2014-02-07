<?php
if (php_sapi_name() !== 'cli') {
	throw new \RuntimeException('Interface is not "cli".');
}

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
		`data_type`,
		`is_nullable`,
		`extra`,
		`character_maximum_length`
	FROM
		`information_schema`.`columns`
	WHERE
		`table_schema` = DATABASE();
	")->fetchAll(PDO::FETCH_ASSOC);

$information_schema = [];

foreach ($columns as $column) {
	if ($column['character_maximum_length']) {
		$column['character_maximum_length'] = (int) $column['character_maximum_length'];
	}

	$information_schema[array_shift($column)][array_shift($column)] = $column;
}

unset($columns);

if (array_key_exists('clean', $parameters)) {
	$models = glob($parameters['path'] . '/*.php');

	foreach ($models as $model_file) {
		if(@unlink($model_file) === false) {
			throw new \RuntimeException('Insufficient permissions.');
		}
	}
}

$model_template = file_get_contents(__DIR__ . '/template/model.php');

foreach ($information_schema as $table_name => $columns) {
	$first_column = current($columns);

	if ($first_column['extra'] !== 'auto_increment') {
		throw new \LogicException('MOA does not work with tables without primary (surrogate) key.');
	}

	$columns = array_map(function ($column) {
		$column['is_nullable'] = $column['is_nullable'] === 'YES';

		return $column;
	}, $columns);

	$properties = [
		'{{namespace}}' => $parameters['namespace'],
		'{{model_name}}' => $table_name,
		'{{extends}}' => isset($parameters['extends']) ? $parameters['extends'] : '\gajus\moa\Mother',
		'{{table_name}}' => $table_name,
		'{{primary_key_name}}' => 'id',
		'{{columns}}' => var_export($columns, true)
	];

	file_put_contents($parameters['path'] . '/' . $table_name . '.php', str_replace(array_keys($properties), array_values($properties), $model_template));
}

echo 'Ok' . PHP_EOL;