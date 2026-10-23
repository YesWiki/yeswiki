<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Core\Service\ExecutableFilename;

require_once 'includes/services/ExecutableFilename.php';

/**
 * A file the web server would run must be recognised whatever path or case it comes with.
 */
class ExecutableFilenameTest extends TestCase
{
    public static function executables(): array
    {
        return [['shell.php'], ['SHELL.PHP'], ['a.php7'], ['a.phtml'], ['a.phar'], ['a.shtml'], ['../../files/a.php'], ['a.php.'], ['.htaccess'], ['.user.ini'], ['dir\\a.pht']];
    }

    public static function harmless(): array
    {
        return [['photo.jpg'], ['a.php_'], ['a.php.png'], ['php'], ['notes.txt'], ['archive.zip']];
    }

    #[DataProvider('executables')]
    public function testAnExecutableNameIsRecognised(string $filename)
    {
        $this->assertTrue(ExecutableFilename::matches($filename));
    }

    #[DataProvider('harmless')]
    public function testAHarmlessNameIsLeftAlone(string $filename)
    {
        $this->assertFalse(ExecutableFilename::matches($filename));
    }
}
