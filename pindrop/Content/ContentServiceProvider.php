<?php

declare(strict_types=1);

namespace Simp\Pindrop\Content;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Simp\Pindrop\Content\Storage\ContentEntityInterface;
use Simp\Pindrop\Content\Storage\StorageEntity;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Services\EnvServiceProvider;

class ContentServiceProvider
{
    private EnvServiceProvider $envProvider;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure the DI container with content services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $builder->addDefinitions([

            // Content Factory Service
            'content.factory' => function(ContainerInterface $container) {
                return new class($container->get('database'), $container->get('logger'), $container) {
                    public function __construct(protected $database, protected $logger, protected ContainerInterface $container){}
                    
                    public function storage(string $classNameOrEntityName)
                    {
                        $className = $classNameOrEntityName;
                        
                        // Check if it's an entity name (not a full class name)
                        if (!class_exists($classNameOrEntityName)) {
                            // Get entity from content.repository service
                            $repository = $this->container->get('content.repository');
                            $entityData = $repository->get($classNameOrEntityName);
                            
                            if (!$entityData) {
                                throw new \InvalidArgumentException("Entity '{$classNameOrEntityName}' not found in repository");
                            }
                            
                            $className = $entityData['class'];
                        }
                        
                        // Validate that the class exists and extends StorageEntity
                        if (!class_exists($className)) {
                            throw new \InvalidArgumentException("Class {$className} does not exist");
                        }
                        
                        if (!is_subclass_of($className, \Simp\Pindrop\Content\Storage\StorageEntity::class)) {
                            throw new \InvalidArgumentException("Class {$className} must extend StorageEntity");
                        }
                        
                        // Return a new instance of the given class
                        return new $className($this->database, $this->logger);
                    }
                };
            },

            // Content Repository Service
            'content.repository' => function(ContainerInterface $container) {
                return new class($container->get('database'), $container->get('logger'), $container) {
                    private array $entities;

                    public function __construct(protected DatabaseService $database, protected $logger, protected ContainerInterface $container)
                    {
                        // Get entity classes from PluginManager
                        $pluginManager = $container->get('plugin.manager');
                        $this->entities = $pluginManager->getEntityClasses();
                    }

                    public function getAll(): array
                    {
                        return $this->entities;
                    }

                    public function get(string $entityName): ?array
                    {
                        return $this->entities[$entityName] ?? null;
                    }

                    public function has(string $entityName): bool
                    {
                        return isset($this->entities[$entityName]);
                    }

                    public function getClass(string $entityName): ?string
                    {
                        return $this->entities[$entityName]['class'] ?? null;
                    }

                    public function getConfig(string $entityName): ?array
                    {
                        return $this->entities[$entityName]['config'] ?? null;
                    }

                    public function findBy(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
                    {
                        try {
                            $sql = "SELECT * FROM node_data WHERE 1=1";
                            $params = [];
                            
                            // Build WHERE clause
                            foreach ($criteria as $key => $value) {
                                if (is_array($value)) {
                                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                                    $sql .= " AND {$key} IN ({$placeholders})";
                                    $params = array_merge($params, $value);
                                } else {
                                    $sql .= " AND {$key} = ?";
                                    $params[] = $value;
                                }
                            }
                            
                            // Build ORDER BY clause
                            if (!empty($orderBy)) {
                                $sql .= " ORDER BY";
                                foreach ($orderBy as $column => $direction) {
                                    $sql .= " {$column} " . strtoupper($direction);
                                    if (next($orderBy) !== false) {
                                        $sql .= ",";
                                    }
                                }
                            }
                            
                            // Add LIMIT and OFFSET
                            if ($limit !== null) {
                                $sql .= " LIMIT ?";
                                $params[] = $limit;
                            }
                            
                            if ($offset !== null) {
                                $sql .= " OFFSET ?";
                                $params[] = $offset;
                            }

                            $stmt = $this->database->query($sql, ...$params);

                            $results = [];
                            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                                $results[] = $row;
                            }
                            
                            return array_map(function($row) {
                                return $this->find($row['id']);
                            }, $results);
                            
                        } catch (\Exception $e) {
                            $this->logger->error('Error in findBy: ' . $e->getMessage());
                            return [];
                        }
                    }

                    public function find(int $id): ?ContentEntityInterface
                    {
                        $sql = "SELECT node_type FROM node_data WHERE id = :id";
                        $stmt = $this->database->query($sql, $id);
                        $result = $stmt->fetchColumn();
                        $entity = $this->getClass($result) ?? null;
                        if (!empty($entity) && class_exists($entity)) {

                            $entity = $this->container->get($entity);
                            if ($entity instanceof ContentEntityInterface) {
                                return $entity->find($id);
                            }
                        }
                        return null;
                    }
                };
            }

        ]);
    }
}
