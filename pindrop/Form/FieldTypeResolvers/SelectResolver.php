<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Select Field Resolver
 * 
 * Handles select dropdown fields and option groups.
 * Based on: https://www.w3schools.com/html/html_form_elements.asp
 */
class SelectResolver
{
    /**
     * Resolve select field attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'required' => $field['required'] ?? false,
            'disabled' => $field['disabled'] ?? false,
            'multiple' => $field['multiple'] ?? false,
            'size' => $field['size'] ?? null,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
            'options' => $field['options'] ?? [],
            'selected' => $field['selected'] ?? [],
        ];

        return $this->buildSelectData($attributes);
    }

    /**
     * Resolve optgroup structure
     */
    public function resolveOptgroup(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['optgroups'] = $field['optgroups'] ?? [];
        
        return $this->buildSelectData($attributes);
    }

    /**
     * Build select data structure
     */
    private function buildSelectData(array $attributes): array
    {
        return [
            'attributes' => $this->buildAttributes($attributes),
            'options' => $attributes['options'],
            'optgroups' => $attributes['optgroups'] ?? [],
            'selected' => (array) $attributes['selected'],
        ];
    }

    /**
     * Build HTML attributes string
     */
    private function buildAttributes(array $attributes): array
    {
        $htmlAttributes = [];
        
        foreach ($attributes as $key => $value) {
            if ($key === 'options' || $key === 'optgroups' || $key === 'selected') {
                continue;
            }
            
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
