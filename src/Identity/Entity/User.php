<?php

namespace YesWiki\Identity\Entity;

use ArrayAccess;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use YesWiki\Identity\Exception\UserNotAuthorizedToSetOffset;
use YesWiki\Identity\Exception\UserNotExistingOffset;

class User implements UserInterface, PasswordAuthenticatedUserInterface, \ArrayAccess
{
    // Obviously needs a group or ACLS class. In the meantime, use of $this->wiki->GetGroupACL and so on

    /* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ PROPERTIES ~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
    // User properties (cf database)
    // The case is, on purpose, similar to the one in the database
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
    // End of user properties (cf database, create-tables.sql and UserManager)

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

    /* ~~~~~~~~~~~~~~~~~~ getters ~~~~~~~~~~~~~~~~~~ */
    public function getName(): string
    {
        return $this->properties['name'];
    }

    public function getEmail(): string
    {
        return $this->properties['email'];
    }

    /* ~~~~~~~~~ implements PasswordAuthenticatedUserInterface ~~~~~~~~~~ */

    /**
     * Returns the hashed password used to authenticate the user.
     *
     * Usually on authentication, a plain-text password will be compared to this value.
     */
    public function getPassword(): ?string
    {
        return $this->properties['password'];
    }

    /* ~~~~~~~~~~~~~~~~~~ setters ~~~~~~~~~~~~~~~~~~ */
    public function setPassword(string $hashedPassword)
    {
        $this->properties['password'] = $hashedPassword;
    }

    /* ~~~~~~~~~~~~~~~~~~ implement ArrayAccess ~~~~~~~~~~~~~~~~~~ */

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

    /* ~~~~~~~~~~~~~~~~~~ implements UserInterface ~~~~~~~~~~~~~~~~~~ */

    /**
     * Returns the roles granted to the user.
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        // currently not used
        return [];
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials(): void
    {
        // not currently used
    }

    /* ~~~~~~~~~~~~~~~~~~ end of implements ~~~~~~~~~~~~~~~~~~ */

    /**
     * Returns a string representation that uniquely identifies this user.
     */
    public function getUserIdentifier(): string
    {
        return $this->getName();
    }
}
