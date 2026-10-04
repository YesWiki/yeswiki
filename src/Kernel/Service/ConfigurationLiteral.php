<?php

namespace YesWiki\Kernel\Service;

/** Reads a configuration file someone else wrote without running it: only `$yeswikiConfig = [...];` made of literals is accepted. */
class ConfigurationLiteral
{
    private const VARIABLES = ['$yeswikiConfig', '$wakkaConfig'];

    /** @var list<array{0: int|string, 1: string}> */
    private array $tokens = [];

    private int $position = 0;

    /**
     * The settings the file states.
     *
     * @return array<array-key, mixed>
     *
     * @throws \UnexpectedValueException when the file is anything but that one assignment of literals
     */
    public static function parse(string $source): array
    {
        $reader = new self();
        foreach (token_get_all($source) as $token) {
            $token = \is_array($token) ? [$token[0], $token[1]] : [$token, $token];
            if (!\in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)) {
                $reader->tokens[] = $token;
            } elseif ($token[0] === T_INLINE_HTML && trim($token[1]) !== '') {
                throw new \UnexpectedValueException('the configuration file holds text outside of PHP');
            }
        }

        $variable = $reader->next();
        if ($variable[0] !== T_VARIABLE || !\in_array($variable[1], self::VARIABLES, true)) {
            throw new \UnexpectedValueException('the configuration file does not start by assigning $yeswikiConfig');
        }
        $reader->expect('=');
        $value = $reader->value();
        $reader->expect(';');
        if ($reader->position < \count($reader->tokens)) {
            throw new \UnexpectedValueException('the configuration file does more than assign $yeswikiConfig: ' . $reader->tokens[$reader->position][1]);
        }
        if (!\is_array($value)) {
            throw new \UnexpectedValueException('the configuration file assigns $yeswikiConfig something other than an array');
        }

        return $value;
    }

    /** @return array{0: int|string, 1: string} */
    private function next(): array
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token === null) {
            throw new \UnexpectedValueException('the configuration file ends too early');
        }
        $this->position++;

        return $token;
    }

    private function peek(): int|string|null
    {
        return $this->tokens[$this->position][0] ?? null;
    }

    private function expect(string $symbol): void
    {
        $token = $this->next();
        if ($token[0] !== $symbol) {
            throw new \UnexpectedValueException("the configuration file has '{$token[1]}' where '$symbol' was expected");
        }
    }

    /** One literal: a scalar, a concatenation of strings, or an array of literals. */
    private function value(): mixed
    {
        $token = $this->next();
        switch ($token[0]) {
            case '[':
                return $this->items(']');
            case T_ARRAY:
                $this->expect('(');

                return $this->items(')');
            case '-':
            case '+':
                $number = $this->next();
                if (!\in_array($number[0], [T_LNUMBER, T_DNUMBER], true)) {
                    throw new \UnexpectedValueException("the configuration file has a sign before '{$number[1]}'");
                }
                $value = self::number($number);

                return $token[0] === '-' ? -$value : $value;
            case T_LNUMBER:
            case T_DNUMBER:
                return self::number($token);
            case T_CONSTANT_ENCAPSED_STRING:
                $string = self::string($token[1]);
                while ($this->peek() === '.') {
                    $this->position++;
                    $next = $this->next();
                    if ($next[0] !== T_CONSTANT_ENCAPSED_STRING) {
                        throw new \UnexpectedValueException("the configuration file joins a string to '{$next[1]}'");
                    }
                    $string .= self::string($next[1]);
                }

                return $string;
            case T_STRING:
                $constant = strtolower($token[1]);
                if (\in_array($constant, ['true', 'false', 'null'], true)) {
                    return ['true' => true, 'false' => false, 'null' => null][$constant];
                }
                break;
        }

        throw new \UnexpectedValueException("the configuration file has '{$token[1]}' where only a literal value is allowed");
    }

    /** @return array<array-key, mixed> */
    private function items(string $close): array
    {
        $items = [];
        while ($this->peek() !== $close) {
            $value = $this->value();
            if ($this->peek() === T_DOUBLE_ARROW) {
                $this->position++;
                if (!\is_int($value) && !\is_string($value)) {
                    throw new \UnexpectedValueException('the configuration file has an array key that is neither a string nor a number');
                }
                $items[$value] = $this->value();
            } else {
                $items[] = $value;
            }
            if ($this->peek() !== ',') {
                break;
            }
            $this->position++;
        }
        $this->expect($close);

        return $items;
    }

    /** @param array{0: int|string, 1: string} $token */
    private static function number(array $token): int|float
    {
        $text = str_replace('_', '', $token[1]);
        if ($token[0] === T_DNUMBER) {
            return (float)$text;
        }
        if (preg_match('/^0[oO]([0-7]+)$/', $text, $octal)) {
            return (int)octdec($octal[1]);
        }

        return \intval($text, 0);
    }

    private static function string(string $literal): string
    {
        $body = substr($literal, 1, -1);
        if ($literal[0] === "'") {
            return (string)preg_replace('/\\\\([\\\\\'])/', '$1', $body);
        }

        return (string)preg_replace_callback(
            '/\\\\(?:([nrtvef\\\\$"])|([0-7]{1,3})|x([0-9A-Fa-f]{1,2})|u\{([0-9A-Fa-f]+)\})/',
            static function (array $found): string {
                if ($found[1] !== '') {
                    return ['n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'][$found[1]];
                }
                if ($found[2] !== '') {
                    return \chr(octdec($found[2]) & 0xFF);
                }
                if ($found[3] !== '') {
                    return \chr((int)hexdec($found[3]));
                }

                return mb_chr((int)hexdec($found[4]), 'UTF-8') ?: '';
            },
            $body
        );
    }
}
