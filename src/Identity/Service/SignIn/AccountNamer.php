<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\SignIn\AttributeReader;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Kernel\Routing\ReservedTags;

use function Symfony\Component\String\u;

/**
 * Names an account created by a sign-in from the provider's account name template (`#[given_name] #[family_name]`), else from the
 * start of the email, else from the user ID: made into a NomWiki as Doryphore's loginsso did, or, with `account_name_pascalcase` off,
 * kept as the provider sent it as loginldap and logincas did. Numbered (`MarieDupont2`, `jdupont-2`) when a page or an account
 * already has the name, where loginldap and logincas signed the person in to that account. Always usable, never reserved or taken.
 */
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

    /**
     * A NomWiki, as loginsso's genere_nom_user() made it: punctuation splits words, accents go, each word starts with a capital,
     * the spaces and whatever is not a letter or a digit go, and a name with fewer than two capitals gets its last letter capitalised
     * (`Marie Dupont-Lefèvre` → `MarieDupontLefevre`, `jdupont` → `JduponT`). Empty when nothing usable is left.
     */
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

    /**
     * The name as the provider sent it, as loginldap kept the uid and logincas the `name` attribute: its case and accents stay,
     * spaces become `-`, and whatever a page name cannot hold goes (`J. Dupont` → `J.-Dupont`). Empty when nothing usable is left.
     */
    public static function asSent(string $text): string
    {
        $name = (string)preg_replace('/\s+/u', '-', trim($text));
        $name = (string)preg_replace('/[^\p{L}0-9_.-]/u', '', $name);

        return trim(mb_substr($name, 0, self::MAX_LENGTH), '-.');
    }
}
