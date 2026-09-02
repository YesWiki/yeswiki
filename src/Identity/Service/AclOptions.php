<?php

namespace YesWiki\Identity\Service;

/** The choices an ACL picker offers: who, then, under "only some", which owner, groups and names. */
class AclOptions
{
    public const READ = 'read';
    public const WRITE = 'write';
    public const COMMENT = 'comment';

    /** The audience value meaning "the members listed below", which is no rule of its own. */
    public const ONLY_SOME = 'only';

    public function __construct(
        protected GroupOperationsService $groups,
    ) {
    }

    /**
     * @param string $privilege one of READ, WRITE, COMMENT
     * @param bool   $entry     whether the rights are stamped on bazar entries rather than a page
     *
     * @return array{audience: list<array{value: string, label: string}>, members: list<array{value: string, label: string}>}
     */
    public function for(string $privilege, bool $entry = false): array
    {
        $audience = [];
        if ($privilege === self::COMMENT) {
            $audience[] = ['value' => 'comments-closed', 'label' => _t('ACLS_COMMENTS_CLOSED')];
        } else {
            $audience[] = ['value' => '*', 'label' => _t('ACLS_EVERYBODY')];
        }
        $audience[] = ['value' => '+', 'label' => _t('ACLS_AUTHENTIFICATED_USERS')];
        $audience[] = ['value' => self::ONLY_SOME, 'label' => _t('ACL_PICKER_ONLY')];

        $members = [];
        $members[] = ['value' => '%', 'label' => _t($entry ? 'BAZ_FORM_EDIT_OWNER_AND_ADMINS' : 'ACLS_OWNER')];
        if ($entry && $privilege !== self::COMMENT) {
            $members[] = ['value' => 'user', 'label' => _t('BAZ_FORM_EDIT_USER')];
        }
        $members[] = ['value' => '@admins', 'label' => _t('ACLS_ADMIN_GROUP')];
        foreach ($this->groups->getAll() as $group) {
            if ($group === 'admins') {
                continue;
            }
            $members[] = ['value' => '@' . $group, 'label' => _t('ACL_PICKER_GROUP', ['name' => $group])];
        }

        return ['audience' => $audience, 'members' => $members];
    }
}
