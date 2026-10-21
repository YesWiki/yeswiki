<?php

namespace YesWiki\Test\Core\Migration;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Forms still on the previous Agenda ActivityPub templates get the ones that handle map coordinates; a template someone edited is left alone.
 */
class UpdateAgendaActivityPubCoordinatesTest extends YesWikiTestCase
{
    private array $formIds = [];

    protected function setUp(): void
    {
        $GLOBALS['wiki'] = $this->getWiki();
        require_once 'includes/migrations/20261020120000_UpdateAgendaActivityPubCoordinates.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->formIds as $id) {
            $GLOBALS['wiki']->services->get(FormManager::class)->delete($id);
        }
    }

    private function original(string $constant): string
    {
        return (new \ReflectionClassConstant(\UpdateAgendaActivityPubCoordinates::class, $constant))->getValue();
    }

    private function formWith(string $template, string $reverseTemplate): string
    {
        $id = (string)$GLOBALS['wiki']->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Agenda migration test form',
            'bn_template' => 'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
            'bn_condition' => '',
            'bn_sem_template' => $template,
            'bn_sem_reverse_template' => $reverseTemplate,
        ]);
        $this->formIds[] = $id;

        return $id;
    }

    private function templates(string $id): array
    {
        $dbService = $GLOBALS['wiki']->services->get(DbService::class);

        return $dbService->loadSingle('SELECT `bn_sem_template`, `bn_sem_reverse_template` FROM ' . $dbService->prefixTable('nature') . " WHERE `bn_id_nature` = '" . $dbService->escape($id) . "'");
    }

    private function migrate(): void
    {
        $wiki = $GLOBALS['wiki'];
        $migration = new \UpdateAgendaActivityPubCoordinates();
        $migration->setWiki($wiki);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    public function testThePreviousTemplatesAreReplacedEvenWithWindowsLineEndings()
    {
        $id = $this->formWith(
            str_replace("\n", "\r\n", $this->original('PREVIOUS_TEMPLATE')),
            $this->original('PREVIOUS_REVERSE_TEMPLATE') . "\n"
        );

        $this->migrate();

        $this->assertSame([
            'bn_sem_template' => trim(file_get_contents('tools/bazar/templates/activitypub/agenda-event.json.twig')),
            'bn_sem_reverse_template' => trim(file_get_contents('tools/bazar/templates/activitypub/agenda-event.reverse.json.twig')),
        ], $this->templates($id));
    }

    public function testAnEditedTemplateIsKept()
    {
        $edited = str_replace('"Event"', '"Event", "category": "ARTS"', $this->original('PREVIOUS_TEMPLATE'));
        $id = $this->formWith($edited, '{"bf_titre": {{ name | json_encode }}}');

        $this->migrate();

        $this->assertSame(['bn_sem_template' => $edited, 'bn_sem_reverse_template' => '{"bf_titre": {{ name | json_encode }}}'], $this->templates($id));
    }
}
