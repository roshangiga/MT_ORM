# MT_ORM

MT ORM is a simple WordPress Object-Relational Mapping (ORM) designed to provide an easy way to interact with your database in WordPress.
It follows good practices like sanitizing and validating data, and handling errors.

Main features:

1. It implements the ActiveRecord pattern, where a single instance of a class represents a row in the database table.
2. CRUD operations (Create, Read, Update, Delete) are defined in this class. All of these methods use the _wpdb_ object.
3. The method `bulkSave` is used to update multiple records in a single query.
4. Magic methods `__get`, `__set` and `__isset` are used to access and set properties that match the fields in the database.
5. It also includes helper functions such as `setFields` and `getFields` to manage the properties of the model.
6. It provides a way to execute raw SQL queries using the `rawQuery` method.
7. It throws custom exceptions if anything goes wrong with the database operations.
8. It also includes a `Collection` feature for handling multiple records at once.

## Installation

Just copy the 2 classes in _models_ and require them anywhere you want to use it (such as in themes or plugins). You can wrap them in a plugin if you wish to use them globally.

## Usage

Here is a basic usage example:

```php
<?php

require_once 'path/to/BaseModel.php';

// First, define your own model class (Ideally in a separate file)
class MyModel extends BaseModel {
    protected static string $tableName = 'my_table';
    
    // Optional: If you leave fields empty, it will fetch all fields from the table
    protected static array $fields = ['id', 'name', 'description'];
    
    // Optional
    protected static array $excludeFields = ['updated_at'];

}

// Create a new instance
$mymodel = new MyModel();

// Set fields
$mymodel->setFields([
    'name' => 'Sample Name',
    'description' => 'Sample Description'
]);

// OR by using magic properties
$mymodel->name = 'Sample Name';
$mymodel->description = 'Sample Description';

// Save to the database
$mymodel->save();

// Fetch by id
$mymodelItem = MyModel::get(1);

// Delete a record
$mymodelItem->delete();
```


## Documentation

For further details, please refer to the inline documentation within the classes themselves. Every method is documented with information about its purpose, arguments, and return values.
