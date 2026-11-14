<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'login_error')]
class LoginError
{
    const string PERMISSION_LOGIN_ERROR_VIEW   = 'LOGIN_ERROR_VIEW';
    const string PERMISSION_LOGIN_ERROR_ADD    = 'LOGIN_ERROR_ADD';
    const string PERMISSION_LOGIN_ERROR_EDIT   = 'LOGIN_ERROR_EDIT';
    const string PERMISSION_LOGIN_ERROR_DELETE = 'LOGIN_ERROR_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 256, nullable: false)]
    private string $email;

    #[ORM\Column(nullable: false)]
    private DateTime $date;

    #[ORM\Column(length: 256, nullable: false)]
    private string $ip;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function setDate(): static
    {
        $this->date = new DateTime();

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }
}
