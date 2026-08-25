<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;

#[Field(['conditionschecking'])]
class ConditionsCheckingField extends LabelField
{
    private string $condition = '';

    /** @var array{noclean: bool} */
    private array $options = ['noclean' => false];

    protected const FIELD_CONDITION = 1;
    protected const FIELD_OPTIONS = 2;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->condition = $values[self::FIELD_CONDITION] ?? '';
        $this->options = !empty($values[self::FIELD_OPTIONS]) && in_array($values[self::FIELD_OPTIONS], ['noclean'], true) ? ['noclean' => true] : ['noclean' => false];
        $this->formText = $this->prepareFormText();
        $this->viewText = '';
    }

    protected function prepareFormText(): ?string
    {
        return $this->render('@core/inputs/conditions-checking.twig', [
        ]);
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * @return array{noclean: bool}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, BazarField> $fieldsByPropertyName BazarField instances of the form, indexed by propertyName
     */
    public function evaluate(array $entry, array $fieldsByPropertyName = []): bool
    {
        $tokens = $this->buildConditionTokens($this->condition, $entry, $fieldsByPropertyName);
        if ($tokens === null) {
            return false;
        }
        $pos = 0;

        return $this->evalOr($tokens, $pos);
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, BazarField> $fieldsByPropertyName
     *
     * @return list<array{type: 'NOT'|'OPEN'|'CLOSE'|'AND'|'OR'}|array{type: 'BOOL', value: bool}>|null null when the condition is malformed
     */
    private function buildConditionTokens(string $condition, array $entry, array $fieldsByPropertyName): ?array
    {
        $tokens = [];
        $remaining = $condition;
        while (trim($remaining) !== '') {
            $split = $this->getFirstOperation($remaining);
            $operation = $split['operation'];
            $current = $split['current'];
            $remaining = $split['rest'];

            switch ($operation) {
                case '(':
                case '!(':
                case 'not(':
                case 'not (':
                    if ($current !== '') {
                        return null;
                    }
                    if ($operation !== '(') {
                        $tokens[] = ['type' => 'NOT'];
                    }
                    $tokens[] = ['type' => 'OPEN'];
                    break;
                case ')':
                    if ($current !== '') {
                        $tokens[] = ['type' => 'BOOL', 'value' => $this->evaluateLeaf($current, $entry, $fieldsByPropertyName)];
                    }
                    $tokens[] = ['type' => 'CLOSE'];
                    break;
                case 'and':
                case 'or':
                    if ($current !== '') {
                        $tokens[] = ['type' => 'BOOL', 'value' => $this->evaluateLeaf($current, $entry, $fieldsByPropertyName)];
                    }
                    $tokens[] = ['type' => $operation === 'and' ? 'AND' : 'OR'];
                    break;
                default:
                    if ($current !== '') {
                        $tokens[] = ['type' => 'BOOL', 'value' => $this->evaluateLeaf($current, $entry, $fieldsByPropertyName)];
                    }
                    break;
            }
        }

        return $tokens;
    }

    /**
     * @return array{current: string, operation: string, rest: string}
     */
    private function getFirstOperation(string $condition): array
    {
        $best = null;
        foreach (['!(', 'not(', 'not (', '(', ')'] as $element) {
            if (preg_match('/' . preg_quote($element, '/') . '/i', $condition, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($best === null || $pos < $best['pos']) {
                    $best = ['pos' => $pos, 'element' => $element, 'len' => strlen($m[0][0])];
                }
            }
        }
        foreach (['and', 'or'] as $element) {
            if (preg_match('/(?<= |\)|^)' . $element . '(?= |\))/i', $condition, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($best === null || $pos < $best['pos']) {
                    $best = ['pos' => $pos, 'element' => $element, 'len' => strlen($m[0][0])];
                }
            }
        }
        if ($best === null) {
            return ['current' => trim($condition), 'operation' => '', 'rest' => ''];
        }

        return [
            'current' => trim(substr($condition, 0, $best['pos'])),
            'operation' => $best['element'],
            'rest' => trim(substr($condition, $best['pos'] + $best['len'])),
        ];
    }

    /**
     * @return array{left: string, type: string, right: string}
     */
    private function splitLeafCondition(string $condition): array
    {
        $condition = trim($condition);
        $elements = ['match', '==', '!=', ' in', '|length ==', '|length !=', '|length <', '|length <=', '|length >=', '|length >', ' is empty', ' is not empty', 'false', 'true'];
        $best = null;
        foreach ($elements as $element) {
            if (preg_match('/' . preg_quote($element, '/') . '/i', $condition, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($best === null || $pos < $best['pos']) {
                    $best = ['pos' => $pos, 'element' => $element, 'len' => strlen($m[0][0])];
                }
            }
        }
        if ($best === null) {
            return ['left' => trim($condition), 'type' => '', 'right' => ''];
        }

        return [
            'left' => trim(substr($condition, 0, $best['pos'])),
            'type' => trim($best['element']),
            'right' => trim(substr($condition, $best['pos'] + $best['len'])),
        ];
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, BazarField> $fieldsByPropertyName
     */
    private function evaluateLeaf(string $condition, array $entry, array $fieldsByPropertyName): bool
    {
        $leaf = $this->splitLeafCondition($condition);
        switch ($leaf['type']) {
            case 'match':
                return $this->conditionMatch($leaf['left'], $leaf['right'], $entry, $fieldsByPropertyName);
            case '==':
                return $this->conditionIsEqual($leaf['left'], $leaf['right'], $entry, $fieldsByPropertyName);
            case '!=':
                return !$this->conditionIsEqual($leaf['left'], $leaf['right'], $entry, $fieldsByPropertyName);
            case 'in':
                return $this->conditionIsIn($leaf['left'], $leaf['right'], $entry, $fieldsByPropertyName);
            case 'is empty':
                return $this->conditionIsEqual($leaf['left'], '', $entry, $fieldsByPropertyName);
            case 'is not empty':
                return !$this->conditionIsEqual($leaf['left'], '', $entry, $fieldsByPropertyName);
            case 'true':
                return true;
            case 'false':
                return false;
            case '':
                return true;
            default:
                if (strpos($leaf['type'], '|length ') === 0) {
                    return $this->conditionIsLength($leaf['left'], $leaf['right'], substr($leaf['type'], strlen('|length ')), $entry, $fieldsByPropertyName);
                }

                return false;
        }
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, BazarField> $fieldsByPropertyName
     *
     * @return list<string>
     */
    private function getEntryFieldValues(string $fieldName, array $entry, array $fieldsByPropertyName): array
    {
        $raw = $entry[$fieldName] ?? '';
        $raw = is_array($raw) ? implode(',', $raw) : (string)$raw;
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }
        $isMultiple = false;
        if (isset($fieldsByPropertyName[$fieldName])) {
            $structure = $fieldsByPropertyName[$fieldName]->getValueStructure();
            $isMultiple = ($structure[$fieldName]['_mode_'] ?? 'single') === 'multiple';
        }
        if (!$isMultiple) {
            return [$trimmed];
        }

        return array_values(array_filter(array_map('trim', explode(',', $trimmed)), function ($value) {
            return $value !== '';
        }));
    }

    /**
     * @return list<string>
     */
    private function extractConditionValues(string $values): array
    {
        $trimmed = trim($values);
        if ($trimmed === '') {
            return [];
        }
        if (substr($trimmed, 0, 1) === '[' && substr($trimmed, -1) === ']') {
            $trimmed = substr($trimmed, 1, -1);
        }

        return array_map('trim', explode(',', $trimmed));
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, BazarField> $fieldsByPropertyName
     */
    private function conditionIsEqual(string $fieldName, string $values, array $entry, array $fieldsByPropertyName): bool
    {
        $fieldValues = array_values(array_unique($this->getEntryFieldValues($fieldName, $entry, $fieldsByPropertyName)));
        $conditionValues = array_values(array_unique($this->extractConditionValues($values)));
        if (count($fieldValues) !== count($conditionValues)) {
            return false;
        }
        sort($fieldValues);
        sort($conditionValues);

        return $fieldValues === $conditionValues;
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, BazarField> $fieldsByPropertyName
     */
    private function conditionIsIn(string $fieldName, string $values, array $entry, array $fieldsByPropertyName): bool
    {
        $fieldValues = $this->getEntryFieldValues($fieldName, $entry, $fieldsByPropertyName);
        if (empty($fieldValues)) {
            return false;
        }
        $conditionValues = $this->extractConditionValues($values);

        return !empty(array_intersect($fieldValues, $conditionValues));
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, BazarField> $fieldsByPropertyName
     */
    private function conditionIsLength(string $fieldName, string $values, string $operation, array $entry, array $fieldsByPropertyName): bool
    {
        $values = trim($values);
        if (!is_numeric($values)) {
            return false;
        }
        $length = count($this->getEntryFieldValues($fieldName, $entry, $fieldsByPropertyName));
        $number = (float)$values;
        switch (trim($operation)) {
            case '==':
                return $length == $number;
            case '!=':
                return $length != $number;
            case '<':
                return $length < $number;
            case '<=':
                return $length <= $number;
            case '>':
                return $length > $number;
            case '>=':
                return $length >= $number;
            default:
                return false;
        }
    }

    /**
     * @param array<string, mixed>      $entry
     * @param array<string, BazarField> $fieldsByPropertyName
     */
    private function conditionMatch(string $fieldName, string $values, array $entry, array $fieldsByPropertyName): bool
    {
        $fieldValues = $this->getEntryFieldValues($fieldName, $entry, $fieldsByPropertyName);
        $conditionValues = $this->extractConditionValues($values);
        if (empty($fieldValues) || count($fieldValues) !== count($conditionValues)) {
            return false;
        }
        $regexes = [];
        foreach ($conditionValues as $value) {
            if (!preg_match('/^\/(.*)\/([a-zA-Z]*)$/', $value, $m)) {
                return false;
            }
            $flags = preg_replace('/[^imsu]/', '', $m[2]);
            $regexes[] = '/' . str_replace('/', '\/', $m[1]) . '/' . $flags;
        }
        foreach ($fieldValues as $fieldValue) {
            $matched = false;
            foreach ($regexes as $regex) {
                if (@preg_match($regex, $fieldValue) === 1) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{type: 'NOT'|'OPEN'|'CLOSE'|'AND'|'OR'}|array{type: 'BOOL', value: bool}> $tokens
     */
    private function evalOr(array $tokens, int &$pos): bool
    {
        $result = $this->evalAnd($tokens, $pos);
        while ($pos < count($tokens) && $tokens[$pos]['type'] === 'OR') {
            $pos++;
            $right = $this->evalAnd($tokens, $pos);
            $result = $result || $right;
        }

        return $result;
    }

    /**
     * @param list<array{type: 'NOT'|'OPEN'|'CLOSE'|'AND'|'OR'}|array{type: 'BOOL', value: bool}> $tokens
     */
    private function evalAnd(array $tokens, int &$pos): bool
    {
        $result = $this->evalUnary($tokens, $pos);
        while ($pos < count($tokens) && $tokens[$pos]['type'] === 'AND') {
            $pos++;
            $right = $this->evalUnary($tokens, $pos);
            $result = $result && $right;
        }

        return $result;
    }

    /**
     * @param list<array{type: 'NOT'|'OPEN'|'CLOSE'|'AND'|'OR'}|array{type: 'BOOL', value: bool}> $tokens
     */
    private function evalUnary(array $tokens, int &$pos): bool
    {
        if ($pos < count($tokens) && $tokens[$pos]['type'] === 'NOT') {
            $pos++;

            return !$this->evalUnary($tokens, $pos);
        }

        return $this->evalPrimary($tokens, $pos);
    }

    /**
     * @param list<array{type: 'NOT'|'OPEN'|'CLOSE'|'AND'|'OR'}|array{type: 'BOOL', value: bool}> $tokens
     */
    private function evalPrimary(array $tokens, int &$pos): bool
    {
        if ($pos >= count($tokens)) {
            return false;
        }
        $token = $tokens[$pos];
        if ($token['type'] === 'OPEN') {
            $pos++;
            $result = $this->evalOr($tokens, $pos);
            if ($pos < count($tokens) && $tokens[$pos]['type'] === 'CLOSE') {
                $pos++;
            }

            return $result;
        }
        if ($token['type'] === 'BOOL') {
            $pos++;

            return $token['value'];
        }
        $pos++;

        return false;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'condition' => $this->getCondition(),
                'option' => $this->getOptions(),
            ],
        );
    }
}
