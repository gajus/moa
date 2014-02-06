# MOA

MOA provides abstraction layer for interacting with database tables. MOA is not [ORM](http://en.wikipedia.org/wiki/Object-relational_mapping). It is closer to [Active Record pattern](http://en.wikipedia.org/wiki/Active_record_pattern) implementation.

### Not Active Record

MOA's build script fetches all of the database attributes to build the models. Each model has definition of the columns (column name, type, etc.) and constraints. The primary purpose of this script is to reduce manually typed duplication of data representation.

### Active Record

All models extend `gajus\moa\Mother`. Mother has getters and setters that use the prefetched table attributes to work out when you are trying to assign a non-existing property, save object without all the required properties, or other cases that would otherwise cause an error only at the time of interacting with the database.

In addition to the getters and setters, MOA provides the following methods:

* `save()` – to insert/update entry in the database
* `delete()` – to remove the entry from the database

The extending models inherit the following methods:

* `afterInsert()`
* `afterUpdate()`
* `afterDelete()`

Each of which can interupt the respective operation.

## Hierarchy

```
gajus\moa\Mother Mother of all models.
    your\Base [optional]
        MOA generated models
            your hand-typed models [optional]
```

## Building models

Models are built using `./bin/build.php` script. The following parameters are available:

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

Examples used for unit testing are built using:

```
php ./build.php --namespace "sandbox\moa" --database "moa" --clean --path "./sandbox/moa"
```