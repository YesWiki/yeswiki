<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Field\LinkField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A link field starts empty, and a bare scheme left in it is not saved as an address. */
class LinkFieldDefaultTest extends YesWikiTestCase
{
    private function field(): LinkField
    {
        $services = $this->getWiki()->services;
        $positional = $services->get(FormManager::class)->templateToPositionalList([
            ['type' => 'lien_internet', 'name' => 'bf_site', 'label' => 'Site'],
        ])[0];
        $field = $services->get(FieldFactory::class)->create($positional);
        $this->assertInstanceOf(LinkField::class, $field);

        return $field;
    }

    public function testANewEntryShowsAnEmptyAddress(): void
    {
        $field = $this->field();

        $input = $field->renderInputIfPermitted([]);

        $this->assertEmpty($field->getDefault());
        $this->assertMatchesRegularExpression('/<input\s[^>]*value=""[^>]*name="bf_site"/s', $input);
        $this->assertStringContainsString('placeholder="https://"', $input);
    }

    public function testABareSchemeIsNotSaved(): void
    {
        $field = $this->field();

        $this->assertSame(['bf_site' => null], $field->formatValuesBeforeSave(['bf_site' => 'http://']));
        $this->assertSame(['bf_site' => null], $field->formatValuesBeforeSave(['bf_site' => 'https://']));
        $this->assertSame(['bf_site' => 'https://example.org'], $field->formatValuesBeforeSave(['bf_site' => 'https://example.org']));
    }
}
