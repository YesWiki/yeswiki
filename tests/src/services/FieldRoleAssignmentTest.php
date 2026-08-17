<?php

namespace YesWiki\Test\Content\Service;

use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Content\Service\FormManager;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Assigning roles explicitly (ticket 11, part 2). */
class FieldRoleAssignmentTest extends YesWikiTestCase
{
    private static ?string $formId = null;

    public static function tearDownAfterClass(): void
    {
        if (self::$formId !== null) {
            self::getWiki()->services->get(FormManager::class)->delete(self::$formId);
            self::$formId = null;
        }
    }

    /**
     * A form with two fields of the same type: the case a type default cannot answer.
     *
     * @return array<string, mixed>
     */
    private function ambiguousForm(): array
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);

        if (self::$formId === null) {
            $id = 9600;
            while ($formManager->getOne((string)$id) !== null) {
                $id++;
            }
            $this->assertSame(0, $formManager->create([
                'id' => (string)$id,
                'label' => 'FieldRoleAssignmentTest',
                'entry_title_template' => '{{bf_titre}}',
                'template' => [
                    ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                    ['type' => 'listedatedeb', 'name' => 'first_date', 'label' => 'Première date'],
                    ['type' => 'listedatedeb', 'name' => 'real_date', 'label' => 'La vraie date'],
                ],
            ]));
            self::$formId = (string)$id;
        }

        $form = $formManager->getOne(self::$formId);
        $this->assertNotNull($form);

        return $form;
    }

    /**
     * @param array<string, string> $map
     *
     * @return array<string, mixed>
     */
    private function withRoles(array $map): array
    {
        $formManager = $this->getWiki()->services->get(FormManager::class);
        $form = $this->ambiguousForm();
        $form[FieldRole::FORM_PROPERTY] = $map;
        $formManager->update($form);

        $reloaded = $formManager->getOne(self::$formId);
        $this->assertNotNull($reloaded);

        return $reloaded;
    }

    public function testWithoutAMapTheFirstFieldOfTheRightTypeWins(): void
    {
        $resolver = $this->getWiki()->services->get(FieldRoleResolver::class);

        $this->assertSame(
            'first_date',
            $resolver->propertyName($this->ambiguousForm(), FieldRole::START_DATE),
            'the type default is "the first field that could play it"'
        );
    }

    public function testAnExplicitMapIsStoredAndOverridesTheTypeDefault(): void
    {
        $form = $this->withRoles([FieldRole::START_DATE => 'real_date']);

        $this->assertSame(
            ['start_date' => 'real_date'],
            $form[FieldRole::FORM_PROPERTY] ?? null,
            'the map survives a save/reload round trip'
        );
        $this->assertSame(
            'real_date',
            $this->getWiki()->services->get(FieldRoleResolver::class)->propertyName($form, FieldRole::START_DATE)
        );
    }

    public function testAMapNamingAnIncompatibleFieldFallsBackRatherThanBreaking(): void
    {
        $form = $this->withRoles([FieldRole::START_DATE => 'bf_titre']);

        $this->assertSame(
            'first_date',
            $this->getWiki()->services->get(FieldRoleResolver::class)->propertyName($form, FieldRole::START_DATE)
        );
    }

    public function testClearingTheMapRestoresTheTypeDefault(): void
    {
        $this->withRoles([FieldRole::START_DATE => 'real_date']);
        $form = $this->withRoles([]);

        $this->assertSame([], $form[FieldRole::FORM_PROPERTY] ?? []);
        $this->assertSame(
            'first_date',
            $this->getWiki()->services->get(FieldRoleResolver::class)->propertyName($form, FieldRole::START_DATE)
        );
    }

    public function testNormalizeMapDropsWhatItCannotUse(): void
    {
        $this->assertSame(
            ['start_date' => 'real_date'],
            FieldRole::normalizeMap([
                'start_date' => ' real_date ',
                'not_a_role' => 'whatever',
                'image' => '',
                'email' => ['an array'],
            ])
        );

        $this->assertSame(
            ['start_date' => 'the_date'],
            FieldRole::normalizeMap(['start_date' => 'the_date', 'end_date' => 'the_date'])
        );

        $this->assertSame([], FieldRole::normalizeMap('not a map'));
    }

    public function testEveryRoleHasALabelKeyTheDesignerCanShow(): void
    {
        foreach (FieldRole::all() as $role) {
            $key = 'FORM_EDIT_FIELD_ROLE_' . strtoupper($role);
            $this->assertNotSame($key, _t($key), "the designer needs a label for the {$role} role");
        }
    }

    /**
     * The designer offers one select per role, filtered to the field types that can play it, with the form's current choice preselected.
     */
    public function testTheDesignerRendersASelectForEveryRole(): void
    {
        $form = $this->withRoles([FieldRole::START_DATE => 'real_date']);

        $output = $this->getWiki()->services->get(TemplateEngine::class)->render('@core/forms/forms_form.twig', [
            'form' => $form,
            'formAndListIds' => ['lists' => [], 'forms' => []],
            'groupsList' => [],
            'onlyOneEntryOptionAvailable' => false,
            'lockedFields' => [],
            'entryOnlyPropertiesAvailable' => true,
            'fieldRoles' => array_map(fn (string $role) => [
                'name' => $role,
                'property' => FieldRole::FORM_PROPERTY,
                'label' => 'FORM_EDIT_FIELD_ROLE_' . strtoupper($role),
                'types' => FieldRole::compatibleTypes($role),
                'current' => ($form[FieldRole::FORM_PROPERTY] ?? [])[$role] ?? '',
            ], FieldRole::all()),
        ]);

        foreach (FieldRole::all() as $role) {
            $this->assertStringContainsString('data-yw-field-role="' . $role . '"', $output);
            $this->assertStringContainsString('name="' . FieldRole::FORM_PROPERTY . '[' . $role . ']"', $output);
        }
        $this->assertStringContainsString('data-yw-role-types="listedatedeb,jour"', $output, 'the script filters the options by these');
        $this->assertStringContainsString('data-yw-role-current="real_date"', $output, "and preselects the form's own choice");
    }
}
