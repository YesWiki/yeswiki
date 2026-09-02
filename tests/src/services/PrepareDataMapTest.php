<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\TemplateDataFactory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A map over a form lists that form's own location field, whatever it is called. */
class PrepareDataMapTest extends YesWikiTestCase
{
    private ?string $formId = null;

    protected function tearDown(): void
    {
        if ($this->formId !== null) {
            $this->getWiki()->services->get(FormManager::class)->delete($this->formId);
        }
        parent::tearDown();
    }

    public function testTheGeolocationFieldComesFromTheFormsFieldRoles(): void
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $id = 9750;
        while ($formManager->getOne((string)$id) !== null) {
            $id++;
        }
        $this->assertSame(0, $formManager->create([
            'id' => (string)$id,
            'label' => 'PrepareDataMapTest',
            'entry_title_template' => '{{bf_titre}}',
            'template' => [
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'map', 'name' => 'bf_latitude', 'label' => 'bf_longitude'],
            ],
        ]));
        $this->formId = (string)$id;

        $prepared = $this->getWiki()->services->get(TemplateDataFactory::class)->prepare('map', ['id' => (string)$id, 'template' => 'map']);

        $this->assertSame('bf_latitude', $prepared['geolocationfield'], 'the map looks for coordinates under a name the form does not use');
    }

    public function testAnExplicitFieldStillWins(): void
    {
        $prepared = $this->getWiki()->services->get(TemplateDataFactory::class)->prepare('map', ['id' => '1', 'template' => 'map', 'geolocationfield' => 'bf_ailleurs']);

        $this->assertSame('bf_ailleurs', $prepared['geolocationfield']);
    }
}
