<?php

namespace YesWiki\Kernel\Component;

/**
 * One editable parameter of a Component: what the settings rail draws an input for, and
 * what ends up as `param="value"` in the tag.
 *
 * `type` is the contract with the browser -- it selects the Vue input, exactly as the
 * YAML's `type:` did (`javascripts/components/InputHelper.js` maps it to `input-{type}`,
 * with text/number/range/url/email all sharing `input-text`). The named constructors below
 * are that list, written out: an unspellable type used to be a silent `input-hidden`, and
 * now it is a call that does not exist.
 *
 * Immutable. Twelve Presentations share the same two blocks of settings, and a fluent
 * object that mutated in place would let one of them relabel a setting for all the others.
 */
final class Setting
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        private readonly string $name,
        private readonly ?string $type,
        private array $payload = [],
    ) {
    }

    // ---------------------------------------------------------------
    // The types. One named constructor per Vue input, so a typo cannot
    // compile into a hidden field the way an unknown `type:` string did.
    // ---------------------------------------------------------------

    public static function text(string $name): self
    {
        return new self($name, 'text');
    }

    public static function number(string $name): self
    {
        return new self($name, 'number');
    }

    public static function range(string $name): self
    {
        return new self($name, 'range');
    }

    public static function url(string $name): self
    {
        return new self($name, 'url');
    }

    public static function email(string $name): self
    {
        return new self($name, 'email');
    }

    public static function checkbox(string $name): self
    {
        return new self($name, 'checkbox');
    }

    /**
     * A closed list of values.
     *
     * Two shapes, because both are natural to write: `value => label` when they differ,
     * and a plain list when they do not (a number of columns is its own label). A list is
     * detected rather than keyed, or its array indexes would become the values -- offering
     * 0, 1, 2 for a choice of 2, 3, 4.
     *
     * `array-key` rather than `string`, and the keys are cast on the way in: a value that
     * is written into the tag as `1` cannot be held as a string key, because PHP turns a
     * numeric string key into an int whatever the declaration says.
     *
     * An option may itself be conditional -- `['label' => …, 'showif' => …]` rather than a
     * bare label -- for a choice that is only offered when another setting says so. Rare,
     * and the rail understands it; it is why a label here is not simply a string.
     *
     * @param array<array-key, string|int|array<string, mixed>> $options value => label, or
     *                                                                   just the values
     */
    public static function choice(string $name, array $options): self
    {
        $keyed = [];
        $isList = array_is_list($options);
        foreach ($options as $value => $label) {
            $key = $isList ? $label : $value;
            $keyed[(string)(is_array($key) ? $value : $key)] = is_array($label) ? $label : (string)$label;
        }

        return (new self($name, 'list'))->with('options', $keyed);
    }

    public static function color(string $name): self
    {
        return new self($name, 'color');
    }

    public static function icon(string $name): self
    {
        return new self($name, 'icon');
    }

    /** A CSS class, with the wiki's own vocabulary offered as sub-settings. */
    public static function cssClass(string $name): self
    {
        return new self($name, 'class');
    }

    /** Pick a page of this wiki. */
    public static function page(string $name): self
    {
        return new self($name, 'page-list');
    }

    /** Pick an entry of this wiki. */
    public static function entry(string $name): self
    {
        return new self($name, 'entry-list');
    }

    /** Pick a field of the form the component is pointed at. */
    public static function formField(string $name): self
    {
        return new self($name, 'form-field');
    }

    /** Map slots (title, subtitle, visual…) onto the form's fields. */
    public static function fieldMapping(string $name): self
    {
        return new self($name, 'field-mapping');
    }

    public static function iconMapping(string $name): self
    {
        return new self($name, 'icon-mapping');
    }

    public static function colorMapping(string $name): self
    {
        return new self($name, 'color-mapping');
    }

    public static function facets(string $name): self
    {
        return new self($name, 'facets');
    }

    public static function sortFields(string $name): self
    {
        return new self($name, 'sort-fields');
    }

    public static function navLinks(string $name): self
    {
        return new self($name, 'nav-links');
    }

    public static function columnsWidth(string $name): self
    {
        return new self($name, 'columns-width');
    }

    public static function geo(string $name): self
    {
        return new self($name, 'geo');
    }

    public static function reaction(string $name): self
    {
        return new self($name, 'reaction');
    }

    /**
     * Pick a picture through the file manager -- browse what the page already holds, or
     * upload one, rather than typing a filename and hoping it is spelt the same.
     */
    public static function image(string $name): self
    {
        return new self($name, 'image');
    }

    /**
     * A number chosen by dragging. For the ones where the right value is whatever looks
     * right, and no one knows it as a number before they see it.
     */
    public static function slider(string $name, float $min, float $max, float $step = 1): self
    {
        return (new self($name, 'range'))
            ->with('min', $min)
            ->with('max', $max)
            ->with('step', $step);
    }

    /**
     * Not an input at all: a rule between settings, or a line of prose in the panel.
     * They carry no name in the tag, so they are given one only to key the payload.
     */
    public static function divider(string $name = 'divider'): self
    {
        return new self($name, 'divider');
    }

    public static function note(string $name, string $text): self
    {
        return (new self($name, 'hint'))->with('label', $text);
    }

    // ---------------------------------------------------------------
    // Modifiers
    // ---------------------------------------------------------------

    public function label(string $label): self
    {
        return $this->with('label', $label);
    }

    public function hint(string $hint): self
    {
        return $this->with('hint', $hint);
    }

    /**
     * A caption above the control, for the settings whose `label` is not one.
     *
     * A checkbox's label is the sentence beside the box -- it is what you click and what
     * says what ticking it does -- so it cannot also be the word above it naming the thing
     * being set. Every other input has one caption doing both jobs; a checkbox needs two.
     */
    public function title(string $title): self
    {
        return $this->with('title', $title);
    }

    /**
     * The value the action itself uses when the parameter is absent.
     *
     * Load-bearing beyond documentation: a setting left at its default is **omitted** from
     * the tag, which is what keeps `{{entrylist id="x"}}` from being written out with
     * thirty parameters restating what the action would have done anyway.
     */
    public function default(string|int|bool $default): self
    {
        return $this->with('default', $default);
    }

    /** What the field is pre-filled with when the component is first inserted. */
    public function suggests(string|int $value): self
    {
        return $this->with('value', $value);
    }

    /** Folded away until the rail is asked to show advanced parameters. */
    public function advanced(): self
    {
        return $this->with('advanced', true);
    }

    // ---------------------------------------------------------------
    // How much of a row a setting takes. The rail lays its settings out on a six-column
    // grid, so these are the three divisions that come out even. Half is the default and
    // needs no call; a composite input takes the row whether it says so or not.
    // ---------------------------------------------------------------

    public function third(): self
    {
        return $this->with('span', 2);
    }

    public function half(): self
    {
        return $this->with('span', 3);
    }

    public function full(): self
    {
        return $this->with('span', 6);
    }

    /**
     * Writes its value into a shared parameter, alongside every other setting that names
     * the same one.
     *
     * `class` is the case: a section's shape, alignment, text tone and full-width-ness are
     * four separate choices that arrive as one space-separated string. They used to be
     * declared as sub-settings of a single `class` setting, which meant the rail drew them
     * as one block -- and a block cannot be interleaved with the ordinary parameters it
     * belongs among. Here they are settings like any other, and the rail joins them on the
     * way out and splits them on the way back in.
     */
    public function writesTo(string $parameter): self
    {
        return $this->with('writesTo', $parameter);
    }

    public function required(): self
    {
        return $this->with('required', true);
    }

    public function multiple(): self
    {
        return $this->with('multiple', true);
    }

    public function min(int $min): self
    {
        return $this->with('min', $min);
    }

    public function max(int $max): self
    {
        return $this->with('max', $max);
    }

    /** The sprite icon drawn beside the input. */
    public function withIcon(string $icon): self
    {
        return $this->with('icon', $icon);
    }

    /**
     * Only shown when other settings say so.
     *
     * `showIf('dynamic')` means "when `dynamic` is set to anything truthy";
     * `showIf(['search' => 'dynamic'])` means "when `search` is exactly that".
     *
     * @param string|array<string, string|int|bool> $condition
     */
    public function showIf(string|array $condition): self
    {
        return $this->with('showif', $condition);
    }

    /**
     * Shown only for some of the Components sharing this settings block, or hidden for
     * some of them. Only meaningful on a shared SettingGroup -- a Component's own settings
     * are already only its own.
     *
     * @param list<string> $componentIds
     */
    public function onlyFor(array $componentIds): self
    {
        return $this->with('showOnlyFor', $componentIds);
    }

    /** @param list<string> $componentIds */
    public function exceptFor(array $componentIds): self
    {
        return $this->with('showExceptFor', $componentIds);
    }

    /** What a checkbox writes when it is ticked, and when it is not. */
    public function checkedValues(string|int $checked, string|int $unchecked): self
    {
        return $this->with('checkedvalue', $checked)->with('uncheckedvalue', $unchecked);
    }

    /**
     * Values a form's own fields cannot supply, offered alongside them: `owner`,
     * `created_at`, `updated_at`, `url`, `form_id`.
     *
     * @param list<string> $fields
     */
    public function extraFields(array $fields): self
    {
        return $this->with('extraFields', $fields);
    }

    /**
     * Settings nested inside this one -- what `class` and `field-mapping` are made of.
     */
    public function subSettings(self ...$settings): self
    {
        $payload = [];
        foreach ($settings as $setting) {
            $payload[$setting->name] = $setting->toArray();
        }

        return $this->with('subproperties', $payload);
    }

    /**
     * This setting's value is the TAG the component writes, not a parameter of it.
     *
     * A Presentation is the only thing that needs this: `Cards` writes `{{entrylist}}` or
     * `{{syndication}}` depending on the source picked, and a tag cannot carry which tag it
     * is. Implies `notWritten()`, since the value has already been used by the time the
     * parameters are assembled.
     */
    public function decidesTag(): self
    {
        return $this->with('decidesTag', true)->notWritten();
    }

    /** Shown in the panel, but never written into the tag -- a control, not a parameter. */
    public function notWritten(): self
    {
        return $this->with('mapped', false);
    }

    /** A link to the page of the documentation that explains this setting. */
    public function documentedAt(string $url): self
    {
        return $this->with('doclink', $url);
    }

    /**
     * An escape hatch for a payload key a modifier does not cover -- the ones only one
     * component in the wiki uses (`intro`, `only`, `iconprefix`, `btn-label-add`,
     * `dataFromFormField`). Kept deliberately awkward: reach for it and you are declaring
     * something nothing else declares, which is worth noticing.
     */
    public function raw(string $key, mixed $value): self
    {
        return $this->with($key, $value);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['type' => $this->type] + $this->payload;
    }

    private function with(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->payload[$key] = $value;

        return $clone;
    }
}
