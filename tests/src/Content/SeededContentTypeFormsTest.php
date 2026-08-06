<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Entity\ContentTypeSchema;

require_once 'tests/YesWikiTestCase.php';

/**
 * The forms the installer seeds must match the schema they are supposed to instantiate.
 *
 * `ContentTypeSchema::LOCKED` is the definition of a built-in Content type's fields; the SQL
 * seed writes a copy of it as JSON. Two hand-maintained copies of one thing, and they have
 * drifted twice now:
 *
 *  - ticket 25 found the seed's field *set* out of step with the schema;
 *  - and the seed labelled the Pages form's `content` field "Contenu" where the schema says
 *    the label is empty. A page renders through its form (ticket 10) and a field renders its
 *    label, so **every page in every wiki grew a "Contenu" caption over its own prose** --
 *    reported from a real instance, reproduced on a fresh install, and invisible to every
 *    test because nothing compared the two copies.
 *
 * A string comparison against the seed file: no wiki, no database.
 */
class SeededContentTypeFormsTest extends TestCase
{
    private const SEED = __DIR__ . '/../../../templates/installation-default-content.sql.twig';

    /**
     * @return array<string, array{string}>
     */
    public static function builtInTypeProvider(): array
    {
        $cases = [];
        foreach (ContentTypeSchema::types() as $type) {
            if (ContentTypeSchema::isBuiltIn($type)) {
                $cases[$type] = [$type];
            }
        }

        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('builtInTypeProvider')]
    public function testTheSeededFormDeclaresExactlyWhatTheSchemaDoes(string $type): void
    {
        $seeded = $this->seededTemplate($type);
        $this->assertNotNull($seeded, "no form with content_type \"{$type}\" is seeded");

        $expected = ContentTypeSchema::lockedFieldNames($type);
        $actual = array_values(array_filter(array_map(
            fn ($field) => is_array($field) ? ($field['name'] ?? null) : null,
            $seeded
        )));

        $this->assertSame(
            $expected,
            $actual,
            "The {$type} form seeded by the installer does not declare the fields "
            . 'ContentTypeSchema::LOCKED does, in that order.'
        );
    }

    /**
     * ...and with the same labels, which is the half that broke.
     *
     * A label is not decoration here: an empty one is how `templates/layouts/field.twig`
     * is told to render the value alone, which is what makes a page's prose render as prose
     * rather than as a captioned field.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('builtInTypeProvider')]
    public function testTheSeededFormUsesTheSchemasLabels(string $type): void
    {
        $seeded = $this->seededTemplate($type);
        $this->assertNotNull($seeded);

        $wrong = [];
        foreach ($seeded as $field) {
            $name = is_array($field) ? ($field['name'] ?? null) : null;
            if (!is_string($name)) {
                continue;
            }
            $expected = ContentTypeSchema::lockedField($type, $name)['label'] ?? null;
            if ($expected !== null && ($field['label'] ?? null) !== $expected) {
                $wrong[] = sprintf(
                    '%s: seeded "%s", schema says "%s"',
                    $name,
                    $field['label'] ?? '',
                    $expected
                );
            }
        }

        $this->assertSame([], $wrong, "The seeded {$type} form disagrees with ContentTypeSchema.");
    }

    /**
     * The `template` array of the seeded form for this content type.
     *
     * @return list<mixed>|null
     */
    private function seededTemplate(string $type): ?array
    {
        $sql = (string)file_get_contents(self::SEED);

        // the seed writes each form's body as a single-quoted SQL literal, with '' for a
        // literal quote -- undoubled here so the JSON parses
        preg_match_all("~'(\{\"id\".*?\})',~s", $sql, $matches);
        foreach ($matches[1] as $literal) {
            $body = json_decode(str_replace("''", "'", $literal), true);
            if (is_array($body) && ($body['content_type'] ?? null) === $type) {
                return is_array($body['template'] ?? null) ? array_values($body['template']) : [];
            }
        }

        return null;
    }
}
