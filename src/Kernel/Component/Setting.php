<?php

namespace YesWiki\Kernel\Component;

/**
 * One editable parameter of a Component: what the settings rail draws an input for, and what ends up as `param="value"` in the tag.
 */
final class Setting
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private readonly string $name,
        private readonly ?string $type,
        private array $payload = [],
    ) {
    }

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

    /** Pick one of this wiki's forms -- what a list has to be told before anything else. */
    public static function form(string $name): self
    {
        return new self($name, 'form-list');
    }

    /** Pick one of this wiki's menus, which is the whole of what `{{nav}}` is told (ticket 64). */
    public static function menu(string $name): self
    {
        return new self($name, 'menu-list');
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

    public static function facets(string $name): self
    {
        return new self($name, 'facets');
    }

    /** Narrow a list to the entries whose fields say something: `bf_type=3|bf_ville=Lyon`. */
    public static function query(string $name): self
    {
        return new self($name, 'query');
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
     * Pick a picture through the file manager -- browse what the page already holds, or upload one, rather than typing a filename and hoping it is spelt the same.
     */
    public static function image(string $name): self
    {
        return new self($name, 'image');
    }

    /** A number chosen by dragging. */
    public static function slider(string $name, float $min, float $max, float $step = 1): self
    {
        return (new self($name, 'range'))
            ->with('min', $min)
            ->with('max', $max)
            ->with('step', $step);
    }

    /** Not an input at all: a rule between settings, or a line of prose in the panel. */
    public static function divider(string $name = 'divider'): self
    {
        return new self($name, 'divider');
    }

    public static function note(string $name, string $text): self
    {
        return (new self($name, 'hint'))->with('label', $text);
    }

    public function label(string $label): self
    {
        return $this->with('label', $label);
    }

    public function hint(string $hint): self
    {
        return $this->with('hint', $hint);
    }

    /** A caption above the control, for the settings whose `label` is not one. */
    public function title(string $title): self
    {
        return $this->with('title', $title);
    }

    /** The value the action itself uses when the parameter is absent. */
    public function default(string|int|bool $default): self
    {
        return $this->with('default', $default);
    }

    /** What the field is pre-filled with when the component is first inserted. */
    public function suggests(string|int $value): self
    {
        return $this->with('value', $value);
    }

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

    /** Writes its value into a shared parameter, alongside every other setting that names the same one. */
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
     * @param string|array<string, string|int|bool> $condition
     */
    public function showIf(string|array $condition): self
    {
        return $this->with('showif', $condition);
    }

    /**
     * ...and this one as well, keeping whatever it already asks for.
     *
     * @param array<string, string|int|bool> $condition
     */
    public function andShowIf(array $condition): self
    {
        $existing = $this->payload['showif'] ?? [];

        if (is_string($existing)) {
            $existing = [$existing => 'notNull'];
        }

        return $this->with('showif', array_merge($existing, $condition));
    }

    /**
     * Shown only for some of the Components sharing this settings block, or hidden for some of them.
     *
     * @param list<string> $componentIds
     */
    public function onlyFor(array $componentIds): self
    {
        return $this->with('showOnlyFor', $componentIds);
    }

    /**
     * @param list<string> $componentIds
     */
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
     * Values a form's own fields cannot supply, offered alongside them: `owner`, `created_at`, `updated_at`, `url`, `form_id`.
     *
     * @param list<string> $fields
     */
    public function extraFields(array $fields): self
    {
        return $this->with('extraFields', $fields);
    }

    /**
     * Narrow a `formField` to the kinds of field that can actually play its part.
     *
     * @param list<string> $types
     */
    public function ofTypes(array $types): self
    {
        return $this->with('fieldTypes', $types);
    }

    /** Settings nested inside this one -- what `class` and `field-mapping` are made of. */
    public function subSettings(self ...$settings): self
    {
        $payload = [];
        foreach ($settings as $setting) {
            $payload[$setting->name] = $setting->toArray();
        }

        return $this->with('subproperties', $payload);
    }

    /** This setting's value is the TAG the component writes, not a parameter of it. */
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
     * An escape hatch for a payload key a modifier does not cover -- the ones only one component in the wiki uses (`intro`, `only`, `iconprefix`, `btn-label-add`, `dataFromFormField`).
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

    /**
     * @return array<string, mixed>
     */
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
