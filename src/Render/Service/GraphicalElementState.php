<?php

namespace YesWiki\Render\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** What `{{panel}}`, `{{accordion}}` and `{{end}}` have to remember while one page renders. */
class GraphicalElementState implements RequestScopedState
{
    /** @var array<string, array<string, bool>> page tag => element => has a closing tag */
    private array $closings = [];

    /** @var array<string, list<string>> page tag => the shapes opened and not yet closed */
    private array $panelShapes = [];

    /** @var array<string, string> page tag => the accordion currently being filled */
    private array $accordionIds = [];

    /** @var array<string, bool> page tag => whether the accordion has had its first panel */
    private array $accordionHasFirstPanel = [];

    /**
     * Whether $body closes $element, computed once per page tag and element.
     *
     * @param callable(): bool $scan reads the body, which only the caller can do
     */
    public function closesElement(string $pageTag, string $element, callable $scan): bool
    {
        return $this->closings[$pageTag][$element] ??= $scan();
    }

    public function openPanel(string $pageTag, string $shape): void
    {
        $this->panelShapes[$pageTag][] = $shape;
    }

    /** The shape of the panel being closed, defaulting to the plain one for an unbalanced `{{end}}`. */
    public function closePanel(string $pageTag): string
    {
        return array_pop($this->panelShapes[$pageTag]) ?? 'panel';
    }

    public function openAccordion(string $pageTag, string $accordionId): void
    {
        $this->accordionIds[$pageTag] = $accordionId;
        unset($this->accordionHasFirstPanel[$pageTag]);
    }

    public function closeAccordion(string $pageTag): void
    {
        unset($this->accordionIds[$pageTag], $this->accordionHasFirstPanel[$pageTag]);
    }

    /** The accordion being filled, or an empty string when this panel stands on its own. */
    public function currentAccordion(string $pageTag): string
    {
        return $this->accordionIds[$pageTag] ?? '';
    }

    /** Whether the accordion has already taken a panel, marking it as having one from now on. */
    public function accordionTakesAnotherPanel(string $pageTag): bool
    {
        $already = $this->accordionHasFirstPanel[$pageTag] ?? false;
        $this->accordionHasFirstPanel[$pageTag] = true;

        return $already;
    }

    public function startNewRequest(): void
    {
        $this->closings = [];
        $this->panelShapes = [];
        $this->accordionIds = [];
        $this->accordionHasFirstPanel = [];
    }
}
