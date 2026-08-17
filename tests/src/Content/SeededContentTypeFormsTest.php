<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Entity\ContentTypeSchema;

require_once 'tests/YesWikiTestCase.php';

/** The forms the installer seeds must match the schema they are supposed to instantiate. */
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

    /** ...and with the same labels, which is the half that broke. */
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
