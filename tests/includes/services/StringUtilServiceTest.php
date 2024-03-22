<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\TestCase;
use YesWiki\Core\Service\StringUtilService;

require_once 'includes/services/StringUtilService.php';

class StringUtilServiceTest extends TestCase
{
    /**
     * @dataProvider folderToNamespaceProvider
     */
    public function testFolderToNamespace(string $input, string $expected)
    {
        $this->assertEquals(
            $expected,
            StringUtilService::folderToNamespace($input),
            'Unable to convert : ' . $input
        );
    }

    public function folderToNamespaceProvider()
    {
        return [
            ['', ''],
            ['.', ''],
            ['foo', 'Foo'],
            ['Foo', 'Foo'],
            ['foo1', 'Foo1'],
            ['foO', 'Foo'],
            ['foo.bar', 'FooBar'],
            ['foo-bar', 'FooBar'],
            ['foo_bar', 'FooBar'],
            ['foo~bar', 'FooBar'],
        ];
    }
}
