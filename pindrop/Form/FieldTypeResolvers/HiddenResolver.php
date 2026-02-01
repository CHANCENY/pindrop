<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Hidden Field Resolver
 * 
 * Handles hidden input fields.
 * Based on: https://www.w3schools.com/html/html_form_input_types.asp
 */
class HiddenResolver
{
    /**
     * Resolve hidden field attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'type' => 'hidden',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? '',
            'form' => $field['form'] ?? null,
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
