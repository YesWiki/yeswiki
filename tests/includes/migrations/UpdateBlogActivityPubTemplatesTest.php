<?php

namespace YesWiki\Test\Core\Migration;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Forms still on a shipped Blog-actu ActivityPub template get the new one and a content warning field; an edited template is left alone.
 */
class UpdateBlogActivityPubTemplatesTest extends YesWikiTestCase
{
    private const FIELDS = "texte***bf_titre***Titre***80***255*** *** ***text***1*** *** *** * *** * *** *** *** ***\r\ntextelong***bf_chapeau***Résumé***40***3*** *** ***wiki*** *** *** *** * *** * *** *** *** ***\r\ntextelong***bf_description***Billet***40***9*** *** ***wiki***1*** *** *** * *** * *** *** *** ***\r\n";

    private array $formIds = [];

    protected function setUp(): void
    {
        $GLOBALS['wiki'] = $this->getWiki();
        require_once 'includes/migrations/20261020110000_UpdateBlogActivityPubTemplates.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->formIds as $id) {
            $GLOBALS['wiki']->services->get(FormManager::class)->delete($id);
        }
    }

    private function original(string $constant): string
    {
        return (new \ReflectionClassConstant(\UpdateBlogActivityPubTemplates::class, $constant))->getValue();
    }

    private function formWith(string $template, string $reverseTemplate, string $fields = self::FIELDS): string
    {
        $id = (string)$GLOBALS['wiki']->services->get(FormManager::class)->create([
            'bn_label_nature' => 'Blog migration test form',
            'bn_template' => $fields,
            'bn_condition' => '',
            'bn_sem_template' => $template,
            'bn_sem_reverse_template' => $reverseTemplate,
        ]);
        $this->formIds[] = $id;

        return $id;
    }

    private function form(string $id): array
    {
        $dbService = $GLOBALS['wiki']->services->get(DbService::class);

        return $dbService->loadSingle('SELECT `bn_template`, `bn_sem_template`, `bn_sem_reverse_template` FROM ' . $dbService->prefixTable('nature') . " WHERE `bn_id_nature` = '" . $dbService->escape($id) . "'");
    }

    private function migrate(): void
    {
        $wiki = $GLOBALS['wiki'];
        $migration = new \UpdateBlogActivityPubTemplates();
        $migration->setWiki($wiki);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    public static function shippedProvider(): array
    {
        return [
            'the released templates' => ['RELEASED_TEMPLATE', 'RELEASED_REVERSE_TEMPLATE'],
            'the templates with a lead' => ['LEAD_TEMPLATE', 'LEAD_REVERSE_TEMPLATE'],
            'the templates with a lead and dates' => ['LEAD_TEMPLATE', 'DATED_REVERSE_TEMPLATE'],
        ];
    }

    #[DataProvider('shippedProvider')]
    public function testShippedTemplatesAreReplacedAndTheFieldAddedAfterTheSummary(string $template, string $reverseTemplate)
    {
        $id = $this->formWith(str_replace("\n", "\r\n", $this->original($template)), $this->original($reverseTemplate) . "\n");

        $this->migrate();

        $form = $this->form($id);
        $this->assertSame(trim(file_get_contents('tools/bazar/templates/activitypub/blog-note.json.twig')), $form['bn_sem_template']);
        $this->assertSame(trim(file_get_contents('tools/bazar/templates/activitypub/blog-note.reverse.json.twig')), $form['bn_sem_reverse_template']);
        $lines = explode("\r\n", $form['bn_template']);
        $this->assertStringStartsWith('textelong***bf_chapeau***', $lines[1]);
        $this->assertStringStartsWith('texte***bf_avertissement***', $lines[2]);
        $this->assertStringStartsWith('textelong***bf_description***', $lines[3]);
    }

    public function testRunningTwiceAddsTheFieldOnce()
    {
        $id = $this->formWith($this->original('LEAD_TEMPLATE'), $this->original('DATED_REVERSE_TEMPLATE'));

        $this->migrate();
        $this->migrate();

        $this->assertSame(1, substr_count($this->form($id)['bn_template'], 'bf_avertissement'));
    }

    public function testAnEditedTemplateIsKept()
    {
        $edited = str_replace('"Note"', '"Article"', $this->original('LEAD_TEMPLATE'));
        $id = $this->formWith($edited, '{"bf_titre": {{ name | json_encode }}}');

        $this->migrate();

        $this->assertSame(['bn_template' => self::FIELDS, 'bn_sem_template' => $edited, 'bn_sem_reverse_template' => '{"bf_titre": {{ name | json_encode }}}'], $this->form($id));
    }
}
