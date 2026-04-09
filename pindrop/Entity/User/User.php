<?php

namespace Simp\Pindrop\Entity\User;

use Simp\Pindrop\Database\DatabaseException;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Events\SystemEvents\Events;
use Simp\Pindrop\Logger\LoggerInterface;


class User
{
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_BANNED = 'banned';
    const STATUS_PENDING = 'pending';

    // Role constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_MODERATOR = 'moderator';
    const ROLE_USER = 'user';
    const ROLE_GUEST = 'guest';

    // Gender constants
    const GENDER_MALE = 'male';
    const GENDER_FEMALE = 'female';
    const GENDER_OTHER = 'other';
    const GENDER_PREFER_NOT_TO_SAY = 'prefer_not_to_say';

    // Profile visibility constants
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_FRIENDS_ONLY = 'friends_only';

    private ?int $id = null;
    private ?string $uuid = null;
    private ?string $username = null;
    private ?string $email = null;
    private ?\DateTime $emailVerifiedAt = null;
    private ?string $passwordHash = null;
    private ?string $passwordSalt = null;
    private ?string $passwordResetToken = null;
    private ?\DateTime $passwordResetExpires = null;
    private ?string $rememberToken = null;
    private ?\DateTime $rememberExpires = null;
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $fullName = null;
    private ?string $displayName = null;
    private ?string $avatarUrl = null;
    private ?string $bio = null;
    private ?string $phone = null;
    private ?\DateTime $phoneVerifiedAt = null;
    private ?string $timezone = null;
    private ?string $language = null;
    private ?string $country = null;
    private ?\DateTime $dateOfBirth = null;
    private ?string $gender = null;
    private string $status = self::STATUS_PENDING;
    private string $role = self::ROLE_USER;
    private ?array $permissions = null;
    private ?\DateTime $lastLoginAt = null;
    private ?string $lastLoginIp = null;
    private ?string $lastLoginUserAgent = null;
    private int $loginAttempts = 0;
    private int $loginCount = 0;
    private ?\DateTime $lockedUntil = null;
    private bool $emailNotifications = true;
    private bool $smsNotifications = false;
    private bool $pushNotifications = true;
    private bool $twoFactorEnabled = false;
    private ?string $twoFactorSecret = null;
    private ?array $backupCodes = null;
    private string $profileVisibility = self::VISIBILITY_PUBLIC;
    private bool $isVerified = false;
    private ?\DateTime $verifiedAt = null;
    private ?string $verificationMethod = null;
    private ?array $metadata = null;
    private ?array $preferences = null;
    private ?\DateTime $createdAt = null;
    private ?\DateTime $updatedAt = null;
    private ?\DateTime $deletedAt = null;

    private ?DatabaseService $database = null;
    private ?LoggerInterface $logger = null;

    public function __construct(array $data = [], ?DatabaseService $database = null, ?LoggerInterface $logger = null)
    {
        $this->database = $database;
        $this->logger = $logger;

        if (!empty($data)) {
            $this->fromArray($data);
        }
    }

    // Static factory methods
    public static function loadById(int $id, ?DatabaseService $database = null): ?self
    {
        $instance = new self([], $database);
        return $instance->loadByIdInstance($id);
    }

    public static function loadByUuid(string $uuid, ?DatabaseService $database = null): ?self
    {
        $instance = new self([], $database);
        return $instance->loadByUuidInstance($uuid);
    }

    public static function loadByUsername(string $username, ?DatabaseService $database = null): ?self
    {
        $instance = new self([], $database);
        return $instance->loadByUsernameInstance($username);
    }

    public static function loadByEmail(string $email, ?DatabaseService $database = null): ?self
    {
        $instance = new self([], $database);
        return $instance->loadByEmailInstance($email);
    }

    public static function loadByPasswordResetToken(string $token, ?DatabaseService $database = null): ?self
    {
        $instance = new self([], $database);
        return $instance->loadByPasswordResetTokenInstance($token);
    }
    
    /**
     * Load all users from database
     */
    public static function loadAll(?DatabaseService $database = null): array
    {
        $instance = new self([], $database);
        $sql = "SELECT * FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC";
        $data = $instance->database->fetchAll($sql);
        
        $users = [];
        foreach ($data as $userData) {
            $user = new self([], $database);
            $user->fromArray($userData);
            $users[] = $user;
        }
        
        return $users;
    }
    
    /**
     * Load users with pagination
     */
    public static function loadWithPagination(int $page = 1, int $limit = 20, ?DatabaseService $database = null): array
    {
        $instance = new self([], $database, getAppContainer()->get('logger'));
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM users";
        $totalData = $instance->database->fetch($countSql);
        $total = $totalData['total'] ?? 0;
        
        // Get users for current page
        $sql = "SELECT * FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $data = $instance->database->fetchAll($sql);

        $users = [];
        foreach ($data as $userData) {
            $user = new self([], $database, getAppContainer()->get('logger'));
            $user->fromArray($userData);
            $users[] = $user;
        }
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    // Instance loader methods

    /**
     * @throws DatabaseException
     */
    public function loadByIdInstance(int $id): ?self
    {
        $sql = "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL";
        $data = $this->database->fetch($sql, $id);
        
        if ($data) {
            $this->fromArray($data);
            return $this;
        }
        
        return null;
    }

    public function loadByUuidInstance(string $uuid): ?self
    {
        $sql = "SELECT * FROM users WHERE uuid = ? AND deleted_at IS NULL";
        $data = $this->database->fetch($sql, $uuid);
        
        if ($data) {
            $this->fromArray($data);
            return $this;
        }
        
        return null;
    }

    public function loadByUsernameInstance(string $username): ?self
    {
        $sql = "SELECT * FROM users WHERE username = ? AND deleted_at IS NULL";
        $data = $this->database->fetch($sql, $username);
        
        if ($data) {
            $this->fromArray($data);
            return $this;
        }
        
        return null;
    }

    public function loadByEmailInstance(string $email): ?self
    {
        $sql = "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL";
        $data = $this->database->fetch($sql, $email);
        
        if ($data) {
            $this->fromArray($data);
            return $this;
        }
        
        return null;
    }

    public function loadByPasswordResetTokenInstance(string $token): ?self
    {
        $sql = "SELECT * FROM users WHERE password_reset_token = ? AND deleted_at IS NULL AND password_reset_expires > NOW()";
        $data = $this->database->fetch($sql, $token);
        
        if ($data) {
            $this->fromArray($data);
            return $this;
        }
        
        return null;
    }

    // CRUD operations
    public static function count(DatabaseService $database)
    {
        return $database->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL")->fetchColumn();

    }

    public function save(): bool
    {
        if ($this->id) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    public function delete(): bool
    {
        if (!$this->id) {
            return false;
        }

        $this->deletedAt = new \DateTime();
        \appEvents()->invokeEvents(Events::USER_DELETING, ['uid' => $this->id, 'force'=>false]);
        return $this->update();
    }

    public function forceDelete(): bool
    {
        if (!$this->id) {
            return false;
        }

        $sql = "DELETE FROM users WHERE id = ?";
        \appEvents()->invokeEvents(Events::USER_DELETING, ['uid' => $this->id, 'force'=>true]);
        $result = $this->database->query($sql, $this->id);
        
        if ($this->logger) {
            $this->logger->info('User force deleted', [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'username' => $this->username
            ]);
        }

        if ($result) {
            \appEvents()->invokeEvents(Events::USER_DELETED, ['uid' => $this->id]);
        }
        
        return $result !== false;
    }

    // Private CRUD methods
    private function insert(): bool
    {
        $this->uuid = $this->uuid ?? $this->generateUuid();
        $this->createdAt = $this->createdAt ?? new \DateTime();
        $this->updatedAt = new \DateTime();

        // Auto-generate full name if not set
        if (!$this->fullName && ($this->firstName || $this->lastName)) {
            $this->fullName = trim($this->firstName . ' ' . $this->lastName);
        }

        $data = [
            'uuid' => $this->uuid,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'password_hash' => $this->passwordHash,
            'password_salt' => $this->passwordSalt,
            'password_reset_token' => $this->passwordResetToken,
            'password_reset_expires' => $this->passwordResetExpires?->format('Y-m-d H:i:s'),
            'remember_token' => $this->rememberToken,
            'remember_expires' => $this->rememberExpires?->format('Y-m-d H:i:s'),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'display_name' => $this->displayName,
            'avatar_url' => $this->avatarUrl,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'phone_verified_at' => $this->phoneVerifiedAt?->format('Y-m-d H:i:s'),
            'timezone' => $this->timezone,
            'language' => $this->language,
            'country' => $this->country,
            'date_of_birth' => $this->dateOfBirth?->format('Y-m-d'),
            'gender' => $this->gender,
            'status' => $this->status,
            'role' => $this->role,
            'permissions' => $this->permissions ? json_encode($this->permissions) : null,
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->lastLoginIp,
            'last_login_user_agent' => $this->lastLoginUserAgent,
            'login_attempts' => $this->loginAttempts,
            'locked_until' => $this->lockedUntil?->format('Y-m-d H:i:s'),
            'email_notifications' => $this->emailNotifications ? 1 : 0,
            'sms_notifications' => $this->smsNotifications ? 1 : 0,
            'push_notifications' => $this->pushNotifications ? 1 : 0,
            'two_factor_enabled' => $this->twoFactorEnabled ? 1 : 0,
            'two_factor_secret' => $this->twoFactorSecret,
            'backup_codes' => $this->backupCodes ? json_encode($this->backupCodes) : null,
            'profile_visibility' => $this->profileVisibility,
            'is_verified' => $this->isVerified ? 1 : 0,
            'verified_at' => $this->verifiedAt?->format('Y-m-d H:i:s'),
            'verification_method' => $this->verificationMethod,
            'metadata' => $this->metadata ? json_encode($this->metadata) : null,
            'preferences' => $this->preferences ? json_encode($this->preferences) : null,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s')
        ];

        \appEvents()->invokeEvents(Events::USER_CREATING, ['user' => &$data]);

        $this->id = $this->database->insert('users', $data);
        
        if ($this->id) {
            if ($this->logger) {
                $this->logger->info('User created', [
                    'id' => $this->id,
                    'uuid' => $this->uuid,
                    'username' => $this->username,
                    'email' => $this->email
                ]);
            }
            \appEvents()->invokeEvents(Events::USER_CREATED, ['user' => $data]);
            return true;
        }

        return false;
    }

    private function update(): bool
    {
        if (!$this->id) {
            return false;
        }

        $this->updatedAt = new \DateTime();

        // Auto-generate full name if not set
        if (!$this->fullName && ($this->firstName || $this->lastName)) {
            $this->fullName = trim($this->firstName . ' ' . $this->lastName);
        }

        $data = [
            'uuid' => $this->uuid,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'password_hash' => $this->passwordHash,
            'password_salt' => $this->passwordSalt,
            'password_reset_token' => $this->passwordResetToken,
            'password_reset_expires' => $this->passwordResetExpires?->format('Y-m-d H:i:s'),
            'remember_token' => $this->rememberToken,
            'remember_expires' => $this->rememberExpires?->format('Y-m-d H:i:s'),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'display_name' => $this->displayName,
            'avatar_url' => $this->avatarUrl,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'phone_verified_at' => $this->phoneVerifiedAt?->format('Y-m-d H:i:s'),
            'timezone' => $this->timezone,
            'language' => $this->language,
            'country' => $this->country,
            'date_of_birth' => $this->dateOfBirth?->format('Y-m-d'),
            'gender' => $this->gender,
            'status' => $this->status,
            'role' => $this->role,
            'permissions' => $this->permissions ? json_encode($this->permissions) : null,
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->lastLoginIp,
            'last_login_user_agent' => $this->lastLoginUserAgent,
            'login_attempts' => $this->loginAttempts,
            'locked_until' => $this->lockedUntil?->format('Y-m-d H:i:s'),
            'email_notifications' => $this->emailNotifications ? 1 : 0,
            'sms_notifications' => $this->smsNotifications ? 1 : 0,
            'push_notifications' => $this->pushNotifications ? 1 : 0,
            'two_factor_enabled' => $this->twoFactorEnabled ? 1 : 0,
            'two_factor_secret' => $this->twoFactorSecret,
            'backup_codes' => $this->backupCodes ? json_encode($this->backupCodes) : null,
            'profile_visibility' => $this->profileVisibility,
            'is_verified' => $this->isVerified ? 1 : 0,
            'verified_at' => $this->verifiedAt?->format('Y-m-d H:i:s'),
            'verification_method' => $this->verificationMethod,
            'metadata' => $this->metadata ? json_encode($this->metadata) : null,
            'preferences' => $this->preferences ? json_encode($this->preferences) : null,
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s')
        ];

        \appEvents()->invokeEvents(Events::USER_UPDATING, ['user' => &$data]);

        $result = $this->database->update('users', $data, 'id = ?', $this->id);
        
        if ($this->logger) {
            $this->logger->info('User updated', [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'username' => $this->username
            ]);
        }

        if ( $result > 0) {
            \appEvents()->invokeEvents(Events::USER_UPDATED, ['user' => $data]);
        }
        return $result > 0;
    }

    // Utility methods
    public function fromArray(array $data): void
    {
        // Pre-process JSON fields
        $jsonFields = ['permissions', 'metadata', 'preferences', 'backup_codes'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }

        // Pre-process datetime fields
        $datetimeFields = [
            'email_verified_at', 'password_reset_expires', 'remember_expires',
            'phone_verified_at', 'last_login_at', 'locked_until', 'verified_at',
            'created_at', 'updated_at', 'deleted_at'
        ];
        foreach ($datetimeFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = new \DateTime($data[$field]);
            }
        }

        // Pre-process date field
        if (isset($data['date_of_birth']) && is_string($data['date_of_birth'])) {
            $data['date_of_birth'] = new \DateTime($data['date_of_birth']);
        }

        // Set properties
        foreach ($data as $key => $value) {
            $property = $this->camelCase($key);
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'password_hash' => $this->passwordHash,
            'password_salt' => $this->passwordSalt,
            'password_reset_token' => $this->passwordResetToken,
            'password_reset_expires' => $this->passwordResetExpires?->format('Y-m-d H:i:s'),
            'remember_token' => $this->rememberToken,
            'remember_expires' => $this->rememberExpires?->format('Y-m-d H:i:s'),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'display_name' => $this->displayName,
            'avatar_url' => $this->avatarUrl,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'phone_verified_at' => $this->phoneVerifiedAt?->format('Y-m-d H:i:s'),
            'timezone' => $this->timezone,
            'language' => $this->language,
            'country' => $this->country,
            'date_of_birth' => $this->dateOfBirth?->format('Y-m-d'),
            'gender' => $this->gender,
            'status' => $this->status,
            'role' => $this->role,
            'permissions' => $this->permissions,
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->lastLoginIp,
            'last_login_user_agent' => $this->lastLoginUserAgent,
            'login_attempts' => $this->loginAttempts,
            'locked_until' => $this->lockedUntil?->format('Y-m-d H:i:s'),
            'email_notifications' => $this->emailNotifications ? 1 : 0,
            'sms_notifications' => $this->smsNotifications ? 1 : 0,
            'push_notifications' => $this->pushNotifications ? 1 : 0,
            'two_factor_enabled' => $this->twoFactorEnabled ? 1 : 0,
            'two_factor_secret' => $this->twoFactorSecret,
            'backup_codes' => $this->backupCodes,
            'profile_visibility' => $this->profileVisibility,
            'is_verified' => $this->isVerified ? 1 : 0,
            'verified_at' => $this->verifiedAt?->format('Y-m-d H:i:s'),
            'verification_method' => $this->verificationMethod,
            'metadata' => $this->metadata,
            'preferences' => $this->preferences,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s')
        ];
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function camelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    // Authentication methods
    public function verifyPassword(string $password): bool
    {
        if (!$this->passwordHash) {
            return false;
        }

        if ($this->passwordSalt) {
            $password .= $this->passwordSalt;
        }

        return password_verify($password, $this->passwordHash);
    }

    public function setPassword(string $password): void
    {
        $this->passwordSalt = bin2hex(random_bytes(16));
        $passwordWithSalt = $password . $this->passwordSalt;
        $this->passwordHash = password_hash($passwordWithSalt, PASSWORD_DEFAULT);
    }

    public function generatePasswordResetToken(): string
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetExpires = new \DateTime('+1 hour');
        return $this->passwordResetToken;
    }

    public function clearPasswordResetToken(): void
    {
        $this->passwordResetToken = null;
        $this->passwordResetExpires = null;
    }

    public function generateRememberToken(): string
    {
        $this->rememberToken = bin2hex(random_bytes(32));
        $this->rememberExpires = new \DateTime('+30 days');
        return $this->rememberToken;
    }

    public function clearRememberToken(): void
    {
        $this->rememberToken = null;
        $this->rememberExpires = null;
    }

    public function recordLogin(string $ip, string $userAgent): void
    {
        $this->lastLoginAt = new \DateTime();
        $this->lastLoginIp = $ip;
        $this->lastLoginUserAgent = $userAgent;
        $this->loginAttempts = 0;
        $this->lockedUntil = null;
    }

    public function recordFailedLogin(): void
    {
        $this->loginAttempts++;
        
        // Lock account after 5 failed attempts for 30 minutes
        if ($this->loginAttempts >= 5) {
            $this->lockedUntil = new \DateTime('+30 minutes');
        }
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil && $this->lockedUntil > new \DateTime();
    }

    public function canLogin(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->isLocked();
    }

    // Permission methods
    public function hasPermission(string $permission): bool
    {
        // Super admin has all permissions
        if ($this->role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        // Check role-based permissions
        $rolePermissions = $this->getRolePermissions();
        if (in_array($permission, $rolePermissions)) {
            return true;
        }

        // Check custom permissions
        return $this->permissions && in_array($permission, $this->permissions);
    }

    private function getRolePermissions(): array
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => ['*'],
            self::ROLE_ADMIN => ['users.manage', 'content.manage', 'system.manage'],
            self::ROLE_MODERATOR => ['content.moderate', 'users.view'],
            self::ROLE_USER => ['content.create', 'content.edit_own'],
            default => []
        };
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public function isModerator(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_MODERATOR]);
    }

    // Email verification
    public function verifyEmail(): void
    {
        $this->emailVerifiedAt = new \DateTime();
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    // Phone verification
    public function verifyPhone(): void
    {
        $this->phoneVerifiedAt = new \DateTime();
    }

    public function isPhoneVerified(): bool
    {
        return $this->phoneVerifiedAt !== null;
    }

    // Account verification
    public function verifyAccount(?string $method = null): void
    {
        $this->isVerified = true;
        $this->verifiedAt = new \DateTime();
        $this->verificationMethod = $method;
    }

    // Two-factor authentication
    public function enableTwoFactor(string $secret): void
    {
        $this->twoFactorEnabled = true;
        $this->twoFactorSecret = $secret;
    }

    public function disableTwoFactor(): void
    {
        $this->twoFactorEnabled = false;
        $this->twoFactorSecret = null;
        $this->backupCodes = null;
    }

    public function generateBackupCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }
        $this->backupCodes = $codes;
        return $codes;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getUuid(): ?string { return $this->uuid; }
    public function getUsername(): ?string { return $this->username; }
    public function getEmail(): ?string { return $this->email; }
    public function getEmailVerifiedAt(): ?\DateTime { return $this->emailVerifiedAt; }
    public function getPasswordHash(): ?string { return $this->passwordHash; }
    public function getPasswordSalt(): ?string { return $this->passwordSalt; }
    public function getPasswordResetToken(): ?string { return $this->passwordResetToken; }
    public function getPasswordResetExpires(): ?\DateTime { return $this->passwordResetExpires; }
    public function getRememberToken(): ?string { return $this->rememberToken; }
    public function getRememberExpires(): ?\DateTime { return $this->rememberExpires; }
    public function getFirstName(): ?string { return $this->firstName; }
    public function getLastName(): ?string { return $this->lastName; }
    public function getFullName(): ?string { return $this->fullName; }
    public function getDisplayName(): ?string { return $this->displayName ?? $this->username; }
    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function getBio(): ?string { return $this->bio; }
    public function getPhone(): ?string { return $this->phone; }
    public function getPhoneVerifiedAt(): ?\DateTime { return $this->phoneVerifiedAt; }
    public function getTimezone(): ?string { return $this->timezone; }
    public function getLanguage(): ?string { return $this->language; }
    public function getCountry(): ?string { return $this->country; }
    public function getDateOfBirth(): ?\DateTime { return $this->dateOfBirth; }
    public function getGender(): ?string { return $this->gender; }
    public function getStatus(): string { return $this->status; }
    public function getRole(): string { return $this->role; }
    public function getPermissions(): ?array { return $this->permissions; }
    public function getLastLoginAt(): ?\DateTime { return $this->lastLoginAt; }
    public function getLastLoginIp(): ?string { return $this->lastLoginIp; }
    public function getLastLoginUserAgent(): ?string { return $this->lastLoginUserAgent; }
    public function getLoginAttempts(): int { return $this->loginAttempts; }
    public function getLoginCount(): int { return $this->loginCount; }
    public function getLockedUntil(): ?\DateTime { return $this->lockedUntil; }
    public function getEmailNotifications(): bool { return $this->emailNotifications; }
    public function getSmsNotifications(): bool { return $this->smsNotifications; }
    public function getPushNotifications(): bool { return $this->pushNotifications; }
    public function getTwoFactorEnabled(): bool { return $this->twoFactorEnabled; }
    public function getTwoFactorSecret(): ?string { return $this->twoFactorSecret; }
    public function getBackupCodes(): ?array { return $this->backupCodes; }
    public function getProfileVisibility(): string { return $this->profileVisibility; }
    public function getIsVerified(): bool { return $this->isVerified; }
    public function isVerified(): bool { return $this->isVerified; }
    public function getVerifiedAt(): ?\DateTime { return $this->verifiedAt; }
    public function getVerificationMethod(): ?string { return $this->verificationMethod; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function getPreferences(): ?array { return $this->preferences; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function getDeletedAt(): ?\DateTime { return $this->deletedAt; }

    public function setUsername(string $username): void { $this->username = $username; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function setEmailVerifiedAt(?\DateTime $emailVerifiedAt): void { $this->emailVerifiedAt = $emailVerifiedAt; }
    public function setPasswordHash(?string $passwordHash): void { $this->passwordHash = $passwordHash; }
    public function setPasswordSalt(?string $passwordSalt): void { $this->passwordSalt = $passwordSalt; }
    public function setPasswordResetToken(?string $passwordResetToken): void { $this->passwordResetToken = $passwordResetToken; }
    public function setPasswordResetExpires(?\DateTime $passwordResetExpires): void { $this->passwordResetExpires = $passwordResetExpires; }
    public function setRememberToken(?string $rememberToken): void { $this->rememberToken = $rememberToken; }
    public function setRememberExpires(?\DateTime $rememberExpires): void { $this->rememberExpires = $rememberExpires; }
    public function setFirstName(?string $firstName): void { $this->firstName = $firstName; }
    public function setLastName(?string $lastName): void { $this->lastName = $lastName; }
    public function setFullName(?string $fullName): void { $this->fullName = $fullName; }
    public function setDisplayName(?string $displayName): void { $this->displayName = $displayName; }
    public function setAvatarUrl(?string $avatarUrl): void { $this->avatarUrl = $avatarUrl; }
    public function setBio(?string $bio): void { $this->bio = $bio; }
    public function setPhone(?string $phone): void { $this->phone = $phone; }
    public function setPhoneVerifiedAt(?\DateTime $phoneVerifiedAt): void { $this->phoneVerifiedAt = $phoneVerifiedAt; }
    public function setTimezone(?string $timezone): void { $this->timezone = $timezone; }
    public function setLanguage(?string $language): void { $this->language = $language; }
    public function setCountry(?string $country): void { $this->country = $country; }
    public function setDateOfBirth(?\DateTime $dateOfBirth): void { $this->dateOfBirth = $dateOfBirth; }
    public function setGender(?string $gender): void { $this->gender = $gender; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function setRole(string $role): void { $this->role = $role; }
    public function setPermissions(?array $permissions): void { $this->permissions = $permissions; }
    public function setLastLoginAt(?\DateTime $lastLoginAt): void { $this->lastLoginAt = $lastLoginAt; }
    public function setLastLoginIp(?string $lastLoginIp): void { $this->lastLoginIp = $lastLoginIp; }
    public function setLastLoginUserAgent(?string $lastLoginUserAgent): void { $this->lastLoginUserAgent = $lastLoginUserAgent; }
    public function setLoginAttempts(int $loginAttempts): void { $this->loginAttempts = $loginAttempts; }
    public function setLoginCount(int $loginCount): void { $this->loginCount = $loginCount; }
    public function setLockedUntil(?\DateTime $lockedUntil): void { $this->lockedUntil = $lockedUntil; }
    public function setEmailNotifications(bool $emailNotifications): void { $this->emailNotifications = $emailNotifications; }
    public function setSmsNotifications(bool $smsNotifications): void { $this->smsNotifications = $smsNotifications; }
    public function setPushNotifications(bool $pushNotifications): void { $this->pushNotifications = $pushNotifications; }
    public function setTwoFactorEnabled(bool $twoFactorEnabled): void { $this->twoFactorEnabled = $twoFactorEnabled; }
    public function setTwoFactorSecret(?string $twoFactorSecret): void { $this->twoFactorSecret = $twoFactorSecret; }
    public function setBackupCodes(?array $backupCodes): void { $this->backupCodes = $backupCodes; }
    public function setProfileVisibility(string $profileVisibility): void { $this->profileVisibility = $profileVisibility; }
    public function setIsVerified(bool $isVerified): void { $this->isVerified = $isVerified; }
    public function setVerifiedAt(?\DateTime $verifiedAt): void { $this->verifiedAt = $verifiedAt; }
    public function setVerificationMethod(?string $verificationMethod): void { $this->verificationMethod = $verificationMethod; }
    public function setMetadata(?array $metadata): void { $this->metadata = $metadata; }
    public function setPreferences(?array $preferences): void { $this->preferences = $preferences; }
    public function setCreatedAt(?\DateTime $createdAt): void { $this->createdAt = $createdAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): void { $this->updatedAt = $updatedAt; }
    public function setDeletedAt(?\DateTime $deletedAt): void { $this->deletedAt = $deletedAt; }

    public function __toString(): string
    {
        return "{$this->email} ({$this->id})";
    }
}