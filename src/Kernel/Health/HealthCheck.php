<?php

namespace YesWiki\Kernel\Health;

/**
 * A named claim about the present, re-derived whenever it is asked (ADR-0026).
 *
 * Never recorded. "Your themes still call `{{searchform}}`" stops being true the moment someone
 * acts on it, and a record designed never to change is the wrong home for it -- which is how the
 * wiki-page log ended up telling a webmaster in October to fix what they had fixed in April.
 */
final class HealthCheck
{
    private string $label = '';

    private Severity $severity = Severity::Broken;

    /** @var callable(): ?string */
    private $run;

    private string $says = '';

    private ?string $route = null;

    /** @var callable(): bool */
    private $gate;

    private function __construct(private readonly string $id)
    {
        $this->run = static fn (): ?string => null;
        $this->gate = static fn (): bool => true;
    }

    public static function named(string $id): self
    {
        return new self($id);
    }

    /** What is being checked, in the reader's language. */
    public function label(string $label): self
    {
        return $this->with(fn (self $c) => $c->label = $label);
    }

    /** Something is lost but the wiki works: shown on the screen with its cost, and no badge. */
    public function degraded(): self
    {
        return $this->with(fn (self $c) => $c->severity = Severity::Degraded);
    }

    /**
     * How to run it: null when there is nothing wrong, and what is wrong when there is.
     *
     * @param callable(): ?string $run
     */
    public function runs(callable $run): self
    {
        return $this->with(fn (self $c) => $c->run = $run);
    }

    /** What it costs and what to do about it -- the sentence beside the detail. */
    public function says(string $sentence): self
    {
        return $this->with(fn (self $c) => $c->says = $sentence);
    }

    /** The screen that fixes it, so a health screen never re-implements one. */
    public function linkedTo(string $route): self
    {
        return $this->with(fn (self $c) => $c->route = $route);
    }

    /**
     * When this wiki can act on it at all.
     *
     * A badge a webmaster cannot clear is permanent, and a permanent badge teaches people to
     * ignore every badge -- so a farm instance that may not update the shared Program is not told
     * that the Program is out of date (ADR-0007).
     *
     * @param callable(): bool $gate
     */
    public function actionableWhen(callable $gate): self
    {
        return $this->with(fn (self $c) => $c->gate = $gate);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function labelText(): string
    {
        return $this->label === '' ? $this->id : $this->label;
    }

    public function severity(): Severity
    {
        return $this->severity;
    }

    public function sentence(): string
    {
        return $this->says;
    }

    public function route(): ?string
    {
        return $this->route;
    }

    public function isActionable(): bool
    {
        return ($this->gate)();
    }

    /** @return string|null what is wrong, or null when nothing is */
    public function failure(): ?string
    {
        return ($this->run)();
    }

    private function with(callable $mutate): self
    {
        $clone = clone $this;
        $mutate($clone);

        return $clone;
    }
}
