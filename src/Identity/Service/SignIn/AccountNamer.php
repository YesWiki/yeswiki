<?php

namespace YesWiki\Identity\Service\SignIn;

use function Symfony\Component\String\u;

use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\SignIn\AttributeReader;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Kernel\Routing\ReservedTags;

/** Names an account a sign-in creates, as a NomWiki made from the provider's attributes. */
class AccountNamer
{
    /** A NomWiki is cut there, to leave room for its number (loginsso's limit, pages allow 50). */
    private const MAX_LENGTH = 47;

    public function __construct(private readonly PageManager $pages)
    {
    }

    /**
     * @param array<string, mixed> $attributes what the provider sent
     */
    public function suggest(ProviderConfig $config, array $attributes): string
    {
        $email = AttributeReader::one($attributes, $config->attributes['email'] ?? 'email');
        $subject = AttributeReader::one($attributes, $config->attributes['id'] ?? 'id');

        $candidates = [
            self::fromTemplate($config->accountNameTemplate, $attributes),
            $email !== null ? explode('@', $email)[0] : '',
            $subject ?? '',
        ];
        foreach ($candidates as $candidate) {
            $name = $config->accountNamePascalCase ? self::wikiName($candidate) : self::asSent($candidate);
            if ($name !== '' && !ReservedTags::isReserved($name)) {
                return $this->pages->suggestFreeTag($name);
            }
        }

        return $this->pages->suggestFreeTag($config->accountNamePascalCase ? 'UtilisateurSso' : 'utilisateur-sso');
    }

    /**
     * The template with each `#[attribute]` replaced by the first value the provider sent under that name, nothing when it sent none.
     *
     * @param array<string, mixed> $attributes
     */
    private static function fromTemplate(?string $template, array $attributes): string
    {
        if ($template === null) {
            return '';
        }

        return trim((string)preg_replace_callback(
            '/#\[([^\]]+)\]/',
            fn (array $match): string => AttributeReader::one($attributes, $match[1]) ?? '',
            $template
        ));
    }

    /** A NomWiki, as loginsso's genere_nom_user() made it. */
    public static function wikiName(string $text): string
    {
        $text = (string)preg_replace('/[[:punct:]]/', ' ', u($text)->ascii()->toString());
        $text = mb_substr($text, 0, self::MAX_LENGTH);
        $name = '';
        foreach (explode(' ', ucwords(strtolower($text))) as $word) {
            $name .= (string)preg_replace('/[^a-zA-Z0-9]/', '', $word);
        }
        if ($name !== '' && strlen((string)preg_replace('/[^A-Z]/', '', $name)) < 2) {
            $name = substr($name, 0, -1) . strtoupper(substr($name, -1));
        }

        return $name;
    }

    /** The name as the provider sent it, made fit for a page name. */
    public static function asSent(string $text): string
    {
        $name = (string)preg_replace('/\s+/u', '-', trim($text));
        $name = (string)preg_replace('/[^\p{L}0-9_.-]/u', '', $name);

        return trim(mb_substr($name, 0, self::MAX_LENGTH), '-.');
    }
}
