<?php

/**
 * Created by PhpStorm.
 * User: Roshan Summun ( roshangiga@gmail.com )
 * Date: 6/5/2023
 * Time: 11:59 AM
 *
 * All model Classes should extend this abstract BaseModel Class. It has all the wrapper for
 * get, getAll, delete, update.
 */
abstract class BaseModel
{
    private \wpdb $wpdb;
    protected string $table;
    protected int $last_id;
    protected array $db_fields = [];  // Stores the names of the database fields

    public function __construct(\wpdb $wpdb) {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . static::$tableName;
        $this->db_fields = static::$fields;
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
            global $wpdb;
            $model = new static($wpdb);
            $model->setFields($result);
            $list[] = $model;
        }

        return new Collection($list);
    }

    public function getTable(): string {
        return $this->table;
    }

    public function getLastId(): int {
        return $this->last_id;
    }

    // Magic method for getting properties
    public function __get($name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        return null;
    }

    // Magic method for setting properties
    public function __set($name, $value) {
        if (in_array($name, $this->db_fields, true)) {
            $this->$name = $value;
        } else {
            throw new \InvalidArgumentException("Invalid property: {$name}");
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

        $where = [];
        foreach ($conditions as $field => $value) {
            if (in_array($field, $model->db_fields, true)) {
                $where[] = $model->wpdb->prepare("{$field} = %s", $value);
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
        $result = $this->wpdb->update(
            $this->table,
            $data,
            $where
        );

        if ($this->wpdb->last_error) {
            throw new \RuntimeException("Update operation failed: " . $this->wpdb->last_error);
        }

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
        $result = $this->wpdb->replace(
            $this->table,
            $data,
        );

        if ($this->wpdb->last_error) {
            throw new \RuntimeException("Update operation failed: " . $this->wpdb->last_error);
        }

        // Save the ID of the last inserted row
        $this->last_id = $this->wpdb->insert_id;

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
     * Example:
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
        $filteredValues = array_intersect_key($values, array_flip($this->db_fields));

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
        foreach ($this->db_fields as $field) {
            if (property_exists($this, $field)) {
                $values[$field] = $this->$field;
            }
        }
        return $values;
    }

    /**
     * Executes a raw SQL query and returns the result.
     *
     * @param string $sql The raw SQL query to execute.
     *
     * @return Collection Database query results in a collection of BaseModel objects.
     *
     * @throws \RuntimeException Throws an exception if the SQL query fails.
     */
    public static function rawQuery(string $sql): Collection {
        global $wpdb;
        $model = new static($wpdb);

        $results = $model->wpdb->get_results($sql);

        if ($model->wpdb->last_error) {
            throw new \RuntimeException("Database query failed: " . $model->wpdb->last_error);
        }

        return $model->getCollectedResults($results);
    }


    /**
     * Fetches a single record from the table by its id.
     *
     * Example:
     * Fetches the history with the id of 1:
     *
     * ```php
     * $history = History::get(1);
     * ```
     * @param int $id The id of the record to fetch.
     *
     * @return BaseModel Returns an instance of the calling class that represents the fetched record.
     *
     * @throws \Exception Throws an exception if the SQL query fails or the record is not found.
     */
    public static function get(int $id): BaseModel {
        global $wpdb;
        $model = new static($wpdb);

        $sql = $model->wpdb->prepare("SELECT * FROM {$model->table} WHERE id = %d", $id);
        $result = $model->wpdb->get_row($sql, ARRAY_A);

        if (empty($result)) {
            throw new NoResultException("No record found with ID $id in {$model->table}");
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
     * Example:
     * If you want to get a user with the name "John Doe", you can use this method as follows:
     *
     * ```php
     * $user = User::getFrom(['name' => 'John Doe']);
     * ```
     *
     * @param array $conditions Data to search (in column => value pairs).
     * @return static An instance of the calling class with properties set to the values of the retrieved record.
     * @throws \InvalidArgumentException If the $conditions argument is not an array.
     * @throws NoResultException If no record matches the provided conditions.
     */
    public static function getFrom(array $conditions = []): BaseModel {
        if (empty($conditions)) {
            throw new \InvalidArgumentException("Invalid argument provided for conditions. Expected associative array.");
        }

        global $wpdb;
        $model = new static($wpdb); // Creates an instance of the derived class

        $whereClause = self::buildWhereClause($conditions, $model);
        $sql = $model->wpdb->prepare("SELECT * FROM {$model->table} WHERE {$whereClause}");

        $result = $model->wpdb->get_row($sql, ARRAY_A);

        if (empty($result)) {
            throw new NoResultException("No record found for : " . $sql);
        }

        $model->setFields($result);
        return $model;
    }

    /**
     * Fetches all records from the table that meet the provided conditions.
     *
     * @param array $conditions Data to search (in column => value pairs).
     *
     * @return Collection Returns an array of objects that are instances of the calling class, each representing a record in the table that meets the conditions.
     *
     * @throws \Exception Throws an exception if the SQL query fails.
     *
     * @example
     * // Fetches all histories that belong to the user with the id of 1
     * $histories = History::getAll(['user_id' => 1]);
     */
    public static function getAll(array $conditions = []): Collection {

        global $wpdb;
        $model = new static($wpdb); // Creates an instance of the derived class

        $whereClause = self::buildWhereClause($conditions, $model);
        $sql = $model->wpdb->prepare("SELECT * FROM {$model->table} WHERE {$whereClause}");

        $results = $model->wpdb->get_results($sql, ARRAY_A);

        return $model->getCollectedResults($results);
    }

    /**
     * Deletes the database record corresponding to this instance.
     *
     * This method uses the instance's id field to execute a delete operation on the corresponding database record.
     *
     * Example:
     * ```php
     * $history = History::get(123);  // Retrieve an instance from the database
     * $history->delete();            // Delete the corresponding database record
     * ```
     * @return bool true if the delete operation was successful, false otherwise
     */
    public function delete(): bool {
        if (isset($this->id)) {
            return $this->wpdb->delete($this->table, ['id' => $this->id]) > 0;
        }
        return false;
    }

    /**
     * Delete records matching provided conditions.
     *
     * @param array $conditions Data to search (in column => value pairs).
     *
     * @return int|false The number of rows updated, or false on error.
     */
    public static function deleteWhere(array $conditions): int {
        if (empty($conditions)) {
            throw new \InvalidArgumentException("Invalid argument provided for conditions. Expected associative array.");
        }

        global $wpdb;
        $model = new static($wpdb); // Creates an instance of the derived class

        $whereClause = self::buildWhereClause($conditions, $model);
        $sql = $model->wpdb->prepare("DELETE * FROM {$model->table} WHERE {$whereClause}");

        $result = $model->wpdb->query($sql);

        if ($model->wpdb->last_error) {
            throw new \RuntimeException("Delete operation failed: " . $model->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Performs a bulk update on a collection of models.
     *
     * ```php
     * // Example:
     * $histories = History::getAll(['user_id' => 1]);
     * $result = History::bulkSave($histories, ['is_paid' => true]);
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
        $model = new static($wpdb);
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
     * @return bool|int The number of rows updated, or false on error.
     *
     * @example
     * $history = new History();
     * $history->user_id = 1;
     * $history->order_id = 123;
     * $history->platform = "web";
     * $history->save();
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

