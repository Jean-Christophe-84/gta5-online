<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'login_success')]
class LoginSuccess
{
    const string PERMISSION_LOGIN_SUCCESS_VIEW   = 'LOGIN_SUCCESS_VIEW';
    const string PERMISSION_LOGIN_SUCCESS_ADD    = 'LOGIN_SUCCESS_ADD';
    const string PERMISSION_LOGIN_SUCCESS_EDIT   = 'LOGIN_SUCCESS_EDIT';
    const string PERMISSION_LOGIN_SUCCESS_DELETE = 'LOGIN_SUCCESS_DELETE';

    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'loginSuccesses')]
    private User $user;

    #[ORM\Column(nullable: false)]
    private DateTime $date;

    #[ORM\Column(length: 256, nullable: false)]
    private string $ip;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

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
