<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Number Field Resolver
 * 
 * Handles number input fields and their variants.
 * Based on: https://www.w3schools.com/html/html_form_input_types.asp
 */
class NumberResolver
{
    /**
     * Resolve number field attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'type' => 'number',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? '',
            'placeholder' => $field['placeholder'] ?? '',
            'required' => $field['required'] ?? false,
            'disabled' => $field['disabled'] ?? false,
            'readonly' => $field['readonly'] ?? false,
            'min' => $field['min'] ?? null,
            'max' => $field['max'] ?? null,
            'step' => $field['step'] ?? null,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
        ];

        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve range field attributes
     */
    public function resolveRange(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'range';
        
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
