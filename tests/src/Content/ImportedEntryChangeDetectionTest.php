<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Service\YesWikiToYesWikiImporter;

/**
 * What counts as a change to an already-imported entry.
 *
 * The importer used to call `EntryManager::update()` on every entry it had ever imported,
 * every run. That is not a slow no-op: an update saves a page revision and stamps
 * `updated_at`, so a wiki mirroring a few hundred entries every half hour grew a revision per
 * entry per run, reported changes that had not happened, and -- worst -- kept moving the very
 * "last modified" date that `allow_local` reads to tell whether a human edited an entry since
 * the last import.
 *
 * The comparison has to survive the two sides being differently typed: the remote answers
 * json (nulls, numbers, sometimes a list for a multi-valued field), storage holds strings with
 * multiple values comma-separated. A false difference here means the rewrite storm comes back,
 * which is why the flavours are enumerated rather than sampled.
 */
class ImportedEntryChangeDetectionTest extends TestCase
{
    public function testAnUnchangedEntryIsNotRewritten(): void
    {
        $stored = [
            'bf_titre' => 'Fête de la courge',
            'bf_description' => '<p>Venez</p>',
            'bf_code_postal' => '14100',
            'imagebf_image' => 'FeteCourge_imagebf_image_photo.jpg',
            'bf_site_internet' => '',
            'checkboxListeThemes' => 'a,b',
        ];
        $incoming = [
            'bf_titre' => 'Fête de la courge',
            'bf_description' => '<p>Venez</p>',
            'bf_code_postal' => 14100,              // json number vs stored string
            'imagebf_image' => 'FeteCourge_imagebf_image_photo.jpg',
            'bf_site_internet' => null,             // json null vs stored empty string
            'checkboxListeThemes' => ['a', 'b'],    // json list vs stored comma-separated
            'antispam' => 1,
        ];

        $this->assertSame([], $this->changedFields($stored, $incoming));
    }

    public function testOnlyTheFieldsThatMovedAreReported(): void
    {
        $stored = ['bf_titre' => 'Fête', 'bf_description' => '<p>Venez</p>', 'bf_ville' => 'Lisieux'];
        $incoming = ['bf_titre' => 'Fête', 'bf_description' => '<p>Venez donc</p>', 'bf_ville' => 'Caen'];

        // the log names them, so a field some other code reformats on save is visible rather
        // than just producing an entry that "always changes"
        $this->assertSame(['bf_description', 'bf_ville'], $this->changedFields($stored, $incoming));
    }

    public function testBookkeepingKeysAreNotContent(): void
    {
        $stored = ['bf_titre' => 'Fête', 'updated_at' => '2026-08-04 10:00:00'];
        $incoming = ['bf_titre' => 'Fête', 'updated_at' => '2026-08-04 18:30:00', 'antispam' => 1];

        // updated_at is what an update would write, and antispam only exists to pass
        // validate(): neither is a reason to rewrite an entry
        $this->assertSame([], $this->changedFields($stored, $incoming));
    }

    public function testANewFieldInTheMappingIsAChange(): void
    {
        $stored = ['bf_titre' => 'Fête'];
        $incoming = ['bf_titre' => 'Fête', 'bf_ville' => 'Lisieux'];

        $this->assertSame(['bf_ville'], $this->changedFields($stored, $incoming));
    }

    public function testAFieldTheMappingDoesNotCoverIsNoneOfOurBusiness(): void
    {
        // only the keys the importer is about to write are compared: a local-only field must
        // not make every entry look changed forever
        $stored = ['bf_titre' => 'Fête', 'bf_notes_internes' => 'écrit ici, pas là-bas'];
        $incoming = ['bf_titre' => 'Fête'];

        $this->assertSame([], $this->changedFields($stored, $incoming));
    }

    /**
     * changedFields() is private, and reachable no other way: constructing the importer means
     * a configured remote wiki to log into. It is a pure function of two arrays, so it is
     * called directly rather than mocked up to.
     *
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $incoming
     *
     * @return list<string>
     */
    private function changedFields(array $stored, array $incoming): array
    {
        $importer = (new \ReflectionClass(YesWikiToYesWikiImporter::class))->newInstanceWithoutConstructor();

        return (new \ReflectionMethod(YesWikiToYesWikiImporter::class, 'changedFields'))
            ->invoke($importer, $stored, $incoming);
    }
}
