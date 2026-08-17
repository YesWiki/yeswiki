<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use YesWiki\Import\Service\YesWikiToYesWikiImporter;

/** What counts as a change to an already-imported entry. */
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
            'bf_code_postal' => 14100,
            'imagebf_image' => 'FeteCourge_imagebf_image_photo.jpg',
            'bf_site_internet' => null,
            'checkboxListeThemes' => ['a', 'b'],
            'antispam' => 1,
        ];

        $this->assertSame([], $this->changedFields($stored, $incoming));
    }

    public function testOnlyTheFieldsThatMovedAreReported(): void
    {
        $stored = ['bf_titre' => 'Fête', 'bf_description' => '<p>Venez</p>', 'bf_ville' => 'Lisieux'];
        $incoming = ['bf_titre' => 'Fête', 'bf_description' => '<p>Venez donc</p>', 'bf_ville' => 'Caen'];

        $this->assertSame(['bf_description', 'bf_ville'], $this->changedFields($stored, $incoming));
    }

    public function testBookkeepingKeysAreNotContent(): void
    {
        $stored = ['bf_titre' => 'Fête', 'updated_at' => '2026-08-04 10:00:00'];
        $incoming = ['bf_titre' => 'Fête', 'updated_at' => '2026-08-04 18:30:00', 'antispam' => 1];

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
        $stored = ['bf_titre' => 'Fête', 'bf_notes_internes' => 'écrit ici, pas là-bas'];
        $incoming = ['bf_titre' => 'Fête'];

        $this->assertSame([], $this->changedFields($stored, $incoming));
    }

    /**
     * changedFields() is private, and reachable no other way: constructing the importer means a configured remote wiki to log into.
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
