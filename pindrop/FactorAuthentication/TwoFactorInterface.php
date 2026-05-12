<?php

namespace Simp\Pindrop\FactorAuthentication;

use Simp\Pindrop\Entity\User\User;
use Symfony\Component\HttpFoundation\Request;
use Twig\Markup;

interface TwoFactorInterface
{
    public function getName();

    public function getDescription();

    public function key();

    public function form(User $user): Markup;

    public function verify(Request $request, User $user): bool;

    public function redirectLink(): string;

    public function userEnablingForm(User $user, array $options = []): Markup;

    public function twoFactor(): TwoFactorAuthenticationInterface;
}
