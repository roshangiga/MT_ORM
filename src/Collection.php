<?php
/**
 * Created by PhpStorm.
 * User: roshan.summun
 * Date: 5/30/2023
 * Time: 9:54 PM
 */
namespace roshangiga;

use ArrayObject;

class Collection extends ArrayObject
{
    public function __construct(array $items = []) {
        parent::__construct($items);
    }

    /**
     * Returns the first item from the collection.
     *
     * @return mixed The first item from the collection.
     */
    public function first() {
        $array = $this->getArrayCopy();
        return reset($array);
    }

    /**
     * Returns the last item from the collection.
     *
     * @return mixed The last item from the collection.
     */
    public function last() {
        $array = $this->getArrayCopy();
        return end($array);
    }

    /**
     * Checks if the collection is empty.
     *
     * @return bool True if the collection is empty, false otherwise.
     */
    public function isEmpty(): bool {
        return $this->count() === 0;
    }


    /**
     * Counts the number of items in the collection.
     *
     * @return int The number of items in the collection.
     */
    public function count(): int {
        return count($this->getArrayCopy());
    }

    /**
     * Applies a callback to all items in the collection and returns a new collection with the results.
     *
     * **Example:**
     * ```
     *  $collection = new Collection([
     *          ['name' => 'John', 'score' => 30],
     *          ['name' => 'Jane', 'score' => 25],
     *          ['name' => 'Bob', 'score' => 35]
     *      ]);
     *  $filteredCollection = $collection->filter(fn($n) => $n['score'] += 10);
     * // Result: [  ['name' => 'Bob', 'score' => 40], ['name' => 'Jane', 'score' => 35], ['name' => 'Bob', 'score' => 45]
     * ```
     *
     * @param callable $callback A callback function to apply to each item in the collection.
     * @return self A new collection with the results.
     */
    public function map(callable $callback) {
        return new static(array_map($callback, $this->getArrayCopy()));
    }

    /**
     * Filters the collection using a callback function and returns a new collection with the filtered items.
     *
     * **Example:**
     * ```
     *  $collection = new Collection([
     *          ['name' => 'John', 'score' => 30],
     *          ['name' => 'Jane', 'score' => 25],
     *          ['name' => 'Bob', 'score' => 35]
     *      ]);
     *  $filteredCollection = $collection->filter(fn($n) => $n['score'] > 30);
     * // Result: [  ['name' => 'Bob', 'score' => 35]  ]
     * ```
     *
     * @param callable $callback A callback function to use for filtering the items.
     * @return self A new collection with the filtered items.
     */
    public function filter(callable $callback): Collection {
        return new static(array_filter($this->getArrayCopy(), $callback));
    }

    /**
     * Returns a new collection with the items in the collection sorted by the result of the callback.
     *
     * **Example:**
     * ```
     *  $collection = new Collection([
     *          ['name' => 'John', 'score' => 30],
     *          ['name' => 'Jane', 'score' => 25],
     *          ['name' => 'Bob', 'score' => 35]
     *      ]);
     *  $sorted = $collection->sortBy(fn($n) => $n['score']);
     * ```
     *
     * @param callable $callback A callback function to use for sorting the items.
     * @return self A new collection with the sorted items.
     */
    public function sortBy(callable $callback): Collection {
        $items = $this->getArrayCopy();
        usort($items, function ($a, $b) use ($callback) {
            return $callback($a) <=> $callback($b);
        });
        return new static($items);
    }

    /**
     * Returns a new collection with the items sorted in descending order based on the results of a callback function.
     *
     * The callback function should take one parameter and return a value that will be used for sorting.
     *
     * **Example:**
     * ```
     *  $collection = new Collection([
     *          ['name' => 'John', 'score' => 30],
     *          ['name' => 'Jane', 'score' => 25],
     *          ['name' => 'Bob', 'score' => 35]
     *      ]);
     *  $sortedReverse = $collection->sortByDesc(fn($n) => $n['score']);
     * ```
     * @param callable $callback
     * @return self A new collection with the sorted items.
     *
     */
    public function sortByDesc(callable $callback) {
        $items = $this->getArrayCopy();
        usort($items, function ($a, $b) use ($callback) {
            return $callback($b) <=> $callback($a);
        });
        return new static($items);
    }

    /**
     * Reduces the collection to a single value, applying a callback function.
     *
     * * This function applies a callback to the items in the collection.
     * * The callback should accept two parameters: the carrying value and the current item value.
     * * It should return the new carrying value.
     *
     *  **Example:**
     * ```
     * $collection = new Collection([
     *          ['name' => 'John', 'score' => 30],
     *          ['name' => 'Jane', 'score' => 25],
     *          ['name' => 'Bob', 'score' => 35]
     *      ]);
     * $sum = $collection->reduce(fn ($carry, $item) => $carry + $item['score'], 0);
     * // $sum will be 90
     * ```
     * @param callable $callback The callback function.
     * @param mixed $initial The initial value to start reducing from. If not provided, it defaults to NULL.
     * @return mixed The reduced value.
     *
     */
    public function reduce(callable $callback, $initial = null) {
        return array_reduce($this->getArrayCopy(), $callback, $initial);
    }


    /**
     * Plucks a specified field from each item in the collection and returns a new collection containing only these values.
     *
     * * If an item is an object, it plucks the value of the property with the given field name.
     * * If an item is an array, it plucks the value at the index with the given field name.
     *
     * **Example:**
     * ```
     * $collection = new Collection([
     *     ['name' => 'John', 'age' => 30],
     *     ['name' => 'Jane', 'age' => 25],
     *     ['name' => 'Bob', 'age' => 35]
     * ]);
     *
     * $names = $collection->pluck('age'); // // Prints: Array ( [0] => 30 [1] => 25 [2] => 35 )
     * ```
     *
     * @param string $field The field to pluck from each item.
     * @return self A new collection with the sorted items.
     */
    public function pluck(string $field): Collection {
        $result = array_map(function ($item) use ($field) {
            return is_object($item) ? $item->$field : (is_array($item) ? $item[$field] : null);
        }, $this->getArrayCopy());

        return new static($result);
    }

    public function toJson() {
        return json_encode($this->getArrayCopy());
    }

    /**
     * Convert the Collection to its string representation.
     * This example will output all elements as a JSON string.
     *
     * @return string
     */
    public function __toString(): string {
        $list = [];
        foreach ($this as $model) {
            $list[] = $model->getFields();
        }
        return json_encode($list);
    }


    /**
     * Save all items in the collection.
     *
     * @return void
     */
    public function save(): void {

        foreach ($this as $item) {
            // Check if the item is an instance of BaseModel or its child class
            if ($item instanceof BaseModel) {
                $item->save();
            }
        }
    }

}