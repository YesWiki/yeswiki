<?php

namespace YesWiki\Test\Core\Migration;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * ActivityPub templates that wrote dates without a timezone write them as ISO 8601 with an offset.
 */
class GiveActivityPubDatesATimezoneTest extends YesWikiTestCase
{
    private array $formIds = [];

    protected function setUp(): void
    {
        $GLOBALS['wiki'] = $this->getWiki();
        require_once 'includes/migrations/20261020100000_GiveActivityPubDatesATimezone.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->formIds as $id) {
            $GLOBALS['wiki']->services->get(FormManager::class)->delete($id);
        }
    }

    private function formWith(string $template): string
    {
        $id = (string)$GLOBALS['wiki']->services->get(FormManager::class)->create([
            'bn_label_nature' => 'ActivityPub dates migration test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
            'bn_sem_template' => $template,
            'bn_sem_reverse_template' => '',
        ]);
        $this->formIds[] = $id;

        return $id;
    }

    private function template(string $id): string
    {
        $dbService = $GLOBALS['wiki']->services->get(DbService::class);

        return $dbService->loadSingle('SELECT `bn_sem_template` FROM ' . $dbService->prefixTable('nature') . " WHERE `bn_id_nature` = '" . $dbService->escape($id) . "'")['bn_sem_template'];
    }

    private function migrate(): void
    {
        $wiki = $GLOBALS['wiki'];
        $migration = new \GiveActivityPubDatesATimezone();
        $migration->setWiki($wiki);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    public function testDatesWithoutATimezoneGetAnOffset()
    {
        $id = $this->formWith(<<<'TWIG'
{
  "type": "Note",
  "name": {{ bf_titre | json_encode }},
  "published": {{ date_creation_fiche | replace({" ": "T"}) | json_encode }},
  "updated": {{ date_maj_fiche | replace({" ": "T"}) | json_encode }}
}
TWIG);

        $this->migrate();

        $this->assertSame(<<<'TWIG'
{
  "type": "Note",
  "name": {{ bf_titre | json_encode }},
  "published": {{ date_creation_fiche | date("c") | json_encode }},
  "updated": {{ date_maj_fiche | default(date_creation_fiche) | date("c") | json_encode }}
}
TWIG, $this->template($id));
    }

    public function testATemplateWithoutThoseDatesIsKept()
    {
        $template = '{"type": "Note", "published": {{ date_creation_fiche | date("c") | json_encode }}}';
        $id = $this->formWith($template);

        $this->migrate();

        $this->assertSame($template, $this->template($id));
    }
}
