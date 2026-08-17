<?php

namespace YesWiki\Identity\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use YesWiki\Identity\Exception\UserNotAuthorizedToSetOffset;
use YesWiki\Identity\Exception\UserNotExistingOffset;

class User implements UserInterface, PasswordAuthenticatedUserInterface, \ArrayAccess
{
    public const PROPS_LIST = [
        'changescount',
        'doubleclickedit',
        'email',
        'motto',
        'name',
        'password',
        'revisioncount',
        'show_comments',
        'signuptime', ];
    protected $properties;

    public function __construct(array $properties)
    {
        foreach (self::PROPS_LIST as $key) {
            if (!array_key_exists($key, $properties)) {
                throw new \Exception("\$properties[$key] should be set to construct an User!");
            }
            $this->properties[$key] = $properties[$key];
        }
    }

    public function getArrayCopy(): array
    {
        return $this->properties;
    }

    public function getName(): string
    {
        return $this->properties['name'];
    }

    public function getEmail(): string
    {
        return $this->properties['email'];
    }

    /** Returns the hashed password used to authenticate the user. */
    public function getPassword(): ?string
    {
        return $this->properties['password'];
    }

    public function setPassword(string $hashedPassword)
    {
        $this->properties['password'] = $hashedPassword;
    }

    public function offsetExists($offset): bool
    {
        return in_array($offset, self::PROPS_LIST);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->offsetExists($offset)) {
            throw new UserNotExistingOffset("Not existing $offset in User!");
        }

        return $this->properties[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        if (!$this->offsetExists($offset)) {
            throw new UserNotAuthorizedToSetOffset();
        }
        $this->properties[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        throw new UserNotAuthorizedToSetOffset('unsetting offset is not allowed for User!');
    }

    /**
     * Returns the roles granted to the user.
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        return [];
    }

    /** Removes sensitive data from the user. */
    public function eraseCredentials(): void
    {
    }

    /** Returns a string representation that uniquely identifies this user. */
    public function getUserIdentifier(): string
    {
        $name = $this->getName();
        if ($name === '') {
            throw new \LogicException('a user with no name has no identifier');
        }

        return $name;
    }
}
