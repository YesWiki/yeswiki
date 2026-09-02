<?php

namespace YesWiki\Identity\Service;

/** The choices an ACL picker offers, in the order a person expects them. */
class AclOptions
{
    public const READ = 'read';
    public const WRITE = 'write';
    public const COMMENT = 'comment';

    public function __construct(
        protected GroupOperationsService $groups,
    ) {
    }

    /**
     * @param string $privilege one of READ, WRITE, COMMENT
     * @param bool   $entry     whether the rights are stamped on bazar entries rather than a page
     *
     * @return list<array{value: string, label: string}>
     */
    public function for(string $privilege, bool $entry = false): array
    {
        $options = [];
        if ($privilege === self::COMMENT) {
            $options[] = ['value' => 'comments-closed', 'label' => _t('ACLS_COMMENTS_CLOSED')];
        } else {
            $options[] = ['value' => '*', 'label' => _t('ACLS_EVERYBODY')];
        }
        $options[] = ['value' => '+', 'label' => _t('ACLS_AUTHENTIFICATED_USERS')];
        $options[] = ['value' => '%', 'label' => _t($entry ? 'BAZ_FORM_EDIT_OWNER_AND_ADMINS' : 'ACLS_OWNER')];
        if ($entry && $privilege !== self::COMMENT) {
            $options[] = ['value' => 'user', 'label' => _t('BAZ_FORM_EDIT_USER')];
        }
        $options[] = ['value' => '@admins', 'label' => _t('ACLS_ADMIN_GROUP')];
        foreach ($this->groups->getAll() as $group) {
            if ($group === 'admins') {
                continue;
            }
            $options[] = ['value' => '@' . $group, 'label' => _t('ACL_PICKER_GROUP', ['name' => $group])];
        }

        return $options;
    }
}
