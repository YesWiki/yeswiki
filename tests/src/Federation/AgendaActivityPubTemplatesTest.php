<?php

namespace YesWiki\Test\Federation;

use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The default Agenda templates publish an entry as an Event Mobilizon accepts, and read Mobilizon events back into entries. */
class AgendaActivityPubTemplatesTest extends YesWikiTestCase
{
    private TemplateEngine $templateEngine;
    private string $timezone;

    protected function setUp(): void
    {
        parent::setUp();
        $wiki = $this->getWiki();
        $GLOBALS['wiki'] = $wiki;
        $this->templateEngine = $wiki->services->get(TemplateEngine::class);
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
        parent::tearDown();
    }

    /** @param array<string, mixed> $data */
    private function render(string $key, array $data): mixed
    {
        $form = json_decode((string)file_get_contents('src/Admin/starters/agenda.json'), true)['form'];

        return json_decode($this->templateEngine->renderSandboxedFromStringNoEscape($form[$key], $data), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function event(array $entry = []): array
    {
        return $this->render('sem_template', $entry + [
            'tag' => 'Bordeaux',
            'bf_titre' => 'Super événement à Bordeaux',
            'bf_description' => 'Un **grand** moment',
            'bf_date_debut_evenement' => '2024-04-10',
            'bf_date_fin_evenement' => '2024-04-12',
            'bf_site_internet' => 'https://vins.example/',
            'bf_adresse' => '1 place de la Bourse',
            'bf_code_postal' => '33000',
            'bf_ville' => 'Bordeaux',
            'bf_geolocation' => ['latitude' => '44.841225', 'longitude' => '-0.5800364', 'geometries' => ''],
            'imagebf_image' => 'Bordeaux_affiche.png',
            'created_at' => '2024-03-01 10:00:00',
            'updated_at' => '2024-03-02 11:00:00',
        ]);
    }

    public function testAllDayDatesGetATimeAndAnOffsetAndTheEndCoversTheLastDay(): void
    {
        $event = $this->event();

        $this->assertSame('2024-04-10T00:00:00+02:00', $event['startTime']);
        $this->assertSame('2024-04-12T23:59:59+02:00', $event['endTime']);
        $this->assertSame('Europe/Paris', $event['timezone']);
        $this->assertSame('2024-03-01T10:00:00+01:00', $event['published']);
    }

    public function testDatesWithATimeKeepIt(): void
    {
        $event = $this->event(['bf_date_debut_evenement' => '2024-05-30T18:00:00+02:00', 'bf_date_fin_evenement' => '2024-05-30T20:00:00+02:00']);

        $this->assertSame('2024-05-30T18:00:00+02:00', $event['startTime']);
        $this->assertSame('2024-05-30T20:00:00+02:00', $event['endTime']);
    }

    public function testAnEventWithoutEndDateEndsOnItsStartDay(): void
    {
        $event = $this->event(['bf_date_fin_evenement' => '']);

        $this->assertSame('2024-04-10T23:59:59+02:00', $event['endTime']);
    }

    public function testTheImageIsAMobilizonBannerAndTheWebsiteALink(): void
    {
        $event = $this->event();

        $this->assertSame('Document', $event['attachment'][0]['type']);
        $this->assertSame('Banner', $event['attachment'][0]['name']);
        $this->assertSame('image/png', $event['attachment'][0]['mediaType']);
        $this->assertStringEndsWith('Bordeaux_affiche.png', $event['attachment'][0]['url']);
        $this->assertSame(['type' => 'Link', 'name' => 'Website', 'mediaType' => 'text/html', 'href' => 'https://vins.example/'], $event['attachment'][1]);
    }

    public function testThePlaceHasFloatCoordinatesAndAPostalAddress(): void
    {
        $location = $this->event()['location'];

        $this->assertSame('1 place de la Bourse, 33000, Bordeaux', $location['name']);
        $this->assertSame(44.841225, $location['latitude']);
        $this->assertSame(-0.5800364, $location['longitude']);
        $this->assertSame('33000', $location['address']['postalCode']);
    }

    public function testAPlaceWithoutCoordinatesSendsNone(): void
    {
        $location = $this->event(['bf_geolocation' => ['latitude' => '', 'longitude' => '', 'geometries' => '']])['location'];

        $this->assertArrayNotHasKey('latitude', $location);
    }

    public function testAnEventWithNeitherPlaceNorImageNorWebsiteIsStillValid(): void
    {
        $event = $this->event(['bf_adresse' => '', 'bf_code_postal' => '', 'bf_ville' => '', 'imagebf_image' => '', 'bf_site_internet' => '']);

        $this->assertArrayNotHasKey('location', $event);
        $this->assertArrayNotHasKey('url', $event);
        $this->assertSame([], $event['attachment']);
    }

    public function testTheDescriptionIsSentAsHtml(): void
    {
        $this->assertStringContainsString('<strong>grand</strong>', $this->event()['content']);
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>
     */
    private function mobilizonEvent(array $changes = []): array
    {
        return array_replace([
            'type' => 'Event',
            'id' => 'https://mobilizon.example/events/5a5c6f3e-1b8e-4b5c-9a1e-2f2f8d1c0b11',
            'url' => 'https://mobilizon.example/events/5a5c6f3e-1b8e-4b5c-9a1e-2f2f8d1c0b11',
            'name' => 'Atelier réparation de vélos',
            'content' => '<p>Venez avec votre <strong>vélo</strong>.</p>',
            'startTime' => '2026-11-02T17:00:00Z',
            'endTime' => '2026-11-02T19:30:00Z',
            'timezone' => 'Europe/Paris',
            'published' => '2026-10-01T08:00:00Z',
            'updated' => '2026-10-02T08:00:00Z',
            'location' => [
                'type' => 'Place',
                'name' => 'La Cyclofficine',
                'address' => ['type' => 'PostalAddress', 'streetAddress' => '12 rue des Roues', 'postalCode' => '75011', 'addressLocality' => 'Paris', 'addressCountry' => 'France'],
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
            'attachment' => [
                ['type' => 'PropertyValue', 'propertyID' => 'mz:poi:accessibility', 'value' => 'oui'],
                ['type' => 'Document', 'mediaType' => 'image/jpeg', 'url' => 'https://mobilizon.example/media/banner.jpg', 'name' => 'Banner'],
                ['type' => 'Link', 'href' => 'https://cyclofficine.example/', 'mediaType' => 'text/html', 'name' => 'Website'],
            ],
        ], $changes);
    }

    public function testAMobilizonEventBecomesAnEntryInTheWikiTimezone(): void
    {
        $entry = $this->render('sem_reverse_template', $this->mobilizonEvent());

        $this->assertSame('Atelier réparation de vélos', $entry['bf_titre']);
        $this->assertSame('2026-11-02T18:00:00+01:00', $entry['bf_date_debut_evenement']);
        $this->assertSame('2026-11-02T20:30:00+01:00', $entry['bf_date_fin_evenement']);
        $this->assertSame('https://mobilizon.example/media/banner.jpg', $entry['imagebf_image']);
        $this->assertSame('https://cyclofficine.example/', $entry['bf_site_internet']);
        $this->assertSame('12 rue des Roues', $entry['bf_adresse']);
        $this->assertSame('75011', $entry['bf_code_postal']);
        $this->assertSame('Paris', $entry['bf_ville']);
        $this->assertSame(['latitude' => '48.8566', 'longitude' => '2.3522'], $entry['bf_geolocation']);
        $this->assertSame('2026-10-01 10:00:00', $entry['created_at']);
        $this->assertStringContainsString('**vélo**', $entry['bf_description']);
    }

    public function testWithoutWebsiteTheEventPageIsTheLink(): void
    {
        $event = $this->mobilizonEvent();
        unset($event['attachment'][2]);

        $entry = $this->render('sem_reverse_template', $event);

        $this->assertSame($event['url'], $entry['bf_site_internet']);
    }

    public function testAPlaceGivenAsTextIsKeptAsTheAddress(): void
    {
        $entry = $this->render('sem_reverse_template', $this->mobilizonEvent(['location' => 'Salle des fêtes']));

        $this->assertSame('Salle des fêtes', $entry['bf_adresse']);
        $this->assertSame('', $entry['bf_ville']);
    }

    public function testAnythingButAnEventIsTurnedDown(): void
    {
        $this->assertNull($this->render('sem_reverse_template', ['type' => 'Note', 'id' => 'https://mobilizon.example/comments/1', 'content' => '<p>Super !</p>']));
    }

    public function testAnEventPublishedByOneWikiIsReadBackByAnother(): void
    {
        $entry = $this->render('sem_reverse_template', $this->event());

        $this->assertSame('Super événement à Bordeaux', $entry['bf_titre']);
        $this->assertSame('2024-04-10T00:00:00+02:00', $entry['bf_date_debut_evenement']);
        $this->assertSame('https://vins.example/', $entry['bf_site_internet']);
        $this->assertSame('Bordeaux', $entry['bf_ville']);
        $this->assertStringEndsWith('Bordeaux_affiche.png', $entry['imagebf_image']);
    }
}
