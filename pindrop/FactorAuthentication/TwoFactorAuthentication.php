<?php

namespace Simp\Pindrop\FactorAuthentication;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use PragmaRX\Google2FA\Google2FA;
use Simp\Pindrop\Entity\User\User;

class TwoFactorAuthentication implements TwoFactorAuthenticationInterface
{
    protected string $secret;
    protected string $accountName;
    protected string $issuer;
    protected string $code;
    protected string $email;
    protected ?ResultInterface $qrcode;

    protected Google2FA $google2FA;


    public function __construct()
    {
        $this->secret = "";
        $this->accountName = "";
        $this->issuer = "";
        $this->code = "";
        $this->email = "";
        $this->qrcode = null;
        $this->google2FA = getAppContainer()->get(Google2FA::class);
    }

    public function setSecret(string $secret): TwoFactorAuthentication
    {
        $this->secret = $secret;
        if (!empty($this->secret) && !empty($this->email) && !empty($this->issuer)) {
           $qrcode = $this->google2FA->getQRCodeUrl(
                $this->issuer,
                $this->email,
                $this->secret
            );

            $builder = new Builder(
                writer: new PngWriter(),
                data: $qrcode,

            );

            $this->qrcode = $builder->build();
        }
        return $this;
    }
    public function setAccountName(string $accountName): TwoFactorAuthentication
    {
        $this->accountName = $accountName;
        return $this;
    }
    public function setIssuer(string $issuer): TwoFactorAuthentication
    {
        $this->issuer = $issuer;
        return $this;
    }
    public function setCode(string $code): TwoFactorAuthentication
    {
        $this->code = $code;
        return $this;
    }
    public function setEmail(string $email): TwoFactorAuthentication
    {
        $this->email = $email;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'secret' => $this->secret,
            'accountName' => $this->accountName,
            'issuer' => $this->issuer,
            'code' => $this->code,
            'email' => $this->email,
            'qrcode' => $this->qrcode,
        ];
    }

    public function createSecret(): TwoFactorAuthentication
    {
        $this->secret = $this->google2FA->generateSecretKey();
        if (!empty($this->secret) && !empty($this->email) && !empty($this->issuer)) {
            $qrcode = $this->google2FA->getQRCodeUrl(
                $this->issuer,
                $this->email,
                $this->secret
            );

            $builder = new Builder(
                writer: new PngWriter(),
                data: $qrcode,

            );

            $this->qrcode = $builder->build();
        }
        return $this;
    }

    public function getQrCode(): ?ResultInterface
    {
        return $this->qrcode;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function verify(string $code): bool
    {
        return $this->google2FA->verify($code, $this->secret);
    }

    public function generateqrCode(): TwoFactorAuthentication
    {
        if (!empty($this->email) && !empty($this->issuer) && !empty($this->secret)) {
            $qrcode = $this->google2FA->getQRCodeUrl(
                $this->issuer,
                $this->email,
                $this->secret
            );

            $builder = new Builder(
                writer: new PngWriter(),
                data: $qrcode,

            );

            $this->qrcode = $builder->build();
        }
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getQrCodeUrl(): ?ResultInterface
    {
        return $this->qrcode;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }

    public function saveSecret(): bool
    {
        $user = User::loadByEmail($this->email, getAppContainer()->get('database'));
        if ($user === null) {
            return false;
        }
        $user->setTwoFactorSecret($this->secret);
        return $user->save();
    }

    public function verifyTotp(string $totp): bool
    {
        return $this->google2FA->verifyKey($this->secret, $totp);
    }
}
