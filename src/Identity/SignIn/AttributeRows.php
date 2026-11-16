<?php

namespace YesWiki\Identity\SignIn;

use YesWiki\Identity\Service\SignIn\ProvidersEditor;

/**
 * The attributes a provider sent, one row each, as the test screen and the sign-in detail of the logs show them.
 * Every value comes from the provider: the templates escape all of it.
 */
final class AttributeRows
{
    /**
     * @param array<array-key, mixed> $attributes   what the provider sent
     * @param array<array-key, mixed> $attributeUse which attribute fed which field, from a SignInPlan or read back from a log row
     *
     * @return list<array{name: string, values: list<string>, usedAs: list<string>}>
     */
    public static function of(array $attributes, array $attributeUse): array
    {
        $rows = [];
        foreach ($attributes as $name => $value) {
            $usedAs = [];
            foreach ($attributeUse as $field => $use) {
                if (is_array($use) && ($use['present'] ?? false) && (string)($use['attribute'] ?? '') === (string)$name) {
                    $usedAs[] = self::fieldLabel((string)$field);
                }
            }
            $rows[] = [
                'name' => (string)$name,
                // a list is one value per line; an object (ZITADEL's roles: {role: {org id: domain}}) is one value, its keys kept
                'values' => array_map(
                    fn (mixed $one): string => is_string($one) ? $one : (string)json_encode($one, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    is_array($value) && array_is_list($value) ? $value : [$value]
                ),
                'usedAs' => $usedAs,
            ];
        }

        return $rows;
    }

    /** A field as the admin screens name it: translated for the fields every provider has, as it is for the others. */
    public static function fieldLabel(string $field): string
    {
        return in_array($field, ProvidersEditor::ATTRIBUTES, true) ? _t('SIGN_IN_ADMIN_ATTRIBUTE_' . strtoupper($field)) : $field;
    }
}
