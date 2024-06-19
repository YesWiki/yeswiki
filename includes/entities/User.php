<?php

namespace YesWiki\Core\Entity;

use ArrayAccess;
use Exception;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use YesWiki\Core\Exception\UserNotAuthorizedToSetOffset;
use YesWiki\Core\Exception\UserNotExistingOffset;

class User implements UserInterface, PasswordAuthenticatedUserInterface, ArrayAccess
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
                throw new Exception("\$properties[$key] should be set to construct an User!");
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

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
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
     *     public function getRoles()
     *     {
     *         return ['ROLE_USER'];
     *     }
     *
     * Alternatively, the roles might be stored in a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return string[]
     */
    public function getRoles()
    {
        // currently not used
        return [];
    }

    /**
     * Returns the salt that was originally used to hash the password.
     *
     * This can return null if the password was not hashed using a salt.
     *
     * This method is deprecated since Symfony 5.3, implement it from {@link LegacyPasswordAuthenticatedUserInterface} instead.
     *
     * @return string|null
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
        // not currently used
    }

    /**
     * @return string
     *
     * @deprecated since Symfony 5.3, use getUserIdentifier() instead
     */
    public function getUsername()
    {
        return $this->getUserIdentifier();
    }

    /* ~~~~~~~~~~~~~~~~~~ end of implements ~~~~~~~~~~~~~~~~~~ */

    /**
     * @return string
     */
    public function getUserIdentifier()
    {
        return $this->getName();
    }
}
