<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Date Field Resolver
 * 
 * Handles date, time, and datetime input fields.
 * Based on: https://www.w3schools.com/html/html_form_input_types.asp
 */
class DateResolver
{
    /**
     * Resolve date field attributes
     */
    public function resolve(array $field): array
    {
        $attributes = [
            'type' => 'date',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? '',
            'required' => $field['required'] ?? false,
            'disabled' => $field['disabled'] ?? false,
            'readonly' => $field['readonly'] ?? false,
            'min' => $field['min'] ?? null,
            'max' => $field['max'] ?? null,
            'step' => $field['step'] ?? null,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
            'autocomplete' => $field['autocomplete'] ?? 'off',
        ];

        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve time field attributes
     */
    public function resolveTime(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'time';
        $attributes['step'] = $field['step'] ?? null;
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve datetime-local field attributes
     */
    public function resolveDateTimeLocal(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'datetime-local';
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve month field attributes
     */
    public function resolveMonth(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'month';
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve week field attributes
     */
    public function resolveWeek(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'week';
        
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
