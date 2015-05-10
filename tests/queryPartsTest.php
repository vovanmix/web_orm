<?php

namespace Vovanmix\WebOrm\Tests;

require_once( __DIR__.'/../vendor/autoload.php' );

use Vovanmix\WebOrm\QueryBuilder;

class queryPartsTest extends \PHPUnit_Framework_TestCase
{

    public function testFillDefaultSettings(){

        $val = QueryBuilder::fillDefaultSettings(['fields' => [
            'name',
            'last' => 'LastName',
            'CONCAT(street, zip)' => 'address'
        ]]);
        $expectedVal = [
            'fields' => [
                'name',
                'last' => 'LastName',
                'CONCAT(street, zip)' => 'address'
            ],
            'joins' => [],
            'conditions' => [],
            'group' => '',
            'having' => [],
            'order' => [],
            'limit' => '',
        ];
        $this->assertEquals($expectedVal, $val);


        $val = QueryBuilder::fillDefaultSettings([]);
        $expectedVal = [
            'fields' => [],
            'joins' => [],
            'conditions' => [],
            'group' => '',
            'having' => [],
            'order' => [],
            'limit' => '',
        ];
        $this->assertEquals($expectedVal, $val);


    }

    public function testBuildFields(){

        $sql = QueryBuilder::buildFields([
            'name',
            'last' => 'LastName',
            'CONCAT(street, zip)' => 'address'
        ]);
        $expectedSql = 'name,last as LastName,CONCAT(street, zip) as address';
        $this->assertEquals($expectedSql, $sql);


        $sql2 = QueryBuilder::buildFields(NULL);
        $expectedSql = '*';
        $this->assertEquals($expectedSql, $sql2);

        $sql = QueryBuilder::buildFields([]);
        $expectedSql = '*';
        $this->assertEquals($expectedSql, $sql);
    }

    public function testBuildJoins(){

        $sql = QueryBuilder::buildJoins([
            [
                'users', [
                    ['users.city', '=', 1],
                    ['parent', '.=', 'parent.id']
                ]
            ]
        ]);
        $expectedSql = '  LEFT JOIN `users` ON users.city = 1  AND parent = parent.id ';
        $this->assertEquals($expectedSql, $sql);

        $sql = QueryBuilder::buildJoins([]);
        $expectedSql = '';
        $this->assertEquals($expectedSql, $sql);
    }

    public function testBuildHaving()
    {

    }

    public function testBuildOrder()
    {

    }

    public function testBuildConditions(){

    }

    public function testPrepare(){

        $val = QueryBuilder::prepare(" 'MyName' is hello ");
        $expectedVal = "'`MyName` is hello'";
        $this->assertEquals($expectedVal, $val);

        $val2 = QueryBuilder::prepare(['hello']);
        $expectedVal2 = "'hello'";
        $this->assertEquals($expectedVal2, $val2);

        $val3 = QueryBuilder::prepare(1);
        $expectedVal3 = "'1'";
        $this->assertEquals($expectedVal3, $val3);

        $val = QueryBuilder::prepare(0);
        $expectedVal = "0";
        $this->assertEquals($expectedVal, $val);

        $val = QueryBuilder::prepare('0');
        $expectedVal = "0";
        $this->assertEquals($expectedVal, $val);

        $val = QueryBuilder::prepare('');
        $expectedVal = '""';
        $this->assertEquals($expectedVal, $val);

        $val = QueryBuilder::prepare(NULL);
        $expectedVal = 'NULL';
        $this->assertEquals($expectedVal, $val);

        $val = QueryBuilder::prepare(false);
        $expectedVal = 0;
        $this->assertEquals($expectedVal, $val);

        $val = QueryBuilder::prepare(true);
        $expectedVal = "'1'";
        $this->assertEquals($expectedVal, $val);
    }
}