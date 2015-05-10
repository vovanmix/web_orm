<?php

namespace Vovanmix\WebOrm\Tests;

require_once( __DIR__.'/../vendor/autoload.php' );

use Vovanmix\WebOrm\StringHelper;

class StringHelperTest extends \PHPUnit_Framework_TestCase
{

    public function testCamelize()
    {
        $StringHelper = new StringHelper();
        $result = $StringHelper->camelize('hello_world');
        $this->assertEquals('HelloWorld', $result);
    }

    public function testHumanize()
    {
        $StringHelper = new StringHelper();
        $result = $StringHelper->humanize('hello_world');
        $this->assertEquals('Hello World', $result);
    }

    public function testUnderscore()
    {
        $StringHelper = new StringHelper();
        $result = $StringHelper->underscore('HelloWorld');
        $this->assertEquals('hello_world', $result);
    }

    //buildConnectionString
}