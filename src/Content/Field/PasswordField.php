<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Identity\Security\LegacyPasswordHash;
use YesWiki\Identity\Service\PasswordHasherFactory;

/** A password input inside a form (`mot_de_passe`). */
#[\Field(['mot_de_passe'])]
class PasswordField extends BazarField
{
    use ContributesNoSearchableText;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->type = 'password';
        $this->maxChars = $this->maxChars ?? 255;

        $this->readAccess = empty($this->readAccess) ? '%' : str_replace(['!*', '*'], ['%', '%'], $this->readAccess);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        if (!empty($value)) {
            return [
                $this->propertyName => $this->hash((string)$value),
                'fields-to-remove' => [$this->propertyName . '-previous'],
            ];
        }

        return [
            $this->propertyName => $entry[$this->propertyName . '-previous'] ?? null,
            'fields-to-remove' => [$this->propertyName . '-previous'],
        ];
    }

    public function hash(string $plainPassword): string
    {
        return $this->hasher()->hash($plainPassword);
    }

    /** Whether a plain password matches a stored hash. */
    public function verify(?string $hashedPassword, string $plainPassword): bool
    {
        if (empty($hashedPassword) || LegacyPasswordHash::isMd5($hashedPassword)) {
            return false;
        }

        return $this->hasher()->verify($hashedPassword, $plainPassword);
    }

    /**
     * Whether a stored hash was made by an algorithm we no longer write, and should be replaced next time the plain password is available.
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
        return '';
    }
}
