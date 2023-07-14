<?php

/**
 * Created by PhpStorm.
 * User: roshan.summun
 * Date: 7/12/2023
 * Time: 10:20 AM
 *
 * All model Classes should extend this abstract BaseModel Class. It has all the wrapper for
 * get, getAll, delete, update.
 */
namespace roshangiga;

use InvalidArgumentException;

abstract class BaseModel
{
    protected string $table;
    protected int $last_id;
    protected string $last_query;
    protected array $fields = [];

    /**
     * Primary key column name for the table.
     * Override this in child classes if primary key is not 'id'.
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * Associative array defining one-to-one relationships.
     *
     * Format:
     * ```
     * protected static array $excludeFields = ['timestamp',];
     * ```
     *
     * @var array<string, string>
     */
    protected array $excludeFields = [];
    /**
     * Associative array defining one-to-one relationships.
     *
     * Format:
     * ```
     * protected static array $oneToOne = [RelatedClassName => 'foreign_key_name',]
     * ```
     *
     * @var array<string, string>
     */
    protected static array $oneToOne = [];

    /**
     * Associative array defining one-to-many relationships.
     *
     * Format:
     * ```
     * protected static array $oneToMany = [RelatedClassName => 'foreign_key_name',]
     * ```
     *
     * @var array<string, string>
     */
    protected static array $oneToMany = [];


    public function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . static::$tableName;
        $this->last_id = $wpdb->insert_id;
        $this->last_query = $wpdb->last_query;

        // Check if inherited fields are set, if they are not, get columns from the database
        $fields = static::$fields ?? $this->getTableColumns();

        // Exclude fields specified in $excludeFields property of the child class
        if (isset(static::$excludeFields)) {
            $fields = array_diff($fields, static::$excludeFields);
        }

        $this->fields = $fields;
    }

    protected function getTableColumns(): array {
        global $wpdb;

        $sql = "SHOW COLUMNS FROM $this->table";
        return $wpdb->get_col($sql);
    }

    /**
     * Fetching related data if relationships are defined
     *
     * @param BaseModel $model
     * @return BaseModel
     */

    public static function fetchingRelatedDataIfRelationshipsDefined(BaseModel $model): BaseModel {

        foreach (static::$oneToOne as $relatedModel => $keys) {

            $localValue = $model->{$keys['local_key']};
            $relatedData = $relatedModel::getWhere([$keys['foreign_key'] => $localValue]);

            $classParts = explode('\\', $relatedModel);
            $className = end($classParts);
            $model->{$className} = $relatedData; // appending the related data to the model
        }

        foreach (static::$oneToMany as $relatedModel => $keys) {
            $localValue = $model->{$keys['local_key']};
            $relatedData = $relatedModel::getAll([$keys['foreign_key'] => $localValue]);

            // Map through the related data collection and get fields for each model
            $classParts = explode('\\', $relatedModel);
            $className = end($classParts);
            $model->{$className} = $relatedData; // appending the related data to the model
        }

        return $model;
    }

    /**
     * Get a Collection based on WP query results
     *
     * @param array $results
     * @return Collection
     */
    private function getCollectedResults(array $results): Collection {
        if (empty($results)) {
            return new Collection([]);
        }

        $list = [];
        foreach ($results as $result) {
            $model = new static();
            $model->setFields($result);
            $list[] = $model;
        }

        return new Collection($list);
    }

    public function getTable(): string {
        return $this->table;
    }

    public function getLastQuery(): string {
        return $this->last_query;
    }

    public function getLastId(): int {
        return $this->last_id;
    }

    /**
     * Checks if the provided property exists in either the model's fields or its relationships.
     *
     * @param string $propertyName The name of the property to check.
     *
     * @return bool Returns true if the property exists in either fields or relationships, false otherwise.
     */
    protected function checkPropertyExistence($propertyName): bool {
        // Check if the property is in fields
        if (in_array($propertyName, $this->fields, true)) {
            return true;
        }

        // Combine the relationships and check if the property is in there
        $relationships = array_merge(static::$oneToOne, static::$oneToMany);

        foreach ($relationships as $relatedModel => $keys) {
            $classParts = explode('\\', $relatedModel);
            $className = end($classParts);

            if ($className === $propertyName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Magic getter method.
     *
     * @param string $name Name of the property.
     * @return mixed  Value of the property.
     * @throws InvalidArgumentException if the property is not defined.
     */
    public function __get($name) {
        if ($this->checkPropertyExistence($name)) {
            return $this->$name;
        }

        throw new InvalidArgumentException("Undefined property: {$name}. Is it defined in fields or relationships?");
    }

    // Magic method for setting properties
    public function __set($name, $value) {
        if ($this->checkPropertyExistence($name)) {
            $this->$name = $value;
        } else {
            throw new InvalidArgumentException("Undefined property: {$name}. Is it defined in fields or relationships?");
        }
    }

    // Magic method for checking if a property is set
    public function __isset($name) {
        return isset($this->$name);
    }

    /**
     * Builds a SQL query from an array of conditions
     *
     * @param array $conditions
     * @param BaseModel $model
     * @return string the prepared SQL query
     */
    private static function buildWhereClause(array $conditions, BaseModel $model): string {
        if (empty($conditions)) {
            return "1 = 1";
        }

        global $wpdb;

        $where = [];
        foreach ($conditions as $field => $value) {
            if (in_array($field, $model->fields, true)) {
                $where[] = $wpdb->prepare("{$field} = %s", $value);
            }
        }

        return implode(" AND ", $where);
    }


    /**
     * Updates a row in the table.
     *
     * @param array $data Data to update (in column => value pairs).
     * @param array $where A named array of WHERE clauses (in column => value pairs).
     * @return bool|int The number of rows updated, or false on error.
     */
    private function update(array $data, array $where) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            $data,
            $where
        );

        if ($wpdb->last_error) {
            throw new \RuntimeException("Update operation failed: " . $wpdb->last_error);
        }

        $this->last_id = $wpdb->insert_id;
        $this->last_query = $wpdb->last_query;

        // After performing update or replace, update the class properties.
        $this->setFields($data);

        return $result;
    }

    /**
     * Insert row or update (if $this->primaryKey is passed & exists)
     *
     * @param array $data Data to update (in column => value pairs).
     * @return bool|int The number of rows updated, or false on error.
     */
    private function replace(array $data) {
        global $wpdb;

        $result = $wpdb->replace(
            $this->table,
            $data,
        );

        if ($wpdb->last_error) {
            throw new \RuntimeException("Update operation failed: " . $wpdb->last_error);
        }

        $this->last_id = $wpdb->insert_id;
        $this->last_query = $wpdb->last_query;

        // After performing update or replace, update the class properties.
        $this->setFields($data);

        return $result;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // PUBLIC METHODS
    // ----------------------------------------------------------------------------------------------------------------
    /**
     * Sets the values of the fields for this instance.
     *
     * This method only accepts values for fields that are part of the $db_fields array.
     * Values for non-existent fields are ignored.
     *
     * **Example:**
     * ```php
     * $history = new History();
     * $history->setFields([
     *     'id' => 123,
     *     'user_id' => 1,
     *     ...
     * ]);
     * ```
     *
     * @param array $values Data to update (in column => value pairs).
     * @return BaseModel The current instance for method chaining
     */
    public function setFields(array $values): BaseModel {
        // Intersect keys from $values with $db_fields
        $filteredValues = array_intersect_key($values, array_flip($this->fields));

        foreach ($filteredValues as $field => $value) {
            $this->$field = $value;
        }

        return $this;
    }

    /**
     * Gets the class properties as an associative array.
     *
     * @return array Returns an associative array of the class properties.
     */
    public function getFields(): array {
        $fields = [];
        foreach ($this->fields as $field) {
            if (property_exists($this, $field)) {
                $fields[$field] = $this->$field;
            } else {
                throw new Exception("Undefined property: {$field}. Is it defined in fields?");
            }
        }
        return $fields;
    }

    /**
     * Executes a raw SQL query and returns the result.
     *
     * **Example:**
     * ```php
     * $history = History::rawQuery("SELECT * FROM {$wpdb->prefix}history");
     * ```
     * @param string $sql The raw SQL query to execute.
     *
     * @return Collection Database query results in a collection of BaseModel objects.
     *
     * @throws \RuntimeException Throws an exception if the SQL query fails.
     */
    public static function rawQuery(string $sql): Collection {
        global $wpdb;
        $model = new static();

        $results = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error) {
            throw new \RuntimeException("Database query failed: " . $wpdb->last_error);
        }

        return $model->getCollectedResults($results);
    }


    /**
     * Fetches a single record from the table by its primary key value.
     *
     * **Example:**
     * ```php
     * $history = History::get(1); // Fetches the history with pk value of 1
     * ```
     * @param int $pkval The primary key value (default id).
     * @return BaseModel Returns an instance of the calling class that represents the fetched record.
     *
     */
    public static function get(int $pkval): BaseModel {

        global $wpdb;
        $model = new static();

        $sql = $wpdb->prepare("SELECT * FROM {$model->table} WHERE {$model->primaryKey} = %d", $pkval);
        $result = $wpdb->get_row($sql, ARRAY_A);

        if (empty($result)) {
            throw new NoResultException("No record found with {$model->primaryKey} ($pkval) in {$model->table}");
        }

        $model->setFields($result);

        return self::fetchingRelatedDataIfRelationshipsDefined($model);
    }

    /**
     * Retrieves a record based on provided conditions.
     *
     * This method accepts an associative array as its argument, where keys represent
     * column names and values represent the corresponding values to match against.
     * It returns an instance of the class that represents the first record matching
     * all the provided conditions.
     *
     * **Example:**
     * ```php
     * $user = User::getWhere(['name' => 'John Doe']); //get a user with the name "John Doe"
     * ```
     *
     * @param array $conditions Data to search (in column => value pairs).
     * @return static An instance of the calling class with properties set to the values of the retrieved record.
     *
     * @throws InvalidArgumentException If the $conditions argument is not an array.
     * @throws NoResultException If no record matches the provided conditions.
     */
    public static function getWhere(array $conditions = []): BaseModel {
        if (empty($conditions)) {
            throw new InvalidArgumentException("Invalid argument provided for conditions. Expected associative array.");
        }

        global $wpdb;
        $model = new static();

        $whereClause = self::buildWhereClause($conditions, $model);
        $sql = $wpdb->prepare("SELECT * FROM {$model->table} WHERE {$whereClause}");
        $result = $wpdb->get_row($sql, ARRAY_A);

        if (empty($result)) {
            throw new NoResultException("No record found for : " . $sql);
        }

        $model->setFields($result);

        return $model;
    }

    /**
     * Fetches all records from the table that meet the provided conditions.
     *
     * **Example:**
     * ```php
     * $histories = History::getAll(['user_id' => 1]); // Fetches all histories for user with id 1
     * ```
     * @param array $conditions Data to search (in column => value pairs).
     * @return Collection Returns an array of objects that are instances of the calling class, each representing a record in the table that meets the conditions.
     * @throws \Exception Throws an exception if the SQL query fails.
     */
    public static function getAll(array $conditions = []): Collection {

        global $wpdb;
        $model = new static(); // Creates an instance of the derived class

        $whereClause = self::buildWhereClause($conditions, $model);
        $sql = $wpdb->prepare("SELECT * FROM {$model->table} WHERE {$whereClause}");
        $results = $wpdb->get_results($sql, ARRAY_A);

        $rows = [];
        foreach ($results as $key => $result) {

            // Creating a new instance for each row
            $rowModel = new static();
            $rowModel->setFields($result);

            // Fetching related data if relationships are defined
            $rows[] = self::fetchingRelatedDataIfRelationshipsDefined($rowModel);
        }

        return new Collection($rows);
    }

    /**
     * Deletes the current record from the database.
     *
     * **Example:**
     * ```php
     * $history = History::get(123);  // Retrieve an instance from the database
     * $history->delete();            // Delete the corresponding database record
     * ```
     * @return bool true if the delete operation was successful, false otherwise
     */
    public function delete(): bool {
        global $wpdb;

        $primaryKey = static::$primaryKey ?? 'id';

        if (isset($this->$primaryKey)) {
            return $wpdb->delete($this->table, [$primaryKey => $this->$primaryKey]) > 0;
        }
        return false;
    }


    /**
     * Delete records matching provided conditions.
     *
     * **Example:**
     * ```php
     * $result = History::deleteWhere(['user_id' => 1]); // Deletes all histories for user with id 1
     * ```
     *
     * @param array $conditions Data to search (in column => value pairs).
     * @return int|false The number of rows updated, or false on error.
     */
    public static function deleteWhere(array $conditions): int {
        if (empty($conditions)) {
            throw new InvalidArgumentException("Invalid argument provided for conditions. Expected associative array.");
        }

        global $wpdb;
        $model = new static(); // Creates an instance of the derived class

        $whereClause = self::buildWhereClause($conditions, $model);
        $sql = $wpdb->prepare("DELETE * FROM {$model->table} WHERE {$whereClause}");

        $result = $wpdb->query($sql);

        if ($wpdb->last_error) {
            throw new \RuntimeException("Delete operation failed: " . $model->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Performs a bulk update on a collection of models.
     *
     * * **Example:**
     * ```php
     * $histories = History::getAll(['user_id' => 1]);
     * $result = History::bulkSave($histories, ['is_paid' => true]); // Set is_paid to true for all histories
     * ```
     * @param Collection $models A collection of models to update.
     * @param array $data An associative array of field names and values to update.
     *
     * @return bool True if the operation was successful, false otherwise.
     * @throws \RuntimeException if the SQL query fails.
     *
     * @throws InvalidArgumentException if the $models collection is empty.
     */
    public static function bulkSave(Collection $models, array $data): bool {
        if (empty($models)) {
            throw new \InvalidArgumentException("The collection of models is empty.");
        }

        global $wpdb;
        $model = new static();
        $table = $model->getTable();
        $primaryKey = static::$primaryKey ?? 'id';

        // Collect the IDs of the models
        $ids = [];
        foreach ($models as $model) {
            $ids[] = $model->$primaryKey;
        }

        // Prepare the SET clause
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = $wpdb->prepare("{$key} = %s", $value);
        }
        $setClause = implode(", ", $set);

        // Prepare the WHERE clause
        $idsFormat = implode(", ", array_fill(0, count($ids), '%d'));
        $whereClause = $wpdb->prepare("{$primaryKey} IN ($idsFormat)", $ids);

        // Prepare the full SQL statement
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";

        $result = $wpdb->query($sql);

        if ($wpdb->last_error) {
            throw new \RuntimeException("Bulk update operation failed: " . $wpdb->last_error);
        }

        return $result !== false;
    }

    /**
     * Saves the current object to the database.
     *
     * If the object's id property is set, it updates the existing record.
     * If the id property is not set, it inserts a new record.
     *
     * **Example:**
     * ```php
     * $history = new History();
     * $history->user_id = 1;
     * $history->order_id = 123;
     * $history->save();
     * ```
     * @return bool|int The number of rows updated, or false on error.
     *
     */
    public function save() {
        $data = $this->getFields();
        $primaryKey = static::$primaryKey ?? 'id';

        if (isset($this->$primaryKey)) {
            return $this->update($data, [$primaryKey => $this->$primaryKey]);
        }

        return $this->replace($data);
    }
}

// Custom exception class

class NoResultException extends \RuntimeException
{
}