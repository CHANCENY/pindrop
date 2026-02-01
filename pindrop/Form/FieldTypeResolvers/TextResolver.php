<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Text Field Resolver
 * 
 * Handles text input fields and their variants.
 * Based on: https://www.w3schools.com/html/html_form_input_types.asp
 */
class TextResolver
{
    /**
     * Resolve text field attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'type' => 'text',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? '',
            'placeholder' => $field['placeholder'] ?? '',
            'required' => $field['required'] ?? false,
            'disabled' => $field['disabled'] ?? false,
            'readonly' => $field['readonly'] ?? false,
            'maxlength' => $field['maxlength'] ?? null,
            'minlength' => $field['minlength'] ?? null,
            'size' => $field['size'] ?? null,
            'pattern' => $field['pattern'] ?? null,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
        ];

        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve password field attributes
     */
    public function resolvePassword(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'password';
        $attributes['autocomplete'] = $field['autocomplete'] ?? 'current-password';
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve email field attributes
     */
    public function resolveEmail(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'email';
        $attributes['autocomplete'] = $field['autocomplete'] ?? 'email';
        $attributes['multiple'] = $field['multiple'] ?? false;
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve search field attributes
     */
    public function resolveSearch(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'search';
        $attributes['autocomplete'] = $field['autocomplete'] ?? 'on';
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve URL field attributes
     */
    public function resolveUrl(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'url';
        $attributes['autocomplete'] = $field['autocomplete'] ?? 'url';
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve telephone field attributes
     */
    public function resolveTel(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'tel';
        $attributes['autocomplete'] = $field['autocomplete'] ?? 'tel';
        
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
