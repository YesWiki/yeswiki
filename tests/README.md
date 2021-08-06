# Instructions to use phpunit

## Description

Phpunit is a framework useful to perform automatic tests on php code.
THe whole documentation is available here : https://phpunit.de and https://phpunit.de/documentation.html.

## Usage

1. in command line, go to root directory.
2. ensure phpunit is installed with command `composer install`
3. start test with command `./vendor/bin.phpunit tests`

## Create a test

1. if the tested class or function is in file `./tools/bazar/actions/BazarAction.php`, create the file `./tests/tools/bazar/actions/BazarActionTest.php`
2. in file, inspire from this code
```
<?php

namespace YesWiki\Test\Bazar\Actions;

use PHPUnit\Framework\TestCase;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Wiki;

class MyClassTest extends TestCase
{
    
    /**
     */
    public function testMyFunction()
    {
        $this->assertTrue(true/*... to modify*/);
    }
}
```

3. Define your test as the following code to use wiki object.
```
public function testMyFunction()
{
    require_once 'includes/YesWikiLoader.php';
    $wiki = YesWikiLoader::getWiki();
    $this->assertTrue(true/*... to modify*/);
}
```
4. to use dataprovider, define code as :
```
/**
    * @dataProvider provider
    * @covers MyClass::myFunction
    */
public function testMyFunction($a, $b, $expected)
{
    ...
}

public function provider()
{
    return [
        'first' => ['a','c',true],
        'second' => ['bd','c',true],
        'third' => ['a','g',true],
    ];
}
```

THe annotation `@covers MyClass::myFunction` allows to define code coverage by tests.