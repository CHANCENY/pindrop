<?php

namespace Simp\Pindrop\Routing;

use Attribute;


#[Attribute]
class AttributeRoute implements AttributeRouteInterface
{

    private array $route = [];
    private ?string $route_name;

    public function __construct(string $path, string|array $method,  ...$options)
    {
        
        $allowed_method = ['post', 'get', 'put', 'delete', 'options','patch'];
        $this->route_name = isset($options['name']) ? $options['name'] : null;
        if (empty($this->route_name)) {
            throw new \Exception("Route name not given, ($path)");
        }

        $this->route['path'] = $path;
        $this->route['methods'] = is_array($method) ? 
        (!array_intersect(array_map('strtolower',$method),$allowed_method) ? [] : $method) : 
        (in_array(strtolower($method),$allowed_method) ? [$method] : []);

        if (!isset($options['controller'])) {
            throw new \Exception('Route controller is missing');
        }

        $this->route['defaults']['_controller'] = $options['controller'];
        $this->route['requirements']['_permission'] = isset($options['permission']) && is_array($options['permission']) ?
        $options['permission'] : [];
    }

	public function isValidRoute(): bool
	{
		return !empty($this->route) && !empty($this->route_name);
	}

	public function routeDefinition(): array
	{
		return $this->route;
	}

   
    public function getName(): string
    {
        return $this->route_name;
    }

}
