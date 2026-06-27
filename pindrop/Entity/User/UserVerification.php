<?php

namespace Simp\Pindrop\Entity\User;

use DateTime;
use InvalidArgumentException;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Logger\LoggerInterface;

class UserVerification
{
    // Token type constants
    const string TOKEN_TYPE_EMAIL_VERIFICATION = 'email_verification';
    const string TOKEN_TYPE_PASSWORD_RESET = 'password_reset';

    private ?int $id = null;
    private ?int $userId = null;
    private ?string $tokenType = null;
    private ?string $token = null;
    private ?string $email = null;
    private ?DateTime $expiresAt = null;
    private ?bool $used = null;
    private ?DateTime $usedAt = null;
    private ?DateTime $createdAt = null;
    private ?string $ipAddress = null;
    private ?string $userAgent = null;
    
    private ?User $user = null;
    private DatabaseService $db;
    private LoggerInterface $logger;

    public function __construct(DatabaseService $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public static function currentRecentsCount(DatabaseService $database)
    {
        return $database->table('user_session')->where('last_activity', '>', (new DateTime())->sub(new \DateInterval('P5D'))->format('Y-m-d H:i:s'))->count();
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getTokenType(): ?string
    {
        return $this->tokenType;
    }

    public function setTokenType(string $tokenType): self
    {
        if (!in_array($tokenType, [self::TOKEN_TYPE_EMAIL_VERIFICATION, self::TOKEN_TYPE_PASSWORD_RESET])) {
            throw new InvalidArgumentException('Invalid token type');
        }
        $this->tokenType = $tokenType;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getExpiresAt(): ?DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): ?bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): self
    {
        $this->used = $used;
        return $this;
    }

    public function getUsedAt(): ?DateTime
    {
        return $this->usedAt;
    }

    public function setUsedAt(?DateTime $usedAt): self
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getUser(): ?User
    {
        if ($this->user === null && $this->userId !== null) {
            $this->user = User::loadById($this->userId,$this->db);
        }
        return $this->user;
    }

    // Database operations
    public function create(): bool
    {
        try {
            $this->logger->debug('Creating user verification token', [
                'user_id' => $this->userId,
                'token_type' => $this->tokenType,
                'email' => $this->email
            ]);

            $data = [
                'user_id' => $this->userId,
                'token_type' => $this->tokenType,
                'token' => $this->token,
                'email' => $this->email,
                'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
                'used' => $this->used ? 1 : 0,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent
            ];

            $this->id = $this->db->table('user_verification_tokens')->insert($data);

            if ($this->id) {
                $this->logger->info('User verification token created successfully', [
                    'token_id' => $this->id,
                    'user_id' => $this->userId,
                    'token_type' => $this->tokenType
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create user verification token', [
                'error' => $e->getMessage(),
                'user_id' => $this->userId,
                'token_type' => $this->tokenType
            ]);
            return false;
        }
    }

    public function markAsUsed(): bool
    {
        if (!$this->id) {
            return false;
        }

        try {
            $this->logger->debug('Marking verification token as used', [
                'token_id' => $this->id,
                'token_type' => $this->tokenType
            ]);

            $data = [
                'used' => 1,
                'used_at' => (new DateTime())->format('Y-m-d H:i:s')
            ];

            $affected = $this->db->table('user_verification_tokens')->where('id', '=', $this->id)->update($data);

            if ($affected > 0) {
                $this->used = true;
                $this->usedAt = new DateTime();
                $this->logger->info('Verification token marked as used', [
                    'token_id' => $this->id
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark verification token as used', [
                'error' => $e->getMessage(),
                'token_id' => $this->id
            ]);
            return false;
        }
    }

    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }

        try {
            $this->logger->debug('Deleting verification token', [
                'token_id' => $this->id,
                'token_type' => $this->tokenType
            ]);

            $affected = $this->db->table('user_verification_tokens')->where('id', '=', $this->id)->delete();

            if ($affected > 0) {
                $this->logger->info('Verification token deleted successfully', [
                    'token_id' => $this->id
                ]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete verification token', [
                'error' => $e->getMessage(),
                'token_id' => $this->id
            ]);
            return false;
        }
    }

    // Static methods for finding tokens
    public static function findByToken(DatabaseService $db, LoggerInterface $logger, string $token): ?self
    {
        try {
            $logger->debug('Finding verification token by token', ['token' => substr($token, 0, 8) . '...']);

            $result = $db->table('user_verification_tokens')->where('token', '=', $token)->first();

            if ($result) {
                $verification = new self($db, $logger);
                $verification->populateFromData($result);
                $logger->debug('Verification token found', [
                    'token_id' => $verification->getId(),
                    'token_type' => $verification->getTokenType()
                ]);
                return $verification;
            }

            $logger->debug('Verification token not found', ['token' => substr($token, 0, 8) . '...']);
            return null;
        } catch (\Exception $e) {
            $logger->error('Failed to find verification token by token', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 8) . '...'
            ]);
            return null;
        }
    }

    public static function findById(DatabaseService $db, LoggerInterface $logger, int $id): ?self
    {
        try {
            $logger->debug('Finding verification token by id', ['token' => $id]);

            $result = $db->table('user_verification_tokens')->where('id', '=', $id)->first();

            if ($result) {
                $verification = new self($db, $logger);
                $verification->populateFromData($result);
                $logger->debug('Verification token found', [
                    'token_id' => $verification->getId(),
                    'token_type' => $verification->getTokenType()
                ]);
                return $verification;
            }

            $logger->debug('Verification token not found', ['token' => $id]);
            return null;
        } catch (\Exception $e) {
            $logger->error('Failed to find verification token by token', [
                'error' => $e->getMessage(),
                'token' => $id
            ]);
            return null;
        }
    }

    public static function findByUserAndType(DatabaseService $db, LoggerInterface $logger, int $userId, string $tokenType): ?self
    {
        try {
            $logger->debug('Finding verification token by user and type', [
                'user_id' => $userId,
                'token_type' => $tokenType
            ]);

            $result = $db->table('user_verification_tokens')->where('user_id', '=', $userId)->where('token_type', '=', $tokenType)->where('used', '=', 0)->orderBy('created_at', 'DESC')->limit(1)->first();

            if ($result) {
                $verification = new self($db, $logger);
                $verification->populateFromData($result);
                $logger->debug('Verification token found', [
                    'token_id' => $verification->getId(),
                    'token_type' => $verification->getTokenType()
                ]);
                return $verification;
            }

            $logger->debug('Verification token not found', [
                'user_id' => $userId,
                'token_type' => $tokenType
            ]);
            return null;
        } catch (\Exception $e) {
            $logger->error('Failed to find verification token by user and type', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'token_type' => $tokenType
            ]);
            return null;
        }
    }

    public static function findByEmailAndType(DatabaseService $db, LoggerInterface $logger, string $email, string $tokenType): ?self
    {
        try {
            $logger->debug('Finding verification token by email and type', [
                'email' => $email,
                'token_type' => $tokenType
            ]);

            $result = $db->table('user_verification_tokens')->where('email', '=', $email)->where('token_type', '=', $tokenType)->where('used', '=', 0)->orderBy('created_at', 'DESC')->limit(1)->first();

            if ($result) {
                $verification = new self($db, $logger);
                $verification->populateFromData($result);
                $logger->debug('Verification token found', [
                    'token_id' => $verification->getId(),
                    'token_type' => $verification->getTokenType()
                ]);
                return $verification;
            }

            $logger->debug('Verification token not found', [
                'email' => $email,
                'token_type' => $tokenType
            ]);
            return null;
        } catch (\Exception $e) {
            $logger->error('Failed to find verification token by email and type', [
                'error' => $e->getMessage(),
                'email' => $email,
                'token_type' => $tokenType
            ]);
            return null;
        }
    }

    public static function cleanupExpiredTokens(DatabaseService $db, LoggerInterface $logger): int
    {
        try {
            $logger->debug('Cleaning up expired verification tokens');

            $affected = $db->table('user_verification_tokens')->where('expires_at', '<', (new DateTime())->format('Y-m-d H:i:s'))->delete();

            $logger->info('Expired verification tokens cleaned up', ['count' => $affected]);
            return $affected;
        } catch (\Exception $e) {
            $logger->error('Failed to cleanup expired verification tokens', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    public static function revokeAllUserTokens(DatabaseService $db, LoggerInterface $logger, int $userId, ?string $tokenType = null): int
    {
        try {
            $logger->debug('Revoking all user verification tokens', [
                'user_id' => $userId,
                'token_type' => $tokenType
            ]);

            if ($tokenType) {
                $affected = $db->table('user_verification_tokens')->where('user_id', '=', $userId)->where('token_type', '=', $tokenType)->delete();
            } else {
                $affected = $db->table('user_verification_tokens')->where('user_id', '=', $userId)->delete();
            }

            $logger->info('All user verification tokens revoked', [
                'user_id' => $userId,
                'token_type' => $tokenType,
                'count' => $affected
            ]);
            return $affected;
        } catch (\Exception $e) {
            $logger->error('Failed to revoke all user verification tokens', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'token_type' => $tokenType
            ]);
            return 0;
        }
    }

    // Helper methods
    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return true;
        }
        return $this->expiresAt < new DateTime();
    }

    public function isValid(): bool
    {
        return !$this->used && !$this->isExpired();
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function createEmailVerificationToken(DatabaseService $db, LoggerInterface $logger, int $userId, string $email, ?string $ipAddress = null, ?string $userAgent = null): ?self
    {
        try {
            // Revoke any existing email verification tokens for this user
            self::revokeAllUserTokens($db, $logger, $userId, self::TOKEN_TYPE_EMAIL_VERIFICATION);

            $verification = new self($db, $logger);
            $verification->setUserId($userId);
            $verification->setTokenType(self::TOKEN_TYPE_EMAIL_VERIFICATION);
            $verification->setToken(self::generateToken());
            $verification->setEmail($email);
            $verification->setExpiresAt((new DateTime())->add(new \DateInterval('PT24H'))); // 24 hours
            $verification->setUsed(false);
            $verification->setIpAddress($ipAddress);
            $verification->setUserAgent($userAgent);

            if ($verification->create()) {
                $logger->info('Email verification token created', [
                    'user_id' => $userId,
                    'email' => $email
                ]);
                return $verification;
            }

            return null;
        } catch (\Exception $e) {
            $logger->error('Failed to create email verification token', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'email' => $email
            ]);
            return null;
        }
    }

    public static function createPasswordResetToken(DatabaseService $db, LoggerInterface $logger, int $userId, string $email, ?string $ipAddress = null, ?string $userAgent = null): ?self
    {
        try {
            // Revoke any existing password reset tokens for this user
            self::revokeAllUserTokens($db, $logger, $userId, self::TOKEN_TYPE_PASSWORD_RESET);

            $verification = new self($db, $logger);
            $verification->setUserId($userId);
            $verification->setTokenType(self::TOKEN_TYPE_PASSWORD_RESET);
            $verification->setToken(self::generateToken());
            $verification->setEmail($email);
            $verification->setExpiresAt((new DateTime())->add(new \DateInterval('PT1H'))); // 1 hour
            $verification->setUsed(false);
            $verification->setIpAddress($ipAddress);
            $verification->setUserAgent($userAgent);

            if ($verification->create()) {
                $logger->info('Password reset token created', [
                    'user_id' => $userId,
                    'email' => $email
                ]);
                return $verification;
            }

            return null;
        } catch (\Exception $e) {
            $logger->error('Failed to create password reset token', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'email' => $email
            ]);
            return null;
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'token_type' => $this->tokenType,
            'token' => $this->token,
            'email' => $this->email,
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'used' => $this->used,
            'used_at' => $this->usedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'is_expired' => $this->isExpired(),
            'is_valid' => $this->isValid(),
            'user' => $this->getUser()?->toArray()
        ];
    }

    private function populateFromData(array $data): void
    {
        $this->id = (int) $data['id'];
        $this->userId = (int) $data['user_id'];
        $this->tokenType = $data['token_type'];
        $this->token = $data['token'];
        $this->email = $data['email'];
        $this->expiresAt = new DateTime($data['expires_at']);
        $this->used = (bool) $data['used'];
        $this->usedAt = $data['used_at'] ? new DateTime($data['used_at']) : null;
        $this->createdAt = new DateTime($data['created_at']);
        $this->ipAddress = $data['ip_address'];
        $this->userAgent = $data['user_agent'];
    }
}
