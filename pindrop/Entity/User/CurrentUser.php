<?php

namespace Simp\Pindrop\Entity\User;

use DateInterval;
use DateTime;
use Exception;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Events\SystemEvents\Events;
use Simp\Pindrop\Logger\LoggerInterface;

class CurrentUser
{
    private ?int $id = null;
    private ?string $sessionId = null;
    private ?int $userId = null;
    private ?string $ipAddress = null;
    private ?string $userAgent = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $lastActivity = null;
    private ?DateTime $expiresAt = null;
    
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
        return $this->user?->getId() ?? 0;
    }

    public function id()
    {
        return $this->getId();
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
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

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getLastActivity(): ?DateTime
    {
        return $this->lastActivity;
    }

    public function setLastActivity(DateTime $lastActivity): self
    {
        $this->lastActivity = $lastActivity;
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
            $this->logger->debug('Creating user session', [
                'user_id' => $this->userId,
                'session_id' => $this->sessionId
            ]);

            $data = [
                'session_id' => $this->sessionId,
                'user_id' => $this->userId,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'expires_at' => $this->expiresAt->format('Y-m-d H:i:s')
            ];

            $this->id = $this->db->insert('user_session', $data);

            if ($this->id) {
                \appEvents()->invokeEvents(Events::AUTH_LOGIN, ['session_id' => $this->id]);
                $this->logger->info('User session created successfully', [
                    'session_id' => $this->id,
                    'user_id' => $this->userId
                ]);
                return true;
            }
            \appEvents()->invokeEvents(Events::AUTH_LOGIN_FAILED);
            return false;
        } catch (Exception $e) {
            \appEvents()->invokeEvents(Events::AUTH_LOGIN_FAILED);
            $this->logger->error('Failed to create user session', [
                'error' => $e->getMessage(),
                'user_id' => $this->userId
            ]);
            return false;
        }
    }

    public function update(): bool
    {
        if (!$this->id) {
            return false;
        }

        try {
            $this->logger->debug('Updating user session', [
                'session_id' => $this->id,
                'user_id' => $this->userId
            ]);

            $data = [
                'last_activity' => $this->lastActivity->format('Y-m-d H:i:s'),
                'expires_at' => $this->expiresAt->format('Y-m-d H:i:s')
            ];

            $affected = $this->db->update('user_session', $data, 'id' ,$this->id);

            if ($affected > 0) {
                $this->logger->debug('User session updated successfully', [
                    'session_id' => $this->id
                ]);
                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->logger->error('Failed to update user session', [
                'error' => $e->getMessage(),
                'session_id' => $this->id
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
            $this->logger->debug('Deleting user session', [
                'session_id' => $this->id,
                'user_id' => $this->userId
            ]);

            $affected = $this->db->query('delete from user_session where id = :id', id: $this->id)->rowCount();

            if ($affected > 0) {
                \appEvents()->invokeEvents(Events::AUTH_LOGOUT, ['user_id' => $this->userId]);
                $this->logger->info('User session deleted successfully', [
                    'session_id' => $this->id
                ]);
                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->logger->error('Failed to delete user session', [
                'error' => $e->getMessage(),
                'session_id' => $this->id
            ]);
            return false;
        }
    }

    // Static methods for finding sessions
    public static function findBySessionId(DatabaseService $db, LoggerInterface $logger, string $sessionId): ?self
    {
        try {
            $logger->debug('Finding user session by session_id', ['session_id' => $sessionId]);

            $sql = "SELECT * FROM user_session WHERE session_id = :session_id LIMIT 1";
            $result = $db->query($sql, session_id: $sessionId)->fetch();

            if ($result) {
                $session = new self($db, $logger);
                $session->populateFromData($result);
                $logger->debug('User session found', ['session_id' => $sessionId]);
                return $session;
            }

            $logger->debug('User session not found', ['session_id' => $sessionId]);
            return null;
        } catch (Exception $e) {
            $logger->error('Failed to find user session by session_id', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            return null;
        }
    }

    public static function findByUserId(DatabaseService $db, LoggerInterface $logger, int $userId): array
    {
        try {
            $logger->debug('Finding sessions by user_id', ['user_id' => $userId]);

            $sql = "SELECT * FROM user_session WHERE user_id = :user_id ORDER BY last_activity DESC";
            $results = $db->fetchAll($sql, ['user_id' => $userId]);

            $sessions = [];
            foreach ($results as $result) {
                $session = new self($db, $logger);
                $session->populateFromData($result);
                $sessions[] = $session;
            }

            $logger->debug('Found sessions', ['user_id' => $userId, 'count' => count($sessions)]);
            return $sessions;
        } catch (Exception $e) {
            $logger->error('Failed to find sessions by user_id', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return [];
        }
    }

    public static function cleanupExpiredSessions(DatabaseService $db, LoggerInterface $logger): int
    {
        try {
            $logger->debug('Cleaning up expired sessions');

            $sql = "DELETE FROM user_session WHERE expires_at < NOW()";
            $affected = $db->query($sql);

            $logger->info('Expired sessions cleaned up', ['count' => $affected]);
            return $affected;
        } catch (Exception $e) {
            $logger->error('Failed to cleanup expired sessions', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    public static function revokeAllUserSessions(DatabaseService $db, LoggerInterface $logger, int $userId): int
    {
        try {
            $logger->debug('Revoking all user sessions', ['user_id' => $userId]);

            $sql = "DELETE FROM user_session WHERE user_id = :user_id";
            $affected = $db->query($sql, ['user_id' => $userId]);

            $logger->info('All user sessions revoked', ['user_id' => $userId, 'count' => $affected]);
            return $affected;
        } catch (Exception $e) {
            $logger->error('Failed to revoke all user sessions', [
                'error' => $e->getMessage(),
                'user_id' => $userId
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

    public function extendSession(int $minutes = 30): bool
    {
        $this->lastActivity = new DateTime();
        $this->expiresAt = (new DateTime())->add(new DateInterval("PT{$minutes}M"));
        return $this->update();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'last_activity' => $this->lastActivity?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'is_expired' => $this->isExpired(),
            'user' => $this->getUser()?->toArray()
        ];
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function populateFromData(array $data): void
    {
        $this->id = (int) $data['id'];
        $this->sessionId = $data['session_id'];
        $this->userId = (int) $data['user_id'];
        $this->ipAddress = $data['ip_address'];
        $this->userAgent = $data['user_agent'];
        $this->createdAt = new DateTime($data['created_at']);
        $this->lastActivity = new DateTime($data['last_activity']);
        $this->expiresAt = new DateTime($data['expires_at']);
    }

    public function isLoggedIn(): bool
    {
        return !empty($this->id);
    }
}