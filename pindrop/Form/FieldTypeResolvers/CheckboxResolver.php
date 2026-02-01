<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Checkbox Field Resolver
 * 
 * Handles checkbox and radio input fields.
 * Based on: https://www.w3schools.com/html/html_form_input_types.asp
 */
class CheckboxResolver
{
    /**
     * Resolve checkbox field attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'type' => 'checkbox',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? '',
            'checked' => $field['checked'] ?? false,
            'required' => $field['required'] ?? false,
            'disabled' => $field['disabled'] ?? false,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
        ];

        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve radio field attributes
     */
    public function resolveRadio(array $field): array
    {
        $attributes = [
            'type' => 'radio',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? '',
            'checked' => $field['checked'] ?? false,
            'required' => $field['required'] ?? false,
            'disabled' => $field['disabled'] ?? false,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
        ];

        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve radio group
     */
    public function resolveRadioGroup(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['options'] = $field['options'] ?? [];
        $attributes['selected'] = $field['selected'] ?? null;
        
        return $this->buildRadioGroupData($attributes);
    }

    /**
     * Build checkbox data
     */
    private function buildCheckboxData(array $attributes): array
    {
        return [
            'attributes' => $this->buildAttributes($attributes),
            'checked' => $attributes['checked'],
        ];
    }

    /**
     * Build radio group data
     */
    private function buildRadioGroupData(array $attributes): array
    {
        return [
            'attributes' => $this->buildAttributes($attributes),
            'options' => $attributes['options'],
            'selected' => $attributes['selected'],
        ];
    }

    /**
     * Build HTML attributes string
     */
    private function buildAttributes(array $attributes): array
    {
        $htmlAttributes = [];
        
        foreach ($attributes as $key => $value) {
            if ($key === 'options' || $key === 'selected' || $key === 'checked') {
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
