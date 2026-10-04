<?php

namespace YesWiki\Render\Formatter;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;

/** Marks `- [ ]` items and their lists with GitHub's classes, so they can be styled without bullets. */
final class TaskListClassExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, [$this, 'onDocumentParsed']);
    }

    /** Tags each list item that opens with a checkbox, and the list holding it. */
    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $marked = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if (!$node instanceof TaskListItemMarker) {
                continue;
            }
            $item = $node->parent()?->parent();
            if (!$item instanceof ListItem) {
                continue;
            }
            $item->data->append('attributes/class', 'task-list-item');
            $list = $item->parent();
            if ($list instanceof ListBlock && !isset($marked[spl_object_id($list)])) {
                $marked[spl_object_id($list)] = true;
                $list->data->append('attributes/class', 'contains-task-list');
            }
        }
    }
}
