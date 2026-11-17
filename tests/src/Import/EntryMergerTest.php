<?php

namespace YesWiki\Test\Import;

use PHPUnit\Framework\TestCase;
use YesWiki\Import\Service\EntryMerger;

/** The three-way merge of imported entries, field by field. */
class EntryMergerTest extends TestCase
{
    private const PASS = ['antispam', 'created_at', 'updated_at'];

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, array{r: string, l: string}>
     */
    private static function imported(array $values): array
    {
        return EntryMerger::base($values, $values, self::PASS);
    }

    public function testUntouchedEntryTakesRemoteChanges(): void
    {
        $merge = EntryMerger::merge(
            ['bf_titre' => 'Fête', 'bf_lieu' => 'Lyon'],
            ['bf_titre' => 'Fête', 'bf_lieu' => 'Paris'],
            self::imported(['bf_titre' => 'Fête', 'bf_lieu' => 'Lyon']),
            EntryMerger::POLICY_LOCAL,
            self::PASS
        );
        $this->assertSame(['bf_titre' => 'Fête', 'bf_lieu' => 'Paris'], $merge['values']);
        $this->assertSame([], $merge['conflicts']);
    }

    public function testEditsOnDifferentFieldsAreBothKept(): void
    {
        $merge = EntryMerger::merge(
            ['bf_titre' => 'Grande fête', 'bf_lieu' => 'Lyon'],
            ['bf_titre' => 'Fête', 'bf_lieu' => 'Paris'],
            self::imported(['bf_titre' => 'Fête', 'bf_lieu' => 'Lyon']),
            EntryMerger::POLICY_LOCAL,
            self::PASS
        );
        $this->assertSame(['bf_titre' => 'Grande fête', 'bf_lieu' => 'Paris'], $merge['values']);
        $this->assertSame([], $merge['conflicts']);
    }

    public function testConflictFollowsPolicyAndIsReportedOnce(): void
    {
        $base = self::imported(['bf_lieu' => 'Lyon']);
        $local = ['bf_lieu' => 'Villeurbanne'];
        $remote = ['bf_lieu' => 'Paris'];

        $keepLocal = EntryMerger::merge($local, $remote, $base, EntryMerger::POLICY_LOCAL, self::PASS);
        $this->assertSame('Villeurbanne', $keepLocal['values']['bf_lieu']);
        $this->assertArrayHasKey('bf_lieu', $keepLocal['conflicts']);

        $again = EntryMerger::merge($local, $remote, $keepLocal['base'], EntryMerger::POLICY_LOCAL, self::PASS);
        $this->assertSame('Villeurbanne', $again['values']['bf_lieu']);
        $this->assertSame([], $again['conflicts']);

        $later = EntryMerger::merge($local, ['bf_lieu' => 'Marseille'], $keepLocal['base'], EntryMerger::POLICY_LOCAL, self::PASS);
        $this->assertArrayHasKey('bf_lieu', $later['conflicts']);

        $takeRemote = EntryMerger::merge($local, $remote, $base, EntryMerger::POLICY_REMOTE, self::PASS);
        $this->assertSame('Paris', $takeRemote['values']['bf_lieu']);
        $this->assertArrayHasKey('bf_lieu', $takeRemote['conflicts']);
    }

    public function testSameEditOnBothSidesIsNoConflict(): void
    {
        $merge = EntryMerger::merge(['bf_lieu' => 'Paris'], ['bf_lieu' => 'Paris'], self::imported(['bf_lieu' => 'Lyon']), EntryMerger::POLICY_LOCAL, self::PASS);
        $this->assertSame([], $merge['conflicts']);
    }

    public function testValueNormalizedOnSaveIsNotALocalEdit(): void
    {
        $base = EntryMerger::base(['checkboxListeTheme' => ['1', '2']], ['checkboxListeTheme' => '1,2 '], self::PASS);
        $merge = EntryMerger::merge(
            ['checkboxListeTheme' => '1,2 '],
            ['checkboxListeTheme' => ['1', '3']],
            $base,
            EntryMerger::POLICY_LOCAL,
            self::PASS
        );
        $this->assertSame(['1', '3'], $merge['values']['checkboxListeTheme']);
        $this->assertSame([], $merge['conflicts']);
    }

    public function testFieldWithoutBaseTakesRemoteAndPassKeysAreNotMerged(): void
    {
        $merge = EntryMerger::merge(
            ['bf_lieu' => 'Lyon', 'updated_at' => '2026-01-01'],
            ['bf_lieu' => 'Paris', 'updated_at' => '2026-02-01', 'antispam' => 1],
            [],
            EntryMerger::POLICY_LOCAL,
            self::PASS
        );
        $this->assertSame(['bf_lieu' => 'Paris', 'updated_at' => '2026-02-01', 'antispam' => 1], $merge['values']);
        $this->assertArrayNotHasKey('updated_at', $merge['base']);
    }

    public function testStoredValueIsRecordedForTakenFields(): void
    {
        $base = self::imported(['bf_lieu' => 'Lyon', 'bf_titre' => 'Fête']);
        $merge = EntryMerger::merge(
            ['bf_lieu' => 'Lyon', 'bf_titre' => 'Grande fête'],
            ['bf_lieu' => 'Paris', 'bf_titre' => 'Fête'],
            $base,
            EntryMerger::POLICY_LOCAL,
            self::PASS
        );
        $newBase = EntryMerger::storedInto($merge['base'], ['bf_lieu' => 'Paris ', 'bf_titre' => 'Grande fête'], $merge['taken']);
        $this->assertSame(EntryMerger::hash('Paris'), $newBase['bf_lieu']['l']);
        $this->assertSame($base['bf_titre'], $newBase['bf_titre']);
    }
}
