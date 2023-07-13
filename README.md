# MT_ORM

MT ORM is a simple WordPress Object-Relational Mapping (ORM) designed to provide an easy way to interact with your database in WordPress.
It follows good practices like sanitizing and validating data, and handling errors.

Main features:

1. It implements the ActiveRecord pattern, where a single instance of a class represents a row in the database table.
2. CRUD operations (Create, Read, Update, Delete) are defined in this class. All of these methods use the _wpdb_ object.
3. Supports one-to-one and one-to-many relationships.
4. It provides a way to execute raw SQL queries using the `rawQuery` method.
5. It throws custom exceptions if anything goes wrong with the database operations.
6. It also includes a `Collection` feature for handling multiple records at once.

## Installation

Just copy the 2 classes in _src_ and require them anywhere you want to use it (such as in themes/templates).
You can bundle them in a plugin if you wish to use them globally (thats how I use it).

## Usage

```php
require_once 'path/to/BaseModel.php';

// First, define your own model class (Ideally in a separate file)
class Cafeteria extends BaseModel {
    protected static string $tableName = 'my_cafeteria';

    // Optional
    protected static array $excludeFields = ['updated_at'];
    
    // Optional
    protected static array $oneToOne = [
        Meal::class => 'cafeteria_id',  // assuming Meal has 'cafeteria_id' as foreign key
    ];

    // Optional
    protected static array $oneToMany = [
        Staff::class => 'cafeteria_id', // assuming Staff has 'cafeteria_id' as foreign key
    ];
    
    // ...
    // Any other custom methods you want to add
}

// Create a new Cafeteria entry
$cafeteria = new Cafeteria();
$cafeteria->name = 'Cafe1';
$cafeteria->location = 'Building1';
$cafeteria->save();

// Fetch an entry id 1 and access the associated relationships
$cafeteria = Cafeteria::get(1); 
$meal = $cafeteria->Meal;           //one-to-one relationship
$staffs = $cafeteria->Staff;        //one-to-many relationships
// ...

// Fetch Cafeteria entry with a WHERE clause
$cafeteria = Cafeteria::getWhere(['name' => 'Cafe1']);

// Fetch Cafeteria entries (empty args fetches all)
$cafeterias = Cafeteria::getAll(['location' => 'Rose Belle']);

// Bulk update Cafeteria entries
Cafeteria::bulkSave($cafeterias, ['location' => 'Curepipe']);

// Update an existing Cafeteria entry
$cafeteria = Cafeteria::get(1);     // assuming 1 is the id of the cafeteria
$cafeteria->name = 'New Cafe Name';
$cafeteria->save();

// Delete a Cafeteria entry
$is_deleted = Cafeteria::get(1)->delete();

// Delete Cafeteria entries that satisfy a condition
Cafeteria::deleteWhere(['location' => 'Building1']);
```


## Documentation

For further details, please refer to the inline documentation within the classes themselves. Every method is documented with information about its purpose, arguments, and return values.
