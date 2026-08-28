<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;
use YesWiki\Render\Service\MarkdownFormatterService;

#[Field(['labelhtml'])]
class LabelField extends BazarField
{
    /** @var string|null */
    protected $formText;

    /** @var string|null */
    protected $viewText;

    /** @var bool */
    protected $useWikiSyntax;

    protected const FIELD_FORM_TEXT = 1;
    protected const FIELD_VIEW_TEXT = 3;
    protected const FIELD_USE_WIKI_SYNTAX = 4;

    /**
     * @param array<int|string, mixed> $values
     */
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->name = null;
        $this->label = null;
        $this->propertyName = null;
        $this->formText = $values[self::FIELD_FORM_TEXT];
        $this->viewText = $values[self::FIELD_VIEW_TEXT];
        $this->useWikiSyntax = (
            $values[self::FIELD_USE_WIKI_SYNTAX] === false
            || empty($values[self::FIELD_USE_WIKI_SYNTAX])
            || in_array($values[self::FIELD_USE_WIKI_SYNTAX], [0, '0', 'no', 'non', 'false'])
        ) ? false : true;
    }

    protected function getValue($entry)
    {
        return null;
    }

    protected function renderInput($entry)
    {
        if ($this->useWikiSyntax) {
            $content = str_replace('<br/>', "\n", $this->formText ?? '');

            return $this->getService(MarkdownFormatterService::class)->format($content);
        }

        return $this->formText;
    }

    protected function renderStatic($entry)
    {
        if ($this->useWikiSyntax) {
            $content = str_replace('<br/>', "\n", $this->viewText ?? '');

            return $this->getService(MarkdownFormatterService::class)->format($content);
        }

        return $this->viewText;
    }

    public function getFormText(): ?string
    {
        return is_string($this->formText) ? $this->formText : null;
    }

    public function isConditionsCheckingClosingTag(): bool
    {
        return is_string($this->formText) && strpos(ltrim($this->formText), '</div>') === 0;
    }

    public function formatValuesBeforeSave($entry)
    {
        return [];
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'viewtext' => $this->viewText,
            'formtext' => $this->formText,
            'useWikiSyntax' => $this->useWikiSyntax,
        ];
    }
}
