<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Label Field Resolver
 * 
 * Handles label elements and their attributes.
 * Based on: https://www.w3schools.com/html/html_form_elements.asp
 */
class LabelResolver
{
    /**
     * Resolve label attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'for' => $field['for'] ?? '',
            'id' => $field['id'] ?? '',
            'text' => $field['text'] ?? '',
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
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
            if ($key === 'text') {
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
        
        return [
            'attributes' => $htmlAttributes,
            'text' => $attributes['text'],
        ];
    }
}
