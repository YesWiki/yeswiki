<?php

namespace YesWiki\Identity\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\FormManager;

/** Adds an account to the groups a form names: `@group`, `+@group` to create it, or a field whose values are group names. */
class AccountGroups
{
    public const FORM_PROPERTY = 'account_groups';

    public function __construct(
        private readonly GroupOperationsService $groups,
        private readonly UserManager $userManager,
        private readonly FormManager $formManager,
        private readonly AclService $aclService,
    ) {
    }

    /**
     * Applies the User form's `account_groups` to an account, from the values its body holds.
     *
     * @param array<string, mixed> $values
     */
    public function applyUserForm(string $wikiName, array $values): void
    {
        $form = $this->formManager->getByContentType(ContentTypeSchema::TYPE_USER);
        if ($form === null || trim((string)($form[self::FORM_PROPERTY] ?? '')) === '') {
            return;
        }
        $this->add($wikiName, (string)$form[self::FORM_PROPERTY], $values, (string)$form['id']);
    }

    /**
     * Never removes the account from a group, even one a field no longer names.
     *
     * @param array<string, mixed> $values the entry or account body that `bf_*` references read from
     */
    public function add(string $wikiName, string $spec, array $values, ?string $formId): void
    {
        if (trim($spec) === '') {
            return;
        }
        $existing = $this->groups->getAll();
        $names = [];
        foreach (explode(',', $spec) as $item) {
            $item = trim($item);
            $create = str_starts_with($item, '+');
            $name = $create ? substr($item, 1) : $item;
            if (str_starts_with($name, '@')) {
                $candidates = [substr($name, 1)];
            } else {
                $field = $this->formManager->findFieldFromNameOrPropertyName($name, $formId);
                $value = $field === null ? '' : ($values[$field->getPropertyName()] ?? '');
                $candidates = is_array($value) ? $value : explode(',', (string)$value);
            }
            foreach ($candidates as $candidate) {
                $candidate = trim((string)$candidate);
                if ($this->mustJoin($wikiName, $candidate, $create, $existing)) {
                    $names[] = $candidate;
                }
            }
        }

        foreach (array_unique($names) as $name) {
            $previous = in_array($name, $existing, true) ? $this->groups->getMembersText($name) . "\n" : '';
            $this->groups->setMembersFromAclText($name, $previous . $wikiName);
        }
    }

    /**
     * @param array<string> $existing
     */
    private function mustJoin(string $wikiName, string $group, bool $create, array $existing): bool
    {
        if (!preg_match('/^[A-Za-z0-9]+$/', $group)) {
            return false;
        }
        if (strtolower($group) === ADMIN_GROUP && !$this->aclService->isAdmin()) {
            return false;
        }
        if (in_array($group, $existing, true)) {
            return !$this->userManager->isInGroup($group, $wikiName, false);
        }

        return $create;
    }
}
