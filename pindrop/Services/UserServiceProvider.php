<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Simp\Pindrop\Entity\User\CurrentUser;
use Simp\Pindrop\Entity\User\User;
use Symfony\Component\HttpFoundation\Request;

class UserServiceProvider
{
    private EnvServiceProvider $envProvider;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure the DI container with user services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        // Current User Service
        $builder->addDefinitions([
            'current_user' => function(ContainerInterface $container) {
                $database = $container->get('database');
                $logger = $container->get('logger');
                $request = $container->get(Request::class);
                
                if (!$request) {
                    return null;
                }
                
                $sessionId = session_id();
                if (!$sessionId) {
                    return null;
                }
                
                return CurrentUser::findBySessionId($database, $logger, $sessionId);
            },
            
            CurrentUser::class => function(ContainerInterface $container) {
                return $container->get('current_user');
            },
            
            // User repository service
            'user_repository' => function(ContainerInterface $container) {
                return new class($container->get('database'), $container->get('logger')) {
                    private $database;
                    private $logger;
                    
                    public function __construct($database, $logger)
                    {
                        $this->database = $database;
                        $this->logger = $logger;
                    }
                    
                    public function findById(int $id): ?User
                    {
                        return User::loadById($id, $this->database);
                    }
                    
                    public function findByEmail(string $email): ?User
                    {
                        return User::loadByEmail($email, $this->database);
                    }
                    
                    public function findByUsername(string $username): ?User
                    {
                        return User::loadByUsername($username, $this->database);
                    }
                    
                    public function create(array $data): User
                    {
                        $user = new User($data, $this->database);
                        return $user;
                    }
                };
            },
            
            // Authentication service
            'auth_service' => function(ContainerInterface $container) {
                return new class($container->get('database'), $container->get('logger')) {
                    private $database;
                    private $logger;
                    
                    public function __construct($database, $logger)
                    {
                        $this->database = $database;
                        $this->logger = $logger;
                    }
                    
                    public function authenticate(string $email, string $password): ?User
                    {
                        $user = User::loadByEmail($email, $this->database);
                        
                        if (!$user || !$user->verifyPassword($password)) {
                            return null;
                        }
                        
                        // Check user status
                        if ($user->getStatus() === User::STATUS_BANNED || 
                            $user->getStatus() === User::STATUS_SUSPENDED) {
                            return null;
                        }
                        
                        return $user;
                    }
                    
                    public function createSession(User $user, string $sessionId, string $ipAddress, string $userAgent): ?CurrentUser
                    {
                        $session = new CurrentUser($this->database, $this->logger);
                        $session->setUserId($user->getId());
                        $session->setSessionId($sessionId);
                        $session->setIpAddress($ipAddress);
                        $session->setUserAgent($userAgent);
                        $session->setExpiresAt((new \DateTime())->add(new \DateInterval('PT24H')));
                        
                        return $session->create() ? $session : null;
                    }
                    
                    public function revokeSession(string $sessionId): bool
                    {
                        $session = CurrentUser::findBySessionId($this->database, $this->logger, $sessionId);
                        return $session ? $session->delete() : false;
                    }
                    
                    public function revokeAllUserSessions(int $userId): int
                    {
                        return CurrentUser::revokeAllUserSessions($this->database, $this->logger, $userId);
                    }
                };
            }
        ]);
    }
}
