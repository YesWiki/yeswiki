<?php

namespace YesWiki\Content\Field;

use Field;
use Psr\Container\ContainerInterface;
use YesWiki\Identity\Service\PasswordHasherFactory;

/**
 * A password input inside a form (`mot_de_passe`).
 *
 * Values are hashed with the same factory that hashes user-account passwords --
 * PHP's current best algorithm, chosen at hash time and recorded in the hash itself.
 * This field used to call `md5()`, which has been unfit for passwords for two decades:
 * unsalted, and fast enough that a commodity GPU walks the whole plausible keyspace.
 *
 * Hashes written by older YesWikis are still md5 and stay verifiable: the factory's
 * `migrate_from` keeps the legacy hasher for checking, and `needsRehash()` says when a
 * stored value should be replaced the next time the plain password passes through.
 */
#[\Field(['mot_de_passe'])]
class PasswordField extends BazarField
{
    // ticket 18: a hash is not something anyone searches for, and matching one leaks that it is there
    use ContributesNoSearchableText;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'password';
        $this->maxChars = $this->maxChars ?? 255;
        // Here, we force not to use public read acces for password (not empty, not presence of *)
        // because, the field is not rendered and it is not waited to see it in api, or bazar templates like tablea.twig
        $this->readAccess = empty($this->readAccess) ? '%' : str_replace(['!*', '*'], ['%', '%'], $this->readAccess);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        if (!empty($value)) {
            // If a new password has been set, encode it
            return [
                $this->propertyName => $this->hash((string)$value),
                'fields-to-remove' => [$this->propertyName . '-previous'],
            ];
        }

        // If no new password was set, keep the old encoded one
        return [
            $this->propertyName => $entry[$this->propertyName . '-previous'] ?? null,
            'fields-to-remove' => [$this->propertyName . '-previous'],
        ];
    }

    public function hash(string $plainPassword): string
    {
        return $this->hasher()->hash($plainPassword);
    }

    /**
     * Whether a plain password matches a stored hash. Accepts hashes written by any
     * algorithm the factory knows, md5 included, so values stored before this field
     * stopped using md5 keep working.
     */
    public function verify(?string $hashedPassword, string $plainPassword): bool
    {
        if (empty($hashedPassword)) {
            return false;
        }

        return $this->hasher()->verify($hashedPassword, $plainPassword);
    }

    /**
     * Whether a stored hash was made by an algorithm we no longer write, and should be
     * replaced next time the plain password is available.
     */
    public function needsRehash(?string $hashedPassword): bool
    {
        return !empty($hashedPassword) && $this->hasher()->needsRehash($hashedPassword);
    }

    private function hasher(): \Symfony\Component\PasswordHasher\PasswordHasherInterface
    {
        return $this->getService(PasswordHasherFactory::class)
            ->getPasswordHasher(PasswordHasherFactory::BAZAR_FIELD);
    }

    protected function renderStatic($entry)
    {
        // We never want to display passwords
        return '';
    }
}
