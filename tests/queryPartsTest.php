<?php

namespace Vovanmix\WebOrm\Tests;

use Vovanmix\WebOrm\ormPDOClass;

class queryPartsTest extends \PHPUnit_Framework_TestCase
{

    public function testBuildFields()
    {
        $ORM = new ormPDOClass([]);

//        $stack = array();
//        $this->assertEquals(0, count($stack));
//
//        array_push($stack, 'foo');
//        $this->assertEquals('foo', $stack[count($stack)-1]);
//        $this->assertEquals(1, count($stack));
//
//        $this->assertEquals('foo', array_pop($stack));
//        $this->assertEquals(0, count($stack));
    }

    public function testBuildJoins()
    {

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