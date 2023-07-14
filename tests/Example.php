<?php

/**
 * Created by PhpStorm.
 * User: roshan.summun
 * Date: 7/12/2023
 * Time: 11:14 AM
 */

class Example extends BaseModel
{
    protected static string $tableName = "mt_example";

    protected static array $oneToOne = [

        Meal => 'order_id',
    ];

    protected static array $oneToMany = [

        Staff => 'order_id',
    ];
}