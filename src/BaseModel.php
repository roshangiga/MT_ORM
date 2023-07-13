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

use mtorm\Collection;

abstract class BaseModel
{
    protected string $table;
    protected int $last_id;
    protected string $last_query;
    protected array $fields = [];
    protected array $excluded_fields = [];

    public function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . static::$tableName;
        $this->last_id = $wpdb->insert_id;
        $this->last_query = $wpdb->last_query;

        // Check if inherited fields are set, if they are not, get columns from the database
        $fields = static::$fields ?? $this->getTableColumns();

        // Exclude fields specified in $excludeFields property of the child class
        if (property_exists($this, 'excludeFields')) {
            $this->excluded_fields = static::$excludeFields;
            $fields = array_diff($fields, $this->excluded_fields);
        }

        $this->fields = $fields;

    }

    protected function getTableColumns(): array {
        global $wpdb;

        $sql = "SHOW COLUMNS FROM $this->table";
        return $wpdb->get_col($sql);
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

    // Magic method for getting properties
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        if (!in_array($name, $this->fields, true)) {
            throw new \InvalidArgumentException("Undefined property: {$name}. Is it defined in fields?");
        }

        return null;
    }

    // Magic method for setting properties
    public function __set($name, $value) {
        if (in_array($name, $this->fields, true)) {
            $this->$name = $value;
        } else {
            throw new \InvalidArgumentException("Undefined property: {$name}. Is it defined in fields?");
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
    private function update(array $data, array $where)  {
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
     * Insert row or update (if $data['id'] is passed)
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

        $values = [];

        // Only include properties that correspond to database fields
        foreach ($this->fields as $field) {
            if (property_exists($this, $field)) {
                $values[$field] = $this->$field;
            }
        }
        return $values;
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
     * Fetches a single record from the table by its id.
     *
     * **Example:**
     * ```php
     * $history = History::get(1); // Fetches the history with the id of 1
     * ```
     * @param int $id The id of the record to fetch.
     *
     * @return BaseModel Returns an instance of the calling class that represents the fetched record.
     *
     * @throws \Exception Throws an exception if the SQL query fails or the record is not found.
     */
    public static function get(int $id): BaseModel {
        global $wpdb;
        $model = new static();

        $sql = $wpdb->prepare("SELECT * FROM {$model->table} WHERE id = %d", $id);
        $result = $wpdb->get_row($sql);

        if (empty($result)) {
            throw new \mtorm\NoResultException("No record found with ID $id in {$model->table}");
        }

        $model->setFields($result);
        return $model;
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
     * @throws \InvalidArgumentException If the $conditions argument is not an array.
     * @throws NoResultException If no record matches the provided conditions.
     */
    public static function getWhere(array $conditions = []): BaseModel {
        if (empty($conditions)) {
            throw new \InvalidArgumentException("Invalid argument provided for conditions. Expected associative array.");
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

        return $model->getCollectedResults($results, null);
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

        if (isset($this->id)) {
            return $wpdb->delete($this->table, ['id' => $this->id]) > 0;
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
            throw new \InvalidArgumentException("Invalid argument provided for conditions. Expected associative array.");
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
     * @throws \InvalidArgumentException if the $models collection is empty.
     * @throws \RuntimeException if the SQL query fails.
     *
     * @return bool True if the operation was successful, false otherwise.
     */
    public static function bulkSave(Collection $models, array $data): bool
    {
        if(empty($models)) {
            throw new \InvalidArgumentException("The collection of models is empty.");
        }

        global $wpdb;
        $model = new static();
        $table = $model->getTable();

        // Collect the IDs of the models
        $ids = [];
        foreach($models as $model) {
            $ids[] = $model->id;
        }

        // Prepare the SET clause
        $set = [];
        foreach($data as $key => $value) {
            $set[] = $wpdb->prepare("{$key} = %s", $value);
        }
        $setClause = implode(", ", $set);

        // Prepare the WHERE clause
        $idsFormat = implode(", ", array_fill(0, count($ids), '%d'));
        $whereClause = $wpdb->prepare("id IN ($idsFormat)", $ids);

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

        if (isset($this->id)) {
            return $this->update($data, ['id' => $this->id]);
        }

        return $this->replace($data);
    }

}

// Custom exception class

class NoResultException extends \RuntimeException
{
}