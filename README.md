# MT_ORM

MT ORM is a simple WordPress Object-Relational Mapping (ORM) designed to provide an easy way to interact with your database in WordPress projects.

## Installation

Just copy the 2 classes in models and require them anywhere you want to use it. You can wrap them in a plugin if you wish to use it globally.

## Usage

Here is a basic usage of the BaseModel class:

```php
<?php

require_once 'path/to/BaseModel.php';

use MT_Cafeteria\models\BaseModel;

// First, define your own model class
class MyModel extends BaseModel {
    protected static string $tableName = 'my_table';
    protected static array $fields = ['id', 'name', 'description'];
}

// Create a new instance
global $wpdb;
$mymodel = new MyModel($wpdb);

// Set fields
$mymodel->setFields([
    'name' => 'Sample Name',
    'description' => 'Sample Description'
]);

// Or set them alternatively using magic properties:
$mymodel->name = 'Sample Name';
$mymodel->description = 'Sample Description';

// Save to the database
$mymodel->save();

// Fetch by id
$mymodel = MyModel::get(1);

// Delete a record
$mymodel->delete();
```


## Documentation

For further details, please refer to the inline documentation within the classes themselves. Every method is documented with information about its purpose, arguments, and return values.
