<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A form on a template Doryphore or a starter shipped gets the starter's current one; a hand-written template only stops reading the retired date keys. */
class ActivityPubTemplatesSpeakMastodonAndMobilizonTest extends YesWikiTestCase
{
    private const DORYPHORE_BLOG = <<<'TWIG'
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Note",
  "name": {{ bf_titre | json_encode }},
  "preview": {{ bf_chapeau | default("") | json_encode }},
  "content": {{ bf_description | default("") | json_encode }},
  "published": {{ date_creation_fiche | date("c") | json_encode }},
  "updated": {{ date_maj_fiche | default(date_creation_fiche) | date("c") | json_encode }},
  "attachment": [
    {% if imagebf_image %}
    {
      "type": "Image",
      "url": {{ fileUrl(imagebf_image) | json_encode }}
    }
    {% endif %}
  ]
}
TWIG;

    private const DORYPHORE_BLOG_REVERSE = <<<'TWIG'
{
  "bf_titre": {{ name | json_encode }},
  "bf_chapeau": {{ preview | default("") | json_encode }},
  "bf_description": {{ content | default("") | json_encode }},
  "imagebf_image": {{ (attachment | first).url | default("") | json_encode }}
}
TWIG;

    private const DORYPHORE_AGENDA_REVERSE = <<<'TWIG'
{
  "bf_titre": {{ name | json_encode }},
  "bf_description": {{ content | default("") | json_encode }},
  "bf_date_debut_evenement": {{ startTime | json_encode }},
  "bf_date_fin_evenement": {{ endTime | default("") | json_encode }},
  "bf_adresse": {{ location.address.streetAddress | default("") | json_encode }},
  "bf_code_postal": {{ location.address.postalCode | default("") | json_encode }},
  "bf_ville": {{ location.address.addressLocality | default("") | json_encode }},
  "bf_site_internet": {{ url | default("") | json_encode }},
  "imagebf_image": {{ (attachment | first).url | default("") | json_encode }},
  "geolocation": {
    "bf_latitude": {{ location.latitude | default("") | json_encode }},
    "bf_longitude": {{ location.longitude | default("") | json_encode }}
  }
}
TWIG;

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261021100000_ActivityPubTemplatesSpeakMastodonAndMobilizon.php';
    }

    /** @return array<string, array<string, mixed>> */
    private function starters(): array
    {
        return [
            'agenda' => json_decode((string)file_get_contents('src/Admin/starters/agenda.json'), true)['form'],
            'blog' => json_decode((string)file_get_contents('src/Admin/starters/blog.json'), true)['form'],
        ];
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    private function upgrade(array $form): array
    {
        return \ActivityPubTemplatesSpeakMastodonAndMobilizon::upgrade($form, $this->starters());
    }

    /** @return list<array<string, string>> */
    private function blogFields(): array
    {
        return [
            ['type' => 'texte', 'name' => 'bf_titre'],
            ['type' => 'textelong', 'name' => 'bf_chapeau'],
            ['type' => 'textelong', 'name' => 'bf_description'],
        ];
    }

    public function testADoryphoreBlogGetsTheStarterTemplatesAndAContentWarningAfterItsSummary(): void
    {
        $form = $this->upgrade(['sem_template' => self::DORYPHORE_BLOG, 'sem_reverse_template' => self::DORYPHORE_BLOG_REVERSE, 'template' => $this->blogFields()]);

        $this->assertSame($this->starters()['blog']['sem_template'], $form['sem_template']);
        $this->assertSame($this->starters()['blog']['sem_reverse_template'], $form['sem_reverse_template']);
        $this->assertSame(['bf_titre', 'bf_chapeau', 'bf_avertissement', 'bf_description'], array_column($form['template'], 'name'));
    }

    public function testAShippedTemplateIsRecognisedWithWindowsLineEndingsAndBeforeTheDateFix(): void
    {
        $template = str_replace(['date_creation_fiche | date("c")', 'date_maj_fiche | default(date_creation_fiche) | date("c")', "\n"], ['date_creation_fiche | replace({" ": "T"})', 'date_maj_fiche | replace({" ": "T"})', "\r\n"], self::DORYPHORE_BLOG);

        $form = $this->upgrade(['sem_template' => $template, 'template' => $this->blogFields()]);

        $this->assertSame($this->starters()['blog']['sem_template'], $form['sem_template']);
    }

    public function testAContentWarningFieldAlreadyThereIsNotAddedTwice(): void
    {
        $fields = array_merge($this->blogFields(), [['type' => 'texte', 'name' => 'bf_avertissement']]);

        $form = $this->upgrade(['sem_template' => self::DORYPHORE_BLOG, 'template' => $fields]);

        $this->assertSame($fields, $form['template']);
    }

    public function testAnAgendaReadsAndWritesItsOwnMapField(): void
    {
        $form = $this->upgrade(['sem_reverse_template' => self::DORYPHORE_AGENDA_REVERSE, 'template' => [['type' => 'map', 'name' => 'bf_latitude']]]);

        $this->assertSame(str_replace('bf_geolocation', 'bf_latitude', $this->starters()['agenda']['sem_reverse_template']), $form['sem_reverse_template']);
        $this->assertStringNotContainsString('bf_geolocation', $form['sem_reverse_template']);
    }

    public function testAHandWrittenTemplateOnlyStopsReadingTheRetiredDateKeys(): void
    {
        $template = '{"type": "Note", "summary": {{ bf_titre | json_encode }}, "published": {{ date_creation_fiche | replace({" ": "T"}) | json_encode }}, "updated": {{ date_maj_fiche | json_encode }}}';
        $fields = $this->blogFields();

        $form = $this->upgrade(['sem_template' => $template, 'template' => $fields]);

        $this->assertSame('{"type": "Note", "summary": {{ bf_titre | json_encode }}, "published": {{ created_at | date("c") | json_encode }}, "updated": {{ updated_at | json_encode }}}', $form['sem_template']);
        $this->assertSame($fields, $form['template']);
    }

    public function testAFormWithoutTemplatesIsLeftAlone(): void
    {
        $form = ['id' => '5', 'sem_template' => '', 'template' => $this->blogFields()];

        $this->assertSame($form, $this->upgrade($form));
    }
}
