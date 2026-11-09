<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\GroupManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;

/**
 * @Field({"acls"})
 */
class AclField extends BazarField
{
    protected $aclService;
    protected $askIfActivateComments;
    protected $entryReadRight;
    protected $entryWriteRight;
    protected $entryCommentRight;
    protected $params;
    protected $options;
    protected $managedGroups;
    protected $linkedReadRight;
    protected $linkedWriteRight;

    public const OPTIONS = [
        'oui' => 'YES',
        'non' => 'NO',
    ];
    public const OPTION_YES = 'oui';
    public const OPTION_NO = 'non';

    protected const FIELD_ENTRY_READ_RIGHT = 1;
    protected const FIELD_ENTRY_WRITE_RIGHT = 2;
    protected const FIELD_ENTRY_COMMENT_RIGHT = 3;
    protected const FIELD_LABEL = 4;
    protected const FIELD_NAME = 6;
    protected const FIELD_ASK_IF_ACTIVATE_COMMENTS = 7;
    private const FIELD_CLASS_TYPE = 'AclField';
    protected const FIELD_MANAGED_GROUPS = 13;
    protected const FIELD_LINKED_READ_RIGHT = 14;
    protected const FIELD_LINKED_WRITE_RIGHT = 15;
    private const MANAGED_GROUP_URI = 'http://outils-reseaux.org/_vocabulary/managedGroup';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->askIfActivateComments = in_array($this->filterNotEmptyString($values, self::FIELD_ASK_IF_ACTIVATE_COMMENTS, 'no'), [1, true, '1', 'true', 'yes'], true);

        $this->managedGroups = $this->filterNotEmptyString($values, self::FIELD_MANAGED_GROUPS, '');
        $this->linkedReadRight = $this->filterNotEmptyString($values, self::FIELD_LINKED_READ_RIGHT, '');
        $this->linkedWriteRight = $this->filterNotEmptyString($values, self::FIELD_LINKED_WRITE_RIGHT, '');

        $defaultName = $this->askIfActivateComments ? 'bf_commentaires' : ($this->managedGroups . $this->linkedReadRight . $this->linkedWriteRight !== '' ? 'bf_acls' : null);
        $this->name = $this->filterNotEmptyString($values, self::FIELD_NAME, $defaultName);
        $this->propertyName = $this->name;

        $this->default ??= '';
        $this->default = in_array($this->default, array_keys(self::OPTIONS), true) ? $this->default : '';

        $this->label = $this->filterNotEmptyString($values, self::FIELD_LABEL, _t('BAZ_ACTIVATE_COMMENTS'));
        $this->hint = $this->filterNotEmptyString(['hint' => $this->hint], 'hint', _t('BAZ_ACTIVATE_COMMENTS_HINT'));

        $this->entryReadRight = $this->filterNotEmptyString($values, self::FIELD_ENTRY_READ_RIGHT, '*');
        $this->entryWriteRight = $this->filterNotEmptyString($values, self::FIELD_ENTRY_WRITE_RIGHT, '%');
        $this->entryCommentRight = $this->filterNotEmptyString($values, self::FIELD_ENTRY_COMMENT_RIGHT, 'comments-closed');

        $this->aclService = $this->getService(AclService::class);
        $this->params = $this->getService(ParameterBagInterface::class);

        $this->options = null;
    }

    protected function filterNotEmptyString(array $data, string $key, ?string $default): ?string
    {
        return (
            isset($data[$key])
            && is_string($data[$key])
            && !empty(trim($data[$key]))
        )
            ? trim($data[$key])
            : $default;
    }

    protected function renderInput($entry)
    {
        $commentsAlreadyClosed = false;
        $isYesWikiType = in_array($this->getCommentsType(), ['', 'yeswiki']);
        if ($isYesWikiType && !empty($entry['id_fiche'])) {
            $currentCommentAcl = $this->aclService->load($entry['id_fiche'], 'comment', false);
            $commentsAlreadyClosed = (!empty($currentCommentAcl['list']) && $currentCommentAcl['list'] == 'comments-closed');
        }

        return ($this->askIfActivateComments)
            ? $this->render('@bazar/inputs/comments.twig', [
                'value' => $commentsAlreadyClosed ? self::OPTION_NO : $this->getValue($entry),
                'options' => $this->getOptions(),
                'showAlertForCommentsNotActivated' => $isYesWikiType
                    && $this->params->get('comments_activated') !== true,
            ])
            : '';
    }

    public function requireIDFiche()
    {
        return true;
    }

    public function formatValuesBeforeSave($entry)
    {
        $this->syncAccess($entry);
        if (empty($this->aclService->load($entry['id_fiche'], 'read', false)['list'])) {
            $this->aclService->save($entry['id_fiche'], 'read', $this->replaceWithCreator($this->entryReadRight, $entry));
        }
        if (empty($this->aclService->load($entry['id_fiche'], 'write', false)['list'])) {
            $this->aclService->save($entry['id_fiche'], 'write', $this->replaceWithCreator($this->entryWriteRight, $entry));
        }

        if ($this->askIfActivateComments) {
            $commentsType = $this->getCommentsType();
            switch ($commentsType) {
                case 'yeswiki':
                case '':
                    if ($this->getValue($entry) === self::OPTION_YES) {
                        $this->openComments($entry);
                        break;
                    }

                    // no break
                case 'external_humhub':
                case 'embedded_humhub':
                case 'discourse':
                default:
                    $this->closeComments($entry);
                    break;
            }

            return parent::formatValuesBeforeSave($entry);
        }
        if (empty($this->aclService->load($entry['id_fiche'], 'comment', false)['list'])) {
            $this->aclService->save($entry['id_fiche'], 'comment', $this->replaceWithCreator($this->entryCommentRight, $entry));
        }

        return (!empty($this->propertyName))
        ? [
            'fields-to-remove' => [
                $this->propertyName,
            ],
        ]
        : [];
    }

    protected function renderStatic($entry)
    {
        return '';
    }

    protected function getValue($entry)
    {
        $value = parent::getValue($entry);

        return in_array($value, array_keys(self::OPTIONS), true) ? $value : '';
    }

    private function replaceWithCreator($right, $entry)
    {
        if ($right === 'user' or $right === '#') {
            return $entry['nomwiki'];
        }

        return $right;
    }

    public function getCommentsType(): string
    {
        $commentsType = $this->params->get('comments_handler');

        return (
            empty($commentsType)
            || !is_string($commentsType)
        )
        ? ''
        : $commentsType;
    }

    public function getOptions()
    {
        if (is_null($this->options)) {
            $this->options = array_map('_t', self::OPTIONS);
        }

        return $this->options;
    }

    public function getAskIfActivateComments()
    {
        return $this->askIfActivateComments;
    }

    /** Brings the managed groups and the linked rights of an entry in line with its current values. */
    public function syncAccess(array $entry): void
    {
        if (empty($entry['id_fiche'])) {
            return;
        }
        $this->syncManagedGroups($entry);
        $this->saveLinkedRight($entry, 'read', $this->entryReadRight, $this->linkedReadRight);
        $this->saveLinkedRight($entry, 'write', $this->entryWriteRight, $this->linkedWriteRight);
    }

    /** Deletes the groups this entry manages. */
    public function removeManagedGroups(string $tag): void
    {
        $groups = $this->getService(GroupManager::class);
        $triples = $this->getService(TripleStore::class);
        foreach ($triples->getMatching($tag, self::MANAGED_GROUP_URI, null, '=') as $row) {
            $groups->delete($row['value']);
        }
        $triples->delete($tag, self::MANAGED_GROUP_URI, null, '', '');
    }

    /** Returns the configured [group prefix => field name] pairs. */
    private function managedGroupFields(): array
    {
        $out = [];
        foreach (preg_split('/\s*,\s*/', $this->managedGroups, -1, PREG_SPLIT_NO_EMPTY) as $pair) {
            [$prefix, $field] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, '');
            if ($prefix !== '' && $field !== '') {
                $out[$prefix] = $field;
            }
        }

        return $out;
    }

    private function syncManagedGroups(array $entry): void
    {
        $groups = $this->getService(GroupManager::class);
        $triples = $this->getService(TripleStore::class);
        foreach ($this->managedGroupFields() as $prefix => $field) {
            $group = $prefix . $entry['id_fiche'];
            $owned = $triples->exist($entry['id_fiche'], self::MANAGED_GROUP_URI, $group, '', '');
            $exists = $groups->groupExists($group);
            [$name, $fallback] = array_pad(explode('|', $field, 2), 2, '');
            $members = array_values(array_filter(array_map([$this, 'accountOf'], $this->valuesOf($entry, $name))));
            if (empty($members) && $fallback === 'owner') {
                $members = $this->creatorOf($entry['id_fiche']);
            }
            if ((!$owned && $exists) || (!$exists && empty($members))) {
                continue;
            }
            if ($exists) {
                $groups->updateMembers($group, $members);
            } else {
                $groups->create($group, $members);
            }
            if (!$owned) {
                $triples->create($entry['id_fiche'], self::MANAGED_GROUP_URI, $group, '', '');
            }
        }
    }

    /** Returns the account that created the entry: its page owner, or the logged user while it is being created. */
    private function creatorOf(string $tag): array
    {
        $users = $this->getService(UserManager::class);
        $name = $this->getService(PageManager::class)->getOwner($tag) ?? ($users->getLoggedUser()['name'] ?? '');

        return $name !== '' && $users->getOneByName($name) ? [$name] : [];
    }

    /** Returns the account behind a linked entry, or the account of that name. */
    private function accountOf(string $tag): ?string
    {
        $linked = $this->getService(EntryManager::class)->getOne($tag, false, null, true, true);
        $name = $linked ? (string)($linked['nomwiki'] ?? '') : $tag;

        return $name !== '' && $this->getService(UserManager::class)->getOneByName($name) ? $name : null;
    }

    /** Returns the values of a field of the entry, or its tag for id_fiche. */
    private function valuesOf(array $entry, string $name): array
    {
        if ($name === 'id_fiche') {
            return [$entry['id_fiche']];
        }
        $field = $this->getService(FormManager::class)->findFieldFromNameOrPropertyName($name, $entry['id_typeannonce'] ?? null);
        $value = $entry[$field ? $field->getPropertyName() : $name] ?? '';
        if (is_array($value)) {
            return array_map('strval', array_keys(array_filter($value)));
        }

        return preg_split('/\s*,\s*/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY);
    }

    private function saveLinkedRight(array $entry, string $privilege, string $base, string $linked): void
    {
        if ($linked === '') {
            return;
        }
        $lines = preg_split('/\s*[,\n]\s*/', trim($this->replaceWithCreator($base, $entry)), -1, PREG_SPLIT_NO_EMPTY);
        foreach (preg_split('/\s*[,\n]\s*/', $linked, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if (!preg_match('/\{(\w+)\}/', $token, $placeholder)) {
                $lines[] = $token;
                continue;
            }
            foreach ($this->valuesOf($entry, $placeholder[1]) as $value) {
                $lines[] = str_replace($placeholder[0], $value, $token);
            }
        }
        $this->aclService->save($entry['id_fiche'], $privilege, implode("\n", array_values(array_unique($lines))));
    }

    protected function closeComments($entry)
    {
        if (!empty($entry['id_fiche'])
                && is_string($entry['id_fiche'])
                && !empty(trim($entry['id_fiche']))) {
            $this->aclService->save($entry['id_fiche'], 'comment', 'comments-closed');
        }
    }

    protected function openComments($entry)
    {
        if (!empty($entry['id_fiche'])
                && is_string($entry['id_fiche'])
                && !empty(trim($entry['id_fiche']))) {
            $defaultRights = (empty($this->entryCommentRight) || $this->entryCommentRight === 'comments-closed')
                    ? '+'
                    : $this->entryCommentRight;
            $this->aclService->save($entry['id_fiche'], 'comment', $defaultRights);
        }
    }

    public static function mapToFieldArray($fieldProps): array
    {
        $new = parent::mapToFieldArray($fieldProps);
        $new[self::FIELD_TYPE] = $fieldProps['type'];
        $new[self::FIELD_ENTRY_READ_RIGHT] = $fieldProps['entryReadRight'] ?? '';
        $new[self::FIELD_ENTRY_WRITE_RIGHT] = $fieldProps['entryWriteRight'] ?? '';
        $new[self::FIELD_ENTRY_COMMENT_RIGHT] = $fieldProps['entryCommentRight'] ?? '';
        $new[self::FIELD_LABEL] = ($fieldProps['label'] ?? '') == _t('BAZ_ACTIVATE_COMMENTS') ? '' : ($fieldProps['label'] ?? '');
        $new[self::FIELD_DEFAULT] = $fieldProps['default'] ?? '';
        $new[self::FIELD_NAME] = $fieldProps['name'] ?? '';
        $new[self::FIELD_ASK_IF_ACTIVATE_COMMENTS] = !empty($fieldProps['askIfActivateComments']) ? '1' : '';
        $new[self::FIELD_HINT] = ($fieldProps['hint'] ?? '') == _t('BAZ_ACTIVATE_COMMENTS_HINT') ? '' : ($fieldProps['hint'] ?? '');
        $new[self::FIELD_MANAGED_GROUPS] = $fieldProps['managedGroups'] ?? '';
        $new[self::FIELD_LINKED_READ_RIGHT] = $fieldProps['linkedReadRight'] ?? '';
        $new[self::FIELD_LINKED_WRITE_RIGHT] = $fieldProps['linkedWriteRight'] ?? '';
        ksort($new);

        return $new;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'entryReadRight' => $this->entryReadRight,
            'entryWriteRight' => $this->entryWriteRight,
            'entryCommentRight' => $this->entryCommentRight,
            'name' => $this->getName(),
            'id' => $this->getPropertyName(),
            'propertyName' => $this->getPropertyName(),
            'label' => $this->getLabel(),
            'hint' => $this->getHint(),
            'default' => $this->getDefault(),
            'askIfActivateComments' => $this->getAskIfActivateComments(),
            'field_type' => self::FIELD_CLASS_TYPE,
            'managedGroups' => $this->managedGroups,
            'linkedReadRight' => $this->linkedReadRight,
            'linkedWriteRight' => $this->linkedWriteRight,
        ];
    }
}
