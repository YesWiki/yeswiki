<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Field\BazarField;

/**
 * Answers "which of this form's fields plays role X?" (ticket 11).
 *
 * Core asks this instead of reading a literal field name out of user data. The answer
 * comes from the form's explicit `field_roles` map when it has one, and otherwise from
 * the field's own type -- which is why existing forms need no migration and no webmaster
 * action: a `listedatedeb` field has always been the start date, nobody just said so.
 */
class FieldRoleResolver
{
    /**
     * The field playing $role on $form, or null if the form has none.
     *
     * @param array<string, mixed> $form a form array with `prepared` fields
     */
    public function field(?array $form, string $role): ?BazarField
    {
        if (empty($form['prepared']) || !FieldRole::isKnown($role)) {
            return null;
        }

        $explicit = FieldRole::normalizeMap($form[FieldRole::FORM_PROPERTY] ?? null)[$role] ?? null;
        if ($explicit !== null) {
            $field = $this->findByName($form['prepared'], $explicit);
            // an explicit mapping to an incompatible field is a misconfiguration, not an
            // instruction: fall through to the type default rather than returning
            // something the caller cannot use
            if ($field !== null && $this->isCompatible($field, $role)) {
                return $field;
            }
        }

        foreach (FieldRole::compatibleTypes($role) as $type) {
            foreach ($form['prepared'] as $field) {
                if ($field instanceof BazarField && $field->getType() === $type) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * The stored property name of the field playing $role, or null.
     *
     * @param array<string, mixed>|null $form
     */
    public function propertyName(?array $form, string $role): ?string
    {
        $field = $this->field($form, $role);

        return $field === null ? null : ($field->getPropertyName() ?: null);
    }

    /**
     * The value $entry holds for $role, or null when the form has no such field or the
     * entry left it empty.
     *
     * @param array<string, mixed>|null $form
     * @param array<string, mixed>      $entry
     */
    public function value(?array $form, array $entry, string $role): mixed
    {
        $propertyName = $this->propertyName($form, $role);
        if ($propertyName === null) {
            return null;
        }
        $value = $entry[$propertyName] ?? null;

        return ($value === '' || $value === []) ? null : $value;
    }

    /**
     * Whether the form can answer every one of these roles -- what a feature needs to run.
     *
     * @param array<string, mixed>|null $form
     */
    public function hasRoles(?array $form, string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->field($form, $role) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Which of $roles the form cannot answer, so a feature can say what is missing
     * instead of rendering nothing.
     *
     * @param array<string, mixed>|null $form
     *
     * @return list<string>
     */
    public function missingRoles(?array $form, string ...$roles): array
    {
        return array_values(array_filter($roles, fn (string $role) => $this->field($form, $role) === null));
    }

    /** @param array<int, mixed> $prepared */
    private function findByName(array $prepared, string $name): ?BazarField
    {
        foreach ($prepared as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            if ($field->getName() === $name || $field->getPropertyName() === $name) {
                return $field;
            }
        }

        return null;
    }

    private function isCompatible(BazarField $field, string $role): bool
    {
        $compatible = FieldRole::compatibleTypes($role);

        return empty($compatible) || in_array($field->getType(), $compatible, true);
    }
}
