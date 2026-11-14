<?php

declare(strict_types=1);

namespace App\Twig;

use App\Helper\UserHelper;
use Twig\Attribute\AsTwigFilter;

class UserExtension
{
    public function __construct(private readonly UserHelper $userHelper)
    {
    }

    #[AsTwigFilter(name: 'role', isSafe: ['html'])]
    public function getRole(array|string|null $object): string
    {
        return $this->userHelper->getRole($object);
    }
}
