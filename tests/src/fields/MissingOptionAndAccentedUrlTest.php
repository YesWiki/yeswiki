<?php

namespace YesWiki\Test\Core\Field;

require_once 'tests/YesWikiTestCase.php';

use YesWiki\Content\Field\FileField;
use YesWiki\Content\Field\RadioEntryField;
use YesWiki\Content\Field\SelectEntryField;
use YesWiki\Test\Core\YesWikiTestCase;

/** An entry linked to a vanished entry still renders, and a file field sees an accented address as an address. */
class MissingOptionAndAccentedUrlTest extends YesWikiTestCase
{
    /** @return array<int, string> */
    private function values(string $type, string $name): array
    {
        return array_replace(array_fill(0, 16, ''), [0 => $type, 1 => '1', 2 => 'Label', 3 => '', 6 => $name]);
    }

    /** @return iterable<string, array{string, class-string}> */
    public static function linkedEntryFields(): iterable
    {
        yield 'listefiche' => ['listefiche', SelectEntryField::class];
        yield 'radiofiche' => ['radiofiche', RadioEntryField::class];
    }

    /** @param class-string<SelectEntryField|RadioEntryField> $class */
    #[\PHPUnit\Framework\Attributes\DataProvider('linkedEntryFields')]
    public function testAValueNoLongerInTheOptionsIsShownAsItIs(string $type, string $class): void
    {
        $field = new $class($this->values($type, 'bf_lien'), $this->getWiki()->services);
        (new \ReflectionProperty($field, 'options'))->setValue($field, []);

        $html = (string)(new \ReflectionMethod($field, 'renderStatic'))->invoke($field, [$field->getPropertyName() => 'fiche-disparue']);

        $this->assertStringContainsString('fiche-disparue', $html);
    }

    public function testAnAccentedAddressIsAnAddress(): void
    {
        $field = new FileField(array_replace(array_fill(0, 16, ''), [0 => 'fichier', 1 => 'bf_doc', 2 => 'Doc']), $this->getWiki()->services);
        $isUrl = new \ReflectionMethod($field, 'isUrl');

        $this->assertTrue($isUrl->invoke($field, 'https://exemple.org/documents/réunion-été.pdf'));
        $this->assertTrue($isUrl->invoke($field, 'https://exemple.org/plain.pdf'));
        $this->assertFalse($isUrl->invoke($field, 'réunion.pdf'));
    }
}
