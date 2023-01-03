<?php

namespace YesWiki\Core\Entity;

use DateTime;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherAwareInterface;

class CookieData implements PasswordHasherAwareInterface
{
    protected $encryptedData;
    protected $lastConnectionDate;
    protected $remember;
    protected $userName;

    public function __construct(
        string $userName,
        DateTime $lastConnectionDate,
        bool $remember,
        string $encryptedData
    ) {
        $this->encryptedData = $encryptedData;
        $this->lastConnectionDate = $lastConnectionDate;
        $this->remember = $remember;
        $this->userName = $userName;
    }

    public function getEncryptedData(): string
    {
        return $this->encryptedData;
    }

    public function getLastConnectionDate(): DateTime
    {
        return $this->lastConnectionDate;
    }

    public function getRemember(): bool
    {
        return $this->remember;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getPasswordHasherName(): ?string
    {
        return 'cookie';
    }
}
