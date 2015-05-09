<?php

namespace Vovanmix\WebOrm\Tests;

use Vovanmix\WebOrm\ormPDOClass;

class queryPartsTest extends \PHPUnit_Framework_TestCase
{

    public function testBuildFields()
    {
        $ORM = new ormPDOClass(['base' => 'test']);

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
    }

    public function testBuildJoins()
    {
        $ORM = new ormPDOClass(['base' => 'test']);

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

    }
}