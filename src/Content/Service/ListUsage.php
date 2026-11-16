<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Field\EnumField;
use YesWiki\Kernel\Service\RequestScopedState;

/** Which form fields take their options from each list, read from the forms once per request since nothing records it the other way (end-users 20). */
class ListUsage implements RequestScopedState
{
    /** @var array<string, list<array{formId: string, formLabel: string, fieldName: string, fieldLabel: string}>>|null */
    private ?array $byList = null;

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function startNewRequest(): void
    {
        $this->byList = null;
    }

    /** @return list<array{formId: string, formLabel: string, fieldName: string, fieldLabel: string}> the fields that take their options from that list */
    public function fieldsUsing(string $listId): array
    {
        return $this->map()[$listId] ?? [];
    }

    /** @return array<string, list<array{formId: string, formLabel: string, fieldName: string, fieldLabel: string}>> */
    private function map(): array
    {
        if ($this->byList !== null) {
            return $this->byList;
        }
        $this->byList = [];
        foreach ($this->container->get(FormManager::class)->getAll() as $form) {
            foreach ((array)($form['prepared'] ?? []) as $field) {
                if (!$field instanceof EnumField || $field->isEnumEntryField()) {
                    continue;
                }
                $listId = (string)$field->getLinkedObjectName();
                if ($listId === '') {
                    continue;
                }
                $this->byList[$listId][] = [
                    'formId' => (string)($form['id'] ?? ''),
                    'formLabel' => (string)($form['label'] ?? ''),
                    'fieldName' => (string)$field->getPropertyName(),
                    'fieldLabel' => strip_tags((string)$field->getLabel()),
                ];
            }
        }

        return $this->byList;
    }
}
