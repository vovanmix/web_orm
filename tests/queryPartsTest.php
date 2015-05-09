<?php

namespace Vovanmix\WebOrm\Tests;

require_once( __DIR__.'/../vendor/autoload.php' );

use Vovanmix\WebOrm\ormPDOClass;

class queryPartsTest extends \PHPUnit_Framework_TestCase
{

    public function testFillDefaultSettings(){

        $ORM = new ormPDOClass(NULL, true);

        $val = $ORM::fillDefaultSettings(['fields' => [
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


        $val = $ORM::fillDefaultSettings([]);
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

    public function testBuildFields()
    {
        $ORM = new ormPDOClass(NULL, true);

        $sql = $ORM::buildFields([
            'name',
            'last' => 'LastName',
            'CONCAT(street, zip)' => 'address'
        ]);
        $expectedSql = 'name,last as LastName,CONCAT(street, zip) as address';
        $this->assertEquals($expectedSql, $sql);


        $sql2 = $ORM::buildFields(NULL);
        $expectedSql = '*';
        $this->assertEquals($expectedSql, $sql2);

        $sql = $ORM::buildFields([]);
        $expectedSql = '*';
        $this->assertEquals($expectedSql, $sql);
    }

    public function testBuildJoins()
    {
        $ORM = new ormPDOClass(NULL, true);

        $sql = $ORM::buildJoins([
            [
                'users', [
                    ['users.city', '=', 1],
                    ['parent', '.=', 'parent.id']
                ]
            ]
        ]);
        $expectedSql = ' LEFT JOIN `users` ON users.city = 1  AND parent = parent.id ';
        $this->assertEquals($expectedSql, $sql);

        $sql = $ORM::buildJoins([]);
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
        $ORM = new ormPDOClass(NULL, true);

        $val = $ORM::prepare(" 'MyName' is hello ");
        $expectedVal = "'`MyName` is hello'";
        $this->assertEquals($expectedVal, $val);

        $val2 = $ORM::prepare(['hello']);
        $expectedVal2 = "'hello'";
        $this->assertEquals($expectedVal2, $val2);

        $val3 = $ORM::prepare(1);
        $expectedVal3 = "'1'";
        $this->assertEquals($expectedVal3, $val3);

        $val = $ORM::prepare(0);
        $expectedVal = "0";
        $this->assertEquals($expectedVal, $val);

        $val = $ORM::prepare('0');
        $expectedVal = "0";
        $this->assertEquals($expectedVal, $val);

        $val = $ORM::prepare('');
        $expectedVal = '""';
        $this->assertEquals($expectedVal, $val);

        $val = $ORM::prepare(NULL);
        $expectedVal = 'NULL';
        $this->assertEquals($expectedVal, $val);

        $val = $ORM::prepare(false);
        $expectedVal = 0;
        $this->assertEquals($expectedVal, $val);

        $val = $ORM::prepare(true);
        $expectedVal = "'1'";
        $this->assertEquals($expectedVal, $val);
    }
}