<?php

namespace YesWiki\Test\Bazar\Actions;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The agenda template shows each event with its start day and month, its image whether a file or a link, and a link to the entry.
 */
class AgendaTemplateTest extends YesWikiTestCase
{
    private $wiki;
    private $entryManager;
    private $formManager;
    private string $formId;
    private array $tags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->wiki->services->get(AuthController::class)->logout();

        $this->formId = $this->formManager->create([
            'bn_label_nature' => 'Agenda template test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'texte***bf_date_debut_evenement***Début***60***255*** *** ***text*** *** *** *** * *** * *** *** *** ***',
                'image***bf_image***Image***400***300***1200***900***right*** *** *** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
        ]);

        $this->tags['remote'] = $this->create('Visite <b>grotte</b>', '2026-10-22T23:00:00+02:00', 'https://images.example/banner.jpg?name=AK1A2449.jpg');
        $this->tags['plain'] = $this->create('Soirée karaoké', '2026-07-03T20:00:00+02:00');
    }

    protected function tearDown(): void
    {
        foreach ($this->tags as $tag) {
            $this->wiki->services->get(TripleStore::class)->deleteAll($tag, '');
            if ($this->entryManager->isEntry($tag)) {
                $this->entryManager->delete($tag, true);
            }
            $this->wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            $this->wiki->services->get(AclService::class)->delete($tag);
        }
        $this->formManager->delete($this->formId);
    }

    private function create(string $title, string $start, string $image = ''): string
    {
        $entry = $this->entryManager->create($this->formId, [
            'antispam' => 1,
            'bf_titre' => $title,
            'bf_date_debut_evenement' => $start,
            'imagebf_image' => $image,
        ]);
        $this->wiki->services->get(AclService::class)->save($entry['id_fiche'], 'read', '*');

        return $entry['id_fiche'];
    }

    private function render(string $params = ''): string
    {
        return $this->wiki->Format('{{bazarliste id="' . $this->formId . '" template="agenda"' . $params . '}}');
    }

    private function figure(string $html, string $tag): string
    {
        preg_match('#<figure[^>]*data-id_fiche="' . $tag . '".*?</figure>#s', $html, $match);

        return $match[0] ?? '';
    }

    public function testTheTwigTemplateShowsTheStartDayAndMonth()
    {
        $html = $this->render();

        $this->assertStringContainsString('data-template="agenda.twig"', $html);
        $this->assertMatchesRegularExpression('#<span class="day">22</span>\s*<span class="month">Oct</span>#', $this->figure($html, $this->tags['remote']));
        $this->assertMatchesRegularExpression('#<span class="day">03</span>\s*<span class="month">Juil</span>#', $this->figure($html, $this->tags['plain']));
    }

    public function testAnImageGivenAsALinkIsShown()
    {
        $figure = $this->figure($this->render(), $this->tags['remote']);

        $this->assertMatchesRegularExpression('#<img loading="lazy" class="img-responsive" alt="" src="[^"]+">#', $figure);
        $this->assertStringNotContainsString('src=""', $figure);
        $this->assertStringNotContainsString('files/https', $figure);
    }

    public function testAnEventWithoutImageGetsThePlaceholder()
    {
        $this->assertStringContainsString('img-placeholder', $this->figure($this->render(), $this->tags['plain']));
    }

    public function testTheTitleIsEscapedAndLinksToTheEntry()
    {
        $figure = $this->figure($this->render(), $this->tags['remote']);

        $this->assertStringContainsString('<h5>Visite &lt;b&gt;grotte&lt;/b&gt;</h5>', $figure);
        $this->assertMatchesRegularExpression('#<a class=""\s+title="Visite grotte"\s+href="[^"]*' . $this->tags['remote'] . '"#', $figure);
    }

    public function testTheModalOptionOpensTheEntryInABox()
    {
        $this->assertStringContainsString('<a class="modalbox"', $this->figure($this->render(' modal="1"'), $this->tags['remote']));
    }

    public function testTheColumnCountSetsTheWidth()
    {
        $this->assertStringContainsString('width: 50%;', $this->render(' nbcol="2"'));
        $this->assertStringContainsString('width: 25%;', $this->render(' nbcol="x"'));
    }
}
