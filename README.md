# MOA

MOA (mother of all) is a database abstraction using [Active Record](http://en.wikipedia.org/wiki/Active_record_pattern) pattern:

> Active record is an approach to accessing data in a database. A database table or view is wrapped into a class. Thus, an object instance is tied to a single row in the table. After creation of an object, a new row is added to the table upon save. Any object loaded gets its information from the database. When an object is updated the corresponding row in the table is also updated. The wrapper class implements accessor methods or properties for each column in the table or view.

– http://en.wikipedia.org/wiki/Active_record_pattern

MOA is not [ORM](http://en.wikipedia.org/wiki/Object-relational_mapping), because it does not work with collections.

In general, MOA is for updating/inserting data, rather than retrieving existing data sets. Therefore, MOA does not implement elaborate finders, filters or methods for querying data. However, these libraries do:

* [PHP ActiveRecord](https://github.com/jpfuentes2/php-activerecord)
* [Paris](https://github.com/j4mie/paris)
* [Parm](https://github.com/cassell/Parm)

Instead, when using MOA you are supposed to write [custom methods with custom SQL queries](https://github.com/gajus/moa#individual-models) to retrieve data. You need to create instance of MOA only when you plan to update/insert data.

## Hierarchy & Responsibilities

### Model builder

[MOA builder script](https://github.com/gajus/moa#building-models) generates model file for each table using attributes fetched from the database. These attributes define column names, type, constraints, etc. The primary purpose of this script is to reduce manually typed duplication of data representation.

In other Active Record implementations, this is avoided either by hard-typing these attributes into your models, or allowing the base class to fetch them during the program run time. My view is that, the former is error-prone, while the latter (even with cache) is lazy-workaround that has a considerable performance hit.

### Mother

All models extend `gajus\moa\Mother`. Mother has getters and setters that use the prefetched table attributes to work out when you are trying to assign a non-existing property, save object without all the required properties, or other cases that would otherwise cause an error only at the time of interacting with the database.

In addition to the getters and setters, MOA provides the following methods:

* `save()` – To insert/update entry in the database.
* `delete()` – To remove the entry from the database.

Models inherit the following methods:

* `afterInsert()`
* `afterUpdate()`
* `afterDelete()`

Each of which can interrupt the respective operation.

As a result, project hierarchy is:

```
gajus\moa\Mother
    your\Base [optional]
        MOA generated models
            your hand-typed models [optional]
                your hand-typed domains [optional, for dealing with ORM]
```

## Naming convention

MOA assumes that your models are writen using underscore convention (e.g. `my_table_name`). Table names must be singular (e.g. `car` not `cars`). MOA generated models will use underscore convention.

While it is advised that you follow the same naming convention, models that extend MOA generated models can have any name.

## Example

```php
<?php
$car = new \my\app\model\Car($db); // $db is PDO instance
$car['colour'] = 'red';
$car->save();

echo $car['id']; // Newly entered record ID.
```

Take a look at the tests as they contain many more examples.

## Extending

### Mother

If you want to inject logic between Mother and the generated models, you need to:

1. Extend `gajus\moa\Mother` class.
2. Build models using `--extends` property with the name of that class.

### Individual models

Models generated using MOA are `abstract`. Therefore, you need to extend each model that you use, even if without adding additional logic:

```php
<?php
namespace my\app\model;

class Car extends \dynamically\generated\Car {
    static public function getLastBought (\PDO $db) {
        $car = $db->query("SELECT `" . static::$properties['primary_key_name'] . "` FROM `" . static::$properties['table_name'] . "` ORDER BY `purchase_datetime` DESC LIMIT 1");
        
        return new static::__construct($db, $car[static::$properties['primary_key_name']]);
    }

    static public function getManyWhereColour (\PDO $db, $colour) {
        $sth = $db->prepare("SELECT * FROM `" . static::$properties['table_name'] . "` WHERE `colour` = ?");
        $sth->execute([ $colour ]);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

> MOA convention is to prefix method names "getMany" for methods that return array and "get" that return instance of Mother.

You don't need to set anything else since all of the properties are already populated in the generated model. To view what properties are available for each table, refer to the generated models.

## Building models

Models are built using `./bin/build.php` CLI script. The following parameters are available:

```
--path [required] Path to the directory where the models will be constructed.
--database [required] MySQL database name.
--host MySQL database host.
--user MySQL database user.
--password MySQL database password.
--namespace [required] PHP class namespace;
--extends PHP class to extend. Defaults to \gajus\moa\Mother.
--clean Whipe out the directory.
```

e.g., the examples used for unit testing are built using:

```
php ./bin/build.php --namespace "sandbox\model\moa" --database "moa" --clean --path "./sandbox/model/moa"
```
