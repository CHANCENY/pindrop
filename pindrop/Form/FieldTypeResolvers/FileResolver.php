<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * File Field Resolver
 * 
 * Handles file upload fields and their attributes.
 * Based on: https://www.w3schools.com/html/html_form_input_types.asp
 */
class FileResolver
{
    /**
     * Resolve file field attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'type' => 'file',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'required' => $field['required'] ?? false,
            'disabled' => $field['disabled'] ?? false,
            'multiple' => $field['multiple'] ?? false,
            'accept' => $field['accept'] ?? null,
            'capture' => $field['capture'] ?? null,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
            'size' => $field['size'] ?? null,
        ];

        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve image field attributes
     */
    public function resolveImage(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['accept'] = $field['accept'] ?? 'image/*';
        $attributes['capture'] = $field['capture'] ?? null;
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve audio field attributes
     */
    public function resolveAudio(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['accept'] = $field['accept'] ?? 'audio/*';
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve video field attributes
     */
    public function resolveVideo(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['accept'] = $field['accept'] ?? 'video/*';
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Build HTML attributes string
     */
    private function buildAttributes(array $attributes): array
    {
        $htmlAttributes = [];
        
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false && $value !== '') {
                if (is_bool($value)) {
                    $htmlAttributes[] = $key;
                } else {
                    $htmlAttributes[$key] = (string) $value;
                }
            }
        }
        
        return $htmlAttributes;
    }
}
