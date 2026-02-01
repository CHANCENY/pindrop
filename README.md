# Pindrop

Pindrop is a modern PHP web application framework designed for building modular, plugin-driven applications with ease and flexibility.

## Overview

Pindrop provides a solid foundation for developing web applications that can grow with your needs. It features a powerful plugin system, dependency injection container, routing system, and modern PHP practices out of the box.

## Key Features

### Plugin System
- **Modular Architecture**: Build functionality as independent plugins
- **Hot Loading**: Automatically discover and load plugins from the modules directory
- **Service Integration**: Each plugin can register its own services
- **Route Registration**: Plugins define their own routes in simple YAML files
- **State Management**: Enable/disable plugins without code changes

### Routing System
- **Modern Router**: Built on simp/router package for reliable request handling
- **Plugin Integration**: Automatically loads routes from all enabled plugins
- **Flexible Configuration**: Support for controllers, forms, and middleware
- **URL Generation**: Generate URLs from route names
- **Statistics**: Track route usage and performance

### Dependency Injection
- **PHP-DI Integration**: Modern dependency injection container
- **Service Providers**: Organized service registration system
- **Auto-wiring**: Automatic dependency resolution
- **Configuration Management**: Environment-based configuration

### Controller Base
- **Standard Interface**: Consistent controller method signatures
- **Helper Methods**: Common response generation (render, json, redirect)
- **Request Handling**: Built-in Symfony HttpFoundation support
- **Extensible**: Easy to extend with custom functionality

## Quick Start

1. **Install Dependencies**: Run `composer install` to get all required packages
2. **Configure Environment**: Copy `.env.example` to `.env` and update settings
3. **Create Plugin**: Add your functionality as a plugin in the `modules/` directory
4. **Define Routes**: Add routes in your plugin's `routing.yml` file
5. **Build Controllers**: Extend `ControllerBase` for your plugin logic

## Plugin Structure

Each plugin follows a simple structure:

```
modules/
└── your-plugin/
    ├── info.yml          # Plugin metadata
    ├── services.yml       # Plugin services
    ├── routing.yml        # Plugin routes
    └── src/
        ├── Controller/
        │   └── YourController.php
        └── Form/
            └── YourForm.php
```

## Configuration

Pindrop uses environment variables for configuration:

- **APP_ENV**: Application environment (development/production)
- **DEBUG**: Enable debug mode
- **TIMEZONE**: Application timezone
- **PLUGIN_ROOT**: Path to plugins directory
- **CONFIG_ROOT**: Path to configuration files

## Architecture

The framework is built around these core principles:

- **Simplicity**: Easy to understand and use
- **Modularity**: Everything is a plugin
- **Flexibility**: Adapt to different project needs
- **Modern Standards**: Follows PHP best practices
- **Developer Experience**: Focused on making development enjoyable

## Getting Started

Pindrop is perfect for building:
- Content management systems
- API backends
- Admin dashboards
- E-commerce platforms
- Custom web applications

Start building your next project with Pindrop - where modularity meets simplicity.