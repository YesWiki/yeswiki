<?php

namespace YesWiki\Render\Formatter;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Footnote\Node\Footnote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;

/** Accepts the footnote definitions authors actually write: `[^1] :`, and glued to the paragraph above. */
final class FootnoteDefinitionExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addBlockStartParser(new FootnoteDefinitionStartParser(), 52);
        $environment->addEventListener(DocumentParsedEvent::class, [$this, 'onDocumentParsed'], 20);
    }

    /** Drops the `\` hard break left dangling at the end of a paragraph that a definition closed. */
    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if (!$node instanceof Footnote) {
                continue;
            }
            $paragraph = $node->previous();
            $last = $paragraph instanceof Paragraph ? $paragraph->lastChild() : null;
            if (!$last instanceof Text || !str_ends_with($last->getLiteral(), '\\')) {
                continue;
            }
            $literal = rtrim(substr($last->getLiteral(), 0, -1));
            if ($literal === '') {
                $last->detach();
            } else {
                $last->setLiteral($literal);
            }
        }
    }
}
