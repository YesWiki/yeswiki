<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\FileManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The name an upload is stored under, and the names refused whatever the configuration allows. */
class UploadedFileNamesTest extends YesWikiTestCase
{
    /** @return array<string, array{string, string}> */
    public static function nameProvider(): array
    {
        return [
            'french accents' => ['café été.pdf', 'cafe_ete.pdf'],
            'cyrillic' => ['Отчёт.pdf', 'Otcet.pdf'],
            'japanese' => ['日本.png', 'Ri_Ben.png'],
            'emoji only' => ['😀.png', 'file.png'],
            'dotfile' => ['.htaccess', 'file.htaccess'],
            'surrounding spaces' => ['  rapport  final .docx', 'rapport_final.docx'],
            'several dots' => ['a.b.c.tar.gz', 'a.b.c.tar.gz'],
            'empty' => ['', 'file'],
        ];
    }

    #[DataProvider('nameProvider')]
    public function testAStoredNameKeepsWhatCanBeTransliterated(string $uploaded, string $stored): void
    {
        $this->assertSame($stored, $this->getWiki()->services->get(FileManager::class)->sanitizeFilename($uploaded));
    }

    /** @return array<string, array{string, bool}> */
    public static function executableProvider(): array
    {
        return [
            'php' => ['shell.php', true],
            'upper case' => ['shell.PHP', true],
            'php before an image extension' => ['shell.php.jpg', true],
            'trailing underscore' => ['shell.phtml_', true],
            'htaccess' => ['.htaccess', true],
            'user.ini' => ['.user.ini', true],
            'shell script' => ['install.sh', true],
            'domain in the name' => ['example.com.pdf', false],
            'perl-looking middle part' => ['photo.pl.jpg', false],
            'a pdf' => ['report.pdf', false],
            'no extension' => ['README', false],
        ];
    }

    #[DataProvider('executableProvider')]
    public function testExecutableNamesAreRecognised(string $filename, bool $executable): void
    {
        $this->assertSame($executable, FileManager::hasExecutableExtension($filename));
    }
}
