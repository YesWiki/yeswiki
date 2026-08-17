<?php

namespace YesWiki\Test\Content\Service;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\IcalFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 11's stated acceptance test, written first: **a form whose date fields are not named `bf_date_debut_evenement` / `bf_date_fin_evenement` still produces correct iCal.**.
 */
class IcalFieldRolesTest extends YesWikiTestCase
{
    private const LABEL = 'IcalFieldRolesTest agenda';

    private static ?string $formId = null;
    private static ?string $entryTag = null;

    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        if (self::$entryTag !== null) {
            $wiki->services->get(EntryManager::class)->delete(self::$entryTag, true);
        }
        if (self::$formId !== null) {
            $wiki->services->get(FormManager::class)->delete(self::$formId);
        }
        self::$formId = null;
        self::$entryTag = null;
    }

    /**
     * A form with deliberately un-French, un-conventional date field names.
     *
     * @return array<string, mixed>
     */
    private function agendaFormWithUnconventionalFieldNames(): array
    {
        $wiki = $this->getWiki();
        $formManager = $wiki->services->get(FormManager::class);

        if (self::$formId === null) {
            $id = 9500;
            while ($formManager->getOne((string)$id) !== null) {
                $id++;
            }
            $this->assertSame(0, $formManager->create([
                'id' => (string)$id,
                'label' => self::LABEL,
                'entry_title_template' => '{{when_it_starts}}',
                'template' => [
                    ['type' => 'texte', 'name' => 'when_it_starts', 'label' => 'Nom'],
                    ['type' => 'listedatedeb', 'name' => 'kickoff', 'label' => 'Début'],
                    ['type' => 'listedatefin', 'name' => 'wrapup', 'label' => 'Fin'],
                ],
            ]));
            self::$formId = (string)$id;
        }

        $form = $formManager->getOne(self::$formId);
        $this->assertNotNull($form);

        return $form;
    }

    public function testTheFormDeclaresItsDateFieldsThroughRolesNotNames(): void
    {
        $form = $this->agendaFormWithUnconventionalFieldNames();

        $names = array_column($form['template'], 'name');
        $this->assertNotContains('bf_date_debut_evenement', $names, 'the point of the test is that this name is absent');
        $this->assertContains('kickoff', $names);
    }

    /**
     * The acceptance test itself: a real entry on that form exports as a VEVENT with the right start and end, read through the roles.
     */
    public function testIcalExportWorksWithoutTheConventionalFieldNames(): void
    {
        $wiki = $this->getWiki();
        $form = $this->agendaFormWithUnconventionalFieldNames();

        if (self::$entryTag === null) {
            $entry = $wiki->services->get(EntryManager::class)->create($form['id'], [
                'antispam' => 1,
                'when_it_starts' => 'Une conférence',
                'kickoff' => '2026-09-01T10:00:00+02:00',
                'wrapup' => '2026-09-01T17:00:00+02:00',
            ]);
            $this->assertNotEmpty($entry['tag'] ?? null, 'the entry should have been created');
            self::$entryTag = $entry['tag'];
        }

        $entryManager = $wiki->services->get(EntryManager::class);
        $stored = $entryManager->getOne(self::$entryTag);
        $this->assertIsArray($stored);

        $ical = $wiki->services->get(IcalFormatter::class)->formatToICAL([$stored], $form['id']);

        $this->assertStringContainsString('BEGIN:VEVENT', $ical, 'the entry must export as an event at all');
        $this->assertStringContainsString('DTSTART', $ical);
        $this->assertStringContainsString('DTEND', $ical);
        $this->assertMatchesRegularExpression('/DTSTART[^:]*:20260901T0800/', $ical, 'start time must survive the role lookup');
        $this->assertMatchesRegularExpression('/DTEND[^:]*:20260901T1500/', $ical, 'end time must survive the role lookup');
    }

    /**
     * The other half of the promise: a form that *does* use the historic French names -- every wiki seeded from the shipped Agenda form -- keeps working untouched, because the roles default from the field's type and those fields are `listedatedeb` / `listedatefin` regardless of what they are called.
     */
    public function testTheSeededFrenchNamedAgendaFormStillWorks(): void
    {
        $wiki = $this->getWiki();
        $agenda = current(array_filter(
            $wiki->services->get(FormManager::class)->getAll(),
            fn ($form) => in_array('bf_date_debut_evenement', array_column($form['template'] ?? [], 'name'), true)
        ));
        if ($agenda === false) {
            $this->markTestSkipped('this wiki has no French-named agenda form to check against');
        }

        $this->assertTrue(
            $wiki->services->get(IcalFormatter::class)->isICALForm($agenda),
            'the conventionally-named agenda form must still be recognised'
        );
    }

    /** The form is recognised as an agenda form by its roles, not by its field names. */
    public function testTheFormIsRecognisedAsAnIcalFormThroughRoles(): void
    {
        $form = $this->agendaFormWithUnconventionalFieldNames();

        $this->assertTrue(
            $this->getWiki()->services->get(IcalFormatter::class)->isICALForm($form),
            'a form with start/end date roles is an agenda form whatever its fields are called'
        );
    }
}
