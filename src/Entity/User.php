<?php

namespace App\Entity;

use App\Entity\Traits\InfoUpdateTrait;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[UniqueEntity(fields: ['mail'], message: 'this_mail_already_exists')]
class User implements EquatableInterface, UserInterface, PasswordAuthenticatedUserInterface, \Stringable
{
    #[ORM\Column(nullable: false)]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    public ?int $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: GroupGta::class, inversedBy: 'users')]
    private GroupGta $groupGta;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(inversedBy: 'users')]
    private Locale $locale;

    #[ORM\Column(length: 256, nullable: false)]
    private string $lastname;

    #[ORM\Column(length: 256, nullable: false)]
    private string $firstname;

    #[ORM\Column(length: 256, unique: true, nullable: false)]
    #[Assert\Email(message: 'this_mail_is_not_valid')]
    private string $mail;

    #[ORM\Column(length: 256, nullable: false)]
    private string $username;

    #[ORM\Column(length: 1024, nullable: false)]
    private string $password;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $salt = null;

    #[ORM\Column(nullable: false)]
    private bool $active;

    #[ORM\Column(type: 'json', nullable: false)]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?DateTime $lastActivity = null;

    #[ORM\Column(nullable: false)]
    private bool $verifyMail;

    #[ORM\Column(length: 256, nullable: false)]
    private string $verifyCode;

    #[ORM\Column(nullable: true)]
    private ?DateTime $verifyCodeExpiresAt = null;

    #[ORM\Column(nullable: false, options: ['default' => 0])]
    private int $verifyAttempts = 0;

    #[ORM\Column(nullable: false, options: ['default' => 1])]
    private bool $personalDatas;

    #[ORM\Column(nullable: true)]
    private ?DateTime $inactivityWarnedAt = null;

    public string $temp;

    #[ORM\OneToMany(targetEntity: BountyTargetUser::class, mappedBy: 'user')]
    public Collection $bountyTargetUsers {
        get {
            return $this->bountyTargetUsers;
        }
    }

    #[ORM\OneToMany(targetEntity: HeistKortzCenterHeistKortzCenterSecondaryLoot::class, mappedBy: 'user')]
    public Collection $heistKortzCenterHeistKortzCenterSecondaryLoots {
        get {
            return $this->heistKortzCenterHeistKortzCenterSecondaryLoots;
        }
    }

    #[ORM\OneToMany(targetEntity: LoginSuccess::class, mappedBy: 'user')]
    private Collection $loginSuccesses {
        get {
            return $this->loginSuccesses;
        }
    }

    #[ORM\OneToMany(targetEntity: PropertyUser::class, mappedBy: 'user')]
    public Collection $propertyUsers {
        get {
            return $this->propertyUsers;
        }
    }

    #[ORM\OneToMany(targetEntity: VehicleUser::class, mappedBy: 'user')]
    public Collection $vehicleUsers {
        get {
            return $this->vehicleUsers;
        }
    }

    #[ORM\OneToMany(targetEntity: WeaponUser::class, mappedBy: 'user')]
    public Collection $weaponUsers {
        get {
            return $this->weaponUsers;
        }
    }

    use InfoUpdateTrait;

    public function __construct()
    {
        $this->bountyTargetUsers                              = new ArrayCollection();
        $this->heistKortzCenterHeistKortzCenterSecondaryLoots = new ArrayCollection();
        $this->loginSuccesses                                 = new ArrayCollection();
        $this->propertyUsers                                  = new ArrayCollection();
        $this->vehicleUsers                                   = new ArrayCollection();
        $this->weaponUsers                                    = new ArrayCollection();
    }

    public function getGroupGta(): GroupGta
    {
        return $this->groupGta;
    }

    public function setGroupGta(GroupGta $groupGta): static
    {
        $this->groupGta = $groupGta;

        return $this;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getMail(): string
    {
        return $this->mail;
    }

    public function setMail(string $mail): static
    {
        $this->mail = $mail;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    #[Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function setSalt(?string $salt): static
    {
        $this->salt = $salt;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getLastActivity(): ?DateTime
    {
        return $this->lastActivity;
    }

    public function setLastActivity(): static
    {
        $this->lastActivity = new DateTime();

        return $this;
    }

    public function getVerifyMail(): bool
    {
        return $this->verifyMail;
    }

    public function setVerifyMail(bool $verifyMail): static
    {
        $this->verifyMail = $verifyMail;

        return $this;
    }

    public function getVerifyCode(): string
    {
        return $this->verifyCode;
    }

    public function setVerifyCode(string $verifyCode): static
    {
        $this->verifyCode = $verifyCode;

        return $this;
    }

    public function getVerifyCodeExpiresAt(): ?DateTime
    {
        return $this->verifyCodeExpiresAt;
    }

    public function setVerifyCodeExpiresAt(?DateTime $verifyCodeExpiresAt): static
    {
        $this->verifyCodeExpiresAt = $verifyCodeExpiresAt;

        return $this;
    }

    public function getVerifyAttempts(): int
    {
        return $this->verifyAttempts;
    }

    public function setVerifyAttempts(int $verifyAttempts): static
    {
        $this->verifyAttempts = $verifyAttempts;

        return $this;
    }

    public function getInactivityWarnedAt(): ?DateTime
    {
        return $this->inactivityWarnedAt;
    }

    public function setInactivityWarnedAt(?DateTime $inactivityWarnedAt): static
    {
        $this->inactivityWarnedAt = $inactivityWarnedAt;

        return $this;
    }

    public function getPersonalDatas(): bool
    {
        return $this->personalDatas;
    }

    public function setPersonalDatas(bool $personalDatas): static
    {
        $this->personalDatas = $personalDatas;

        return $this;
    }

    // Additional methods

    public function getPasswordEdit(): ?string
    {
        return "";
    }

    public function setPasswordEdit(?string $password): void
    {
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->mail;
    }

    /**
     * Comparé à chaque requête avec l'utilisateur rechargé en base : un compte désactivé perd sa session ouverte
     */
    #[Override]
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->getActive() && $this->getActive() && $user->getUserIdentifier() === $this->getUserIdentifier()
               && $user->getPassword() === $this->getPassword() && $user->getRoles() === $this->getRoles();
    }

    #[Override]
    public function eraseCredentials(): void
    {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->firstname . " " . $this->lastname;
    }
}
