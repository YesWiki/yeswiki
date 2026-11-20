<?php

namespace YesWiki\Test\Bazar\Action;

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{bazarcarto}} places entries from the map field the form declares, whatever its name.
 */
class BazarCartoActionTest extends YesWikiTestCase
{
    private $wiki;
    private array $formIds = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
    }

    protected function tearDown(): void
    {
        foreach ($this->formIds as $id) {
            $this->wiki->services->get(FormManager::class)->delete($id);
        }
    }

    private function formWithMapField(?string $name): string
    {
        $lines = ['texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***'];
        if ($name !== null) {
            $lines[] = "map***$name***Lieu*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***";
        }
        $id = $this->wiki->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Carto test form',
            'bn_template' => implode("\n", $lines),
            'bn_condition' => '',
        ]);
        $this->formIds[] = $id;

        return (string)$id;
    }

    private function geolocationField(string $action): ?string
    {
        $html = $this->wiki->Format($action);
        preg_match('/geolocationfield&quot;:&quot;([a-z_0-9]+)&quot;/', $html, $m);

        return $m[1] ?? null;
    }

    public function testTheFormsMapFieldIsUsedWhenItIsNotNamedGeolocation()
    {
        $id = $this->formWithMapField('bf_localisation');

        $this->assertSame('bf_localisation', $this->geolocationField("{{bazarcarto id=\"$id\" dynamic=\"true\"}}"));
    }

    public function testAnExplicitParameterWins()
    {
        $id = $this->formWithMapField('bf_localisation');

        $this->assertSame('bf_other', $this->geolocationField("{{bazarcarto id=\"$id\" dynamic=\"true\" geolocationfield=\"bf_other\"}}"));
    }

    public function testAFormWithoutMapFieldFallsBackToGeolocation()
    {
        $id = $this->formWithMapField(null);

        $this->assertSame('bf_geolocation', $this->geolocationField("{{bazarcarto id=\"$id\" dynamic=\"true\"}}"));
    }
}
