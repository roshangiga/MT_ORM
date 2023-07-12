<?php
/**
 * Created by PhpStorm.
 * User: roshan.summun
 * Date: 7/12/2023
 * Time: 1:39 PM
 */

use PHPUnit\Framework\TestCase;
use roshangiga\Collection;

class CollectionTest extends TestCase
{
    /**
     * @var Collection
     */
    private Collection $collection;

    protected function setUp(): void {
        $this->collection = new Collection([
                                               ['name' => 'John', 'score' => 30],
                                               ['name' => 'Jane', 'score' => 25],
                                               ['name' => 'Bob', 'score' => 35],
                                           ]);
    }

    public function testFirst(): void {
        $firstItem = $this->collection->first();

        $this->assertIsArray($firstItem, "First item should be an array");
        $this->assertEquals(['name' => 'John', 'score' => 30], $firstItem, "First item does not match expected array");

    }

    public function testLast(): void {
        $lastItem = $this->collection->last();

        $this->assertIsArray($lastItem, "Last item should be an array");
        $this->assertEquals(['name' => 'Bob', 'score' => 35], $lastItem, "Last item does not match expected array");
    }

    public function testIsEmpty(): void {
        $this->assertFalse($this->collection->isEmpty());
    }

    public function testCount(): void {
        $this->assertEquals(3, $this->collection->count());
    }

    public function testMap(): void {
        $mapped = $this->collection->map(function ($item) {
            return $item['score'];
        });

        $this->assertEquals([30, 25, 35], $mapped->getArrayCopy(), "Mapped items do not match expected array");
    }

    public function testFilter(): void {
        $filtered = $this->collection->filter(function ($item) {
            return $item['score'] > 30;
        });

        $this->assertEquals([2 => ['name' => 'Bob', 'score' => 35]], $filtered->getArrayCopy(), "Filtered items do not match expected array");
    }

    public function testSortBy(): void {
        $sorted = $this->collection->sortBy(function ($item) {
            return $item['score'];
        });

        $this->assertEquals(new Collection([
                                               ['name' => 'Jane', 'score' => 25],
                                               ['name' => 'John', 'score' => 30],
                                               ['name' => 'Bob', 'score' => 35],
                                           ]), $sorted);
    }

    public function testSortByDesc(): void {
        $sortedDesc = $this->collection->sortByDesc(function ($item) {
            return $item['score'];
        });

        $this->assertEquals(new Collection([
                                               ['name' => 'Bob', 'score' => 35],
                                               ['name' => 'John', 'score' => 30],
                                               ['name' => 'Jane', 'score' => 25],
                                           ]), $sortedDesc);
    }

    public function testReduce(): void {
        $sum = $this->collection->reduce(function ($carry, $item) {
            return $carry + $item['score'];
        }, 0);

        $this->assertEquals(90, $sum);
    }

    public function testPluck(): void {
        $names = $this->collection->pluck('name');
        $this->assertEquals(new Collection(['John', 'Jane', 'Bob']), $names);
    }

    public function testToJson(): void {
        $json = $this->collection->toJson();
        $this->assertEquals('[{"name":"John","score":30},{"name":"Jane","score":25},{"name":"Bob","score":35}]', $json);
    }


    protected function tearDown(): void
    {
        unset($this->collection);
    }
}