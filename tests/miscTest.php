<?php

namespace Vovanmix\WebOrm\Tests;

use Vovanmix\WebOrm\ormPDOClass;

class miscTest extends \PHPUnit_Framework_TestCase
{

    public function testCamelize()
    {
        $ORM = new ormPDOClass([]);
        $result = $ORM::camelize('hello_world');
        $this->assertEquals('HelloWorld', $result);
    }

    public function testHumanize()
    {
        $ORM = new ormPDOClass([]);
        $result = $ORM::humanize('hello_world');
        $this->assertEquals('hello world', $result);
    }

    public function testUnderscore()
    {
        $ORM = new ormPDOClass([]);
        $result = $ORM::underscore('HelloWorld');
        $this->assertEquals('hello_world', $result);
    }
}