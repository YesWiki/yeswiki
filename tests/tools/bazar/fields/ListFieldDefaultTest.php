<?php

namespace YesWiki\Test\Bazar\Field;

use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A list field only defaults to keys its list offers, and renders a value the list does not offer as the value itself.
 */
class ListFieldDefaultTest extends YesWikiTestCase
{
    private $wiki;
    private string $listId;
    private string $formId;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->listId = $this->wiki->services->get(ListManager::class)->create('List field default test list', [
            ['id' => 'inconnu', 'label' => 'Inconnu'],
            ['id' => 'connu', 'label' => 'Connu'],
        ], 'ListFieldDefault' . bin2hex(random_bytes(4)));
        $this->formId = $this->wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'List field default test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'radio***' . $this->listId . '***Statut*** *** ***Inconnu***bf_wrong_case*** ***0*** *** *** * *** * *** *** *** ***',
                'liste***' . $this->listId . '***Statut*** *** ***connu***bf_valid*** ***0*** *** *** * *** * *** *** *** ***',
                'checkbox***' . $this->listId . '***Statuts*** *** ***connu,Autre***bf_partly*** ***0*** *** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(FormManager::class)->delete($this->formId);
        $this->wiki->services->get(PageManager::class)->deleteOrphaned($this->listId);
        $this->wiki->services->get(TripleStore::class)->delete($this->listId, TripleStore::TYPE_URI, null, '', '');
    }

    private function form(): array
    {
        return $this->wiki->services->get(FormManager::class)->getOne($this->formId);
    }

    private function field(string $name): EnumField
    {
        foreach ($this->form()['prepared'] as $field) {
            if ($field instanceof EnumField && $field->getPropertyName() === $name) {
                return $field;
            }
        }
        $this->fail("no list field $name");
    }

    private function renderWithoutWarnings(EnumField $field, array $entry): string
    {
        $warnings = [];
        set_error_handler(function ($errno, $message) use (&$warnings) {
            $warnings[] = $message;

            return true;
        }, E_WARNING);
        try {
            $html = $field->renderStaticIfPermitted($entry);
        } finally {
            restore_error_handler();
        }
        $this->assertSame([], $warnings);

        return $html;
    }

    public function testDefaultThatIsNotAKeyIsIgnored()
    {
        $field = $this->field('bf_wrong_case');

        $this->assertNull($field->getDefault());
        $this->assertSame(['Inconnu'], $field->ignoredDefault());
        $this->assertSame('', $this->renderWithoutWarnings($field, []));
    }

    public function testDefaultThatIsAKeyIsKept()
    {
        $field = $this->field('bf_valid');

        $this->assertSame('connu', $field->getDefault());
        $this->assertSame([], $field->ignoredDefault());
    }

    public function testCheckboxDefaultKeepsOnlyItsKeys()
    {
        $field = $this->field('bf_partly');

        $this->assertSame('connu', $field->getDefault());
        $this->assertSame(['Autre'], $field->ignoredDefault());
    }

    public function testFormManagerNamesTheFieldsWithIgnoredDefaults()
    {
        $this->assertSame(
            ['bf_wrong_case : Inconnu', 'bf_partly : Autre'],
            $this->wiki->services->get(FormManager::class)->invalidListDefaults($this->form())
        );
    }

    public function testRecordedValueTheListNoLongerOffersRendersAsItself()
    {
        $html = $this->renderWithoutWarnings($this->field('bf_wrong_case'), ['bf_wrong_case' => 'retire']);

        $this->assertStringContainsString('retire', $html);
    }
}
