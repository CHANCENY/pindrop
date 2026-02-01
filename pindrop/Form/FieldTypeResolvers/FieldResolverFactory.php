<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Field Resolver Factory
 * 
 * Factory for creating appropriate field resolvers based on field type.
 * Supports all major HTML form elements from W3Schools.
 */
class FieldResolverFactory
{
    /**
     * Create appropriate resolver for field type
     */
    public static function create(string $type): object
    {
        return match (strtolower($type)) {
            // Text-based fields
            'text', 'password', 'email', 'search', 'url', 'tel' => new TextResolver(),
            
            // Number fields
            'number', 'range' => new NumberResolver(),
            
            // Select fields
            'select', 'optgroup' => new SelectResolver(),
            
            // Checkbox/Radio fields
            'checkbox', 'radio' => new CheckboxResolver(),
            
            // Textarea
            'textarea' => new TextareaResolver(),
            
            // Date/Time fields
            'date', 'time', 'datetime-local', 'month', 'week' => new DateResolver(),
            
            // File fields
            'file', 'image', 'audio', 'video' => new FileResolver(),
            
            // Buttons
            'submit', 'reset', 'button' => new ButtonResolver(),
            
            // Hidden fields
            'hidden' => new HiddenResolver(),
            
            // Labels
            'label' => new LabelResolver(),
            
            // Default
            default => new TextResolver(),
        };
    }

    /**
     * Get all supported field types
     */
    public static function getSupportedTypes(): array
    {
        return [
            // Text inputs
            'text', 'password', 'email', 'search', 'url', 'tel',
            
            // Number inputs
            'number', 'range',
            
            // Selection inputs
            'select', 'optgroup',
            
            // Choice inputs
            'checkbox', 'radio',
            
            // Text areas
            'textarea',
            
            // Date/Time inputs
            'date', 'time', 'datetime-local', 'month', 'week',
            
            // File inputs
            'file', 'image', 'audio', 'video',
            
            // Buttons
            'submit', 'reset', 'button',
            
            // Hidden
            'hidden',
            
            // Labels
            'label',
        ];
    }

    /**
     * Check if field type is supported
     */
    public static function isSupported(string $type): bool
    {
        $container = getAppContainer();
        $logger = $container->get('logger');
        
        $isSupported = in_array(strtolower($type), self::getSupportedTypes());
        $logger->debug("FieldResolverFactory: Checking support for type '$type' -> " . ($isSupported ? 'SUPPORTED' : 'NOT SUPPORTED'));
        
        return $isSupported;
    }
}
