<?php

namespace YesWiki\Identity\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use YesWiki\Identity\Exception\UserNotAuthorizedToSetOffset;
use YesWiki\Identity\Exception\UserNotExistingOffset;

/**
 * @implements \ArrayAccess<string, mixed>
 */
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
    /** @var array<string, mixed> */
    protected array $properties = [];

    /** @param array<string, mixed> $properties */
    public function __construct(array $properties)
    {
        foreach (self::PROPS_LIST as $key) {
            if (!array_key_exists($key, $properties)) {
                throw new \Exception("\$properties[$key] should be set to construct an User!");
            }
            $this->properties[$key] = $properties[$key];
        }
    }

    /** @return array<string, mixed> */
    public function getArrayCopy(): array
    {
        return $this->properties;
    }

    public function getName(): string
    {
        $name = $this->properties['name'];

        return is_string($name) ? $name : '';
    }

    public function getEmail(): string
    {
        $email = $this->properties['email'];

        return is_string($email) ? $email : '';
    }

    /** Returns the hashed password used to authenticate the user. */
    public function getPassword(): ?string
    {
        $password = $this->properties['password'];

        return is_string($password) ? $password : null;
    }

    public function setPassword(string $hashedPassword): void
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
        if ($offset === null || !$this->offsetExists($offset)) {
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
