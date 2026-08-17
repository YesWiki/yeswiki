<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Component\ComponentRegistry;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A component that asks which FIELD also has to ask which FORM. */
class FormPickerTest extends YesWikiTestCase
{
    /** Setting types whose options are the fields of a form. */
    private const NEEDS_A_FORM = ['form-field', 'field-mapping', 'facets', 'sort-fields'];

    public function testEverySettingMadeOfAFormsFieldsComesWithAFormPicker(): void
    {
        $checked = 0;
        foreach (self::components() as $id => $component) {
            $types = self::settingTypes($component);
            $asksForAField = array_intersect($types, self::NEEDS_A_FORM);
            if ($asksForAField === []) {
                continue;
            }
            $checked++;
            $this->assertContains(
                'form-list',
                $types,
                "{$id} offers " . implode(', ', $asksForAField)
                . ' -- fields OF a form -- so it must also offer the form'
            );
        }

        $this->assertGreaterThan(0, $checked, 'no component asks for a form field at all');
    }

    /** The form picker writes the parameter the action reads, which is `id`. */
    public function testTheFormPickerIsTheIdParameter(): void
    {
        foreach (self::components() as $id => $component) {
            foreach (self::settings($component) as $name => $setting) {
                if (($setting['type'] ?? null) === 'form-list') {
                    $this->assertSame('id', $name, "{$id}'s form picker");
                }
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function components(): array
    {
        return self::getWiki()->services->get(ComponentRegistry::class)->byId();
    }

    /**
     * Every setting a component shows, its own and its shared groups'.
     *
     * @param array<string, mixed> $component
     *
     * @return array<string, array<string, mixed>>
     */
    private static function settings(array $component): array
    {
        $settings = $component['properties'] ?? [];
        foreach ($component['groups'] ?? [] as $group) {
            $settings += $group['properties'] ?? [];
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $component
     *
     * @return list<string>
     */
    private static function settingTypes(array $component): array
    {
        $types = [];
        foreach (self::settings($component) as $setting) {
            $types[] = $setting['type'] ?? '';
            foreach ($setting['subproperties'] ?? [] as $sub) {
                $types[] = $sub['type'] ?? '';
            }
        }

        return array_values(array_unique($types));
    }
}
