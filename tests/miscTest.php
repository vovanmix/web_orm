<?php

namespace Vovanmix\WebOrm\Tests;

class miscTest extends \PHPUnit_Framework_TestCase
{

    public function testCamelize()
    {
        $ORM = new \Vovanmix\WebOrm\ormPDOClass(['base' => 'test']);
        $result = $ORM::camelize('hello_world');
        $this->assertEquals('HelloWorld', $result);
    }

    public function testHumanize()
    {
        $ORM = new \Vovanmix\WebOrm\ormPDOClass(['base' => 'test']);
        $result = $ORM::humanize('hello_world');
        $this->assertEquals('Hello World', $result);
    }

    public function testUnderscore()
    {
        $ORM = new \Vovanmix\WebOrm\ormPDOClass(['base' => 'test']);
        $result = $ORM::underscore('HelloWorld');
        $this->assertEquals('hello_world', $result);
    }
}