<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\StringUtilService;

class StringUtilServiceTest extends TestCase
{
    #[DataProvider('folderToNamespaceProvider')]
    public function testFolderToNamespace(string $input, string $expected): void
    {
        $this->assertEquals(
            $expected,
            StringUtilService::folderToNamespace($input),
            'Unable to convert : ' . $input
        );
    }

    /**
     * @return list<array{string, string}>
     */
    public static function folderToNamespaceProvider(): array
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
