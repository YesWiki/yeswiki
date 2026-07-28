<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 26: form templates are stored as JSON arrays of named-attribute field objects
 * ("the prepared json"); the historical positional `***` syntax is only ever READ
 * (old revisions, imports from older wikis) and re-encoded to JSON on every write.
 * Attribute keys derive from the FIELD_* constants of the handling field class, so the
 * codec is by construction in sync with what the field constructors consume.
 */
class FormTemplateJsonTest extends YesWikiTestCase
{
    private const LEGACY_TEMPLATE = "texte***bf_titre***Nom***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\n"
        . "textelong***bf_description***Description***40***5*** *** ***wiki***0*** *** *** * *** * *** *** *** ***\r\n"
        . "checkbox***ListeType***Type de ressource*** *** *** ***bf_type*** ***1*** *** *** * *** * *** *** *** ***\r\n"
        . "unknowntype***bf_mystere***Mystère***13*** *** ***\r\n";

    public function testAttributeMapsDeriveFromFieldConstants()
    {
        $fieldFactory = $this->getWiki()->services->get(FieldFactory::class);

        // base map (texte adds pattern/sub_type/placeholder over BazarField's slots)
        $this->assertSame(
            [1 => 'name', 2 => 'label', 3 => 'size', 4 => 'max_chars', 5 => 'default',
                6 => 'pattern', 7 => 'sub_type', 8 => 'required', 9 => 'searchable',
                10 => 'hint', 11 => 'read_access', 12 => 'write_access', 15 => 'placeholder'],
            $fieldFactory->getAttributeIndexToKeyMap('texte')
        );

        // subclass redeclaration wins by index (TextareaField FIELD_NUM_ROWS = 4) and the
        // redeclared name moves with it (FIELD_MAX_CHARS = 6)
        $textarea = $fieldFactory->getAttributeIndexToKeyMap('textelong');
        $this->assertSame('num_rows', $textarea[4]);
        $this->assertSame('max_chars', $textarea[6]);
        $this->assertSame('syntax', $textarea[7]);

        // subclass redeclaration wins by name (EnumField FIELD_NAME = 6 retires slot 1
        // as 'name'; slot 1 is FIELD_LINKED_OBJECT)
        $checkbox = $fieldFactory->getAttributeIndexToKeyMap('checkbox');
        $this->assertSame('linked_object', $checkbox[1]);
        $this->assertSame('name', $checkbox[6]);

        // unknown types have no map
        $this->assertSame([], $fieldFactory->getAttributeIndexToKeyMap('unknowntype'));
    }

    public function testLegacySyntaxEncodesToNamedJson()
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $json = $formManager->normalizeTemplate(self::LEGACY_TEMPLATE);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(4, $decoded);

        $this->assertSame(
            ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Nom', 'size' => '60',
                'max_chars' => '255', 'sub_type' => 'text', 'required' => '1',
                'read_access' => '*', 'write_access' => '*'],
            $decoded[0]
        );

        $this->assertSame('textelong', $decoded[1]['type']);
        $this->assertSame('5', $decoded[1]['num_rows']);
        $this->assertSame('wiki', $decoded[1]['syntax']);
        $this->assertArrayNotHasKey('max_chars', $decoded[1]);

        $this->assertSame('ListeType', $decoded[2]['linked_object']);
        $this->assertSame('bf_type', $decoded[2]['name']);

        // unknown type: positions round-trip as numeric string keys, losslessly
        $this->assertSame(
            ['type' => 'unknowntype', '1' => 'bf_mystere', '2' => 'Mystère', '3' => '13'],
            $decoded[3]
        );
    }

    public function testJsonAndLegacyParseToTheSamePositionalArrays()
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $fromLegacy = $formManager->parseTemplate(self::LEGACY_TEMPLATE);
        $fromJson = $formManager->parseTemplate($formManager->normalizeTemplate(self::LEGACY_TEMPLATE));

        $this->assertSame($fromLegacy, $fromJson);
        // and the constructors' contract holds: 16 padded positional slots per field
        $this->assertCount(16, $fromJson[0]);
        $this->assertSame('texte', $fromJson[0][0]);
        $this->assertSame('bf_titre', $fromJson[0][1]);
        $this->assertSame('text', $fromJson[0][7]);
    }

    public function testJsonEncodingIsStable()
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $json = $formManager->normalizeTemplate(self::LEGACY_TEMPLATE);
        // normalizing already-canonical JSON is the identity
        $this->assertSame($json, $formManager->normalizeTemplate($json));
    }

    public function testCreateStoresTemplateAsNativeJsonArrayInPageBody()
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $formId = '999908';

        try {
            // legacy `***` input (the import path) is converted on create ...
            $formManager->create([
                'id' => $formId,
                'label' => 'FormTemplateJsonTest form',
                'template' => self::LEGACY_TEMPLATE,
                'condition' => '',
            ]);

            $form = $formManager->getOne($formId);
            $this->assertIsArray($form);

            // ... the page body stores a NATIVE json array (no string-in-string double
            // encoding) ...
            $page = $pageManager->getOne($form['tag'], null, true, true);
            $body = json_decode($page['body'], true);
            $this->assertIsArray($body['template']);
            $this->assertCount(4, $body['template']);
            $this->assertSame('texte', $body['template'][0]['type']);
            $this->assertSame('bf_titre', $body['template'][0]['name']);

            // ... and the in-memory form carries the same native array (ticket 27: the
            // positional arrays are internal to prepareData(), never on the form)
            $this->assertIsArray($form['template']);
            $this->assertSame($body['template'], $form['template']);
            $this->assertSame('bf_titre', $form['template'][0]['name']);
        } finally {
            if ($formManager->getOne($formId)) {
                $formManager->delete($formId);
            }
        }
    }

    public function testEmptyTemplateNormalizesToEmptyJsonArray()
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        $this->assertSame('[]', $formManager->normalizeTemplate(''));
        $this->assertSame([], $formManager->parseTemplate('[]'));
        $this->assertSame([], $formManager->parseTemplate(''));
    }
}
