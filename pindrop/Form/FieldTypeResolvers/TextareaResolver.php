<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Textarea Field Resolver
 * 
 * Handles textarea fields for multi-line text input.
 * Based on: https://www.w3schools.com/html/html_form_elements.asp
 */
class TextareaResolver
{
    /**
     * Resolve textarea field attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? '',
            'placeholder' => $field['placeholder'] ?? '',
            'required' => $field['required'] ?? false,
            'disabled' => $field['disabled'] ?? false,
            'readonly' => $field['readonly'] ?? false,
            'rows' => $field['rows'] ?? 4,
            'cols' => $field['cols'] ?? 50,
            'maxlength' => $field['maxlength'] ?? null,
            'minlength' => $field['minlength'] ?? null,
            'wrap' => $field['wrap'] ?? 'soft',
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
        ];

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
