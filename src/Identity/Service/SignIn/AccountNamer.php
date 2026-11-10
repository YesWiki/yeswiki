<?php

namespace YesWiki\Identity\Service\SignIn;

use Symfony\Component\String\Slugger\AsciiSlugger;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Identity\SignIn\AttributeReader;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Kernel\Routing\ReservedTags;

/** Suggests the name of an account created by a sign-in: always usable, never reserved or taken. */
class AccountNamer
{
    public function __construct(private readonly FormPropertiesService $forms)
    {
    }

    /**
     * @param array<string, mixed> $attributes what the provider sent
     */
    public function suggest(ProviderConfig $config, array $attributes): string
    {
        $firstName = AttributeReader::one($attributes, $config->attributes['first_name'] ?? 'first_name');
        $lastName = AttributeReader::one($attributes, $config->attributes['last_name'] ?? 'last_name');
        $email = AttributeReader::one($attributes, $config->attributes['email'] ?? 'email');
        $subject = AttributeReader::one($attributes, $config->attributes['id'] ?? 'id');

        $candidates = [
            $this->fromTemplate($config->accountNameTemplate, $attributes),
            trim(($firstName ?? '') . ' ' . ($lastName ?? '')),
            $email !== null ? explode('@', $email)[0] : '',
            $subject ?? '',
        ];
        foreach ($candidates as $candidate) {
            // generateTag() answers `entry` for a name with no usable letter, and a suffix for a reserved one: both candidates are skipped.
            $slug = mb_strtolower((new AsciiSlugger())->slug($candidate)->toString());
            if ($slug !== '' && !ReservedTags::isReserved($slug)) {
                return $this->forms->generateTag($candidate);
            }
        }

        return $this->forms->generateTag('user');
    }

    /** @param array<string, mixed> $attributes */
    private function fromTemplate(?string $template, array $attributes): string
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
}
