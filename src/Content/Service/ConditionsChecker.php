<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\ConditionsCheckingField;
use YesWiki\Content\Field\LabelField;
use YesWiki\Content\Field\TabChangeField;
use YesWiki\Content\Field\TabsField;

/** Resolves the conditions of a form against an entry, the way the browser resolves them. */
class ConditionsChecker
{
    private const MAX_CASCADE_PASSES = 10;

    /** @param array<string, mixed> $form */
    public function hasConditions(array $form): bool
    {
        foreach ($this->preparedFields($form) as $field) {
            if ($field instanceof ConditionsCheckingField) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     *
     * @return array<int, array{visible: bool, cleared: bool}> keyed like $form['prepared']
     */
    public function states(array $form, array $entry): array
    {
        $prepared = $this->preparedFields($form);
        $fieldsByPropertyName = [];
        foreach ($prepared as $field) {
            if ($field instanceof BazarField && !empty($field->getPropertyName())) {
                $fieldsByPropertyName[$field->getPropertyName()] = $field;
            }
        }

        $states = [];
        $stack = [];
        $depth = 0;
        foreach ($prepared as $index => $field) {
            $states[$index] = [
                'visible' => $this->holdsEverywhere($stack),
                'cleared' => $this->isCleared($stack),
            ];
            if ($field instanceof ConditionsCheckingField) {
                ++$depth;
                $stack[] = [
                    'depth' => $depth,
                    'holds' => $field->evaluate($entry, $fieldsByPropertyName),
                    'clean' => empty($field->getOptions()['noclean']),
                ];
            } elseif ($field instanceof TabsField || $field instanceof TabChangeField) {
                continue;
            } elseif ($field instanceof LabelField) {
                $depth = $this->followDivs($field->getFormText(), $depth, $stack);
            }
        }

        return $states;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     *
     * @return list<string> property names of the fields a false condition hides
     */
    public function hiddenPropertyNames(array $form, array $entry): array
    {
        $prepared = $this->preparedFields($form);
        $hidden = [];
        foreach ($this->states($form, $entry) as $index => $state) {
            $field = $prepared[$index] ?? null;
            if (!$state['visible'] && $field instanceof BazarField && !empty($field->getPropertyName())) {
                $hidden[] = $field->getPropertyName();
            }
        }

        return array_values(array_unique($hidden));
    }

    /**
     * Drops the values of the hidden fields, following the cascade until it settles.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    public function clearHiddenValues(array $form, array $entry): array
    {
        $prepared = $this->preparedFields($form);
        for ($pass = 0; $pass < self::MAX_CASCADE_PASSES; ++$pass) {
            $changed = false;
            foreach ($this->states($form, $entry) as $index => $state) {
                $field = $prepared[$index] ?? null;
                if (!$state['cleared'] || !($field instanceof BazarField)) {
                    continue;
                }
                $propertyName = $field->getPropertyName();
                if (!empty($propertyName) && array_key_exists($propertyName, $entry)) {
                    unset($entry[$propertyName]);
                    $changed = true;
                }
            }
            if (!$changed) {
                break;
            }
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array<int, mixed>
     */
    private function preparedFields(array $form): array
    {
        $prepared = $form['prepared'] ?? [];

        return is_array($prepared) ? array_values($prepared) : [];
    }

    /** @param list<array{depth: int, holds: bool, clean: bool}> $stack */
    private function holdsEverywhere(array $stack): bool
    {
        foreach ($stack as $condition) {
            if (!$condition['holds']) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array{depth: int, holds: bool, clean: bool}> $stack */
    private function isCleared(array $stack): bool
    {
        foreach ($stack as $condition) {
            if (!$condition['holds'] && $condition['clean']) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{depth: int, holds: bool, clean: bool}> $stack */
    private function followDivs(?string $html, int $depth, array &$stack): int
    {
        if (empty($html) || !preg_match_all('#<div\b|</div\b#i', $html, $matches)) {
            return $depth;
        }
        foreach ($matches[0] as $tag) {
            $depth = ($tag[1] === '/') ? max(0, $depth - 1) : $depth + 1;
            while (!empty($stack) && end($stack)['depth'] > $depth) {
                array_pop($stack);
            }
        }

        return $depth;
    }
}
