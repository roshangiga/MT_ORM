<?php
/**
 * Created by PhpStorm.
 * User: Roshan Summun ( roshangiga@gmail.com )
 * Date: 6/5/2023
 * Time: 11:59 AM
 */


use ArrayObject;

class Collection extends ArrayObject
{
    public function __construct(array $items = []) {
        parent::__construct($items);
    }

    public function first() {
        return reset($this->array);
    }

    public function last() {
        return end($this->array);
    }

    public function isEmpty() {
        return $this->count() === 0;
    }


    public function count() {
        return count($this->getArrayCopy());
    }

    /**
     * Applies a callback to all items in the collection and returns a new collection with the results.
     *
     * @param callable $callback
     * @return self
     *
     * @example $squares = $collection->map(fn($n) => $n ** 2); // Returns a new Collection with [1, 4, 9]
     */
    public function map(callable $callback) {
        return new static(array_map($callback, $this->getArrayCopy()));
    }

    public function filter(callable $callback) {
        return new static(array_filter($this->getArrayCopy(), $callback));
    }

    public function reduce(callable $callback, $initial = null) {
        return array_reduce($this->getArrayCopy(), $callback, $initial);
    }

    public function pluck(string $field) {
        return array_map(function ($item) use ($field) {
            return is_object($item) ? $item->$field : (is_array($item) ? $item[$field] : null);
        }, $this->getArrayCopy());
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
