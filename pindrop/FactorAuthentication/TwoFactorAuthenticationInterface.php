<?php

namespace Simp\Pindrop\FactorAuthentication;

use Endroid\QrCode\Writer\Result\ResultInterface;

interface TwoFactorAuthenticationInterface
{
    public function __construct();
    public function setSecret(string $secret): TwoFactorAuthenticationInterface;
    public function setAccountName(string $accountName): TwoFactorAuthenticationInterface;
    public function setIssuer(string $issuer): TwoFactorAuthenticationInterface;
    public function setCode(string $code): TwoFactorAuthenticationInterface;
    public function setEmail(string $email): TwoFactorAuthenticationInterface;
    public function toArray(): array;
    public function createSecret(): TwoFactorAuthenticationInterface;
    public function getQrCode(): ?ResultInterface;
    public function getSecret(): string;
    public function verify(string $code): bool;
    public function generateqrCode(): TwoFactorAuthenticationInterface;
    public function getEmail(): string;
    public function getQrCodeUrl(): ?ResultInterface;
    public function getIssuer(): string;
    public function getAccountName(): string;
    public function saveSecret(): bool;
    public function verifyTotp(string $totp): bool;
}
