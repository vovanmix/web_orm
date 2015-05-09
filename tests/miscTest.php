<?php

namespace Vovanmix\WebOrm\Tests;

use Vovanmix\WebOrm\ormPDOClass;

class miscTest extends \PHPUnit_Framework_TestCase
{

    public function testCamelize()
    {
        $ORM = new ormPDOClass(['base' => 'test']);
        $result = $ORM::camelize('hello_world');
        $this->assertEquals('HelloWorld', $result);
    }

    public function testHumanize()
    {
        $ORM = new ormPDOClass(['base' => 'test']);
        $result = $ORM::humanize('hello_world');
        $this->assertEquals('Hello World', $result);
    }

    public function testUnderscore()
    {
        $ORM = new ormPDOClass(['base' => 'test']);
        $result = $ORM::underscore('HelloWorld');
        $this->assertEquals('hello_world', $result);
    }
}