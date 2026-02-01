<?php

declare(strict_types=1);

namespace Simp\Pindrop\Form\FieldTypeResolvers;

/**
 * Button Field Resolver
 * 
 * Handles button elements and their attributes.
 * Based on: https://www.w3schools.com/html/html_form_elements.asp
 */
class ButtonResolver
{
    /**
     * Main resolve method - determines button type and calls appropriate resolver
     */
    public function resolve(array $field): array
    {
        $type = $field['type'] ?? 'button';
        
        switch ($type) {
            case 'submit':
                return $this->resolveSubmit($field);
            case 'reset':
                return $this->resolveReset($field);
            case 'button':
            default:
                return $this->resolveButton($field);
        }
    }

    /**
     * Resolve submit button attributes
     */
    public function resolveSubmit(array $field): array
    {
        $attributes = [
            'type' => 'submit',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? 'Submit',
            'disabled' => $field['disabled'] ?? false,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
            'form' => $field['form'] ?? null,
            'formaction' => $field['formaction'] ?? null,
            'formenctype' => $field['formenctype'] ?? null,
            'formmethod' => $field['formmethod'] ?? null,
            'formnovalidate' => $field['formnovalidate'] ?? false,
            'formtarget' => $field['formtarget'] ?? null,
        ];

        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve reset button attributes
     */
    public function resolveReset(array $field): array
    {
        $attributes = $this->resolve($field);
        $attributes['type'] = 'reset';
        $attributes['value'] = $field['value'] ?? 'Reset';
        
        return $this->buildAttributes($attributes);
    }

    /**
     * Resolve button attributes
     */
    public function resolveButton(array $field): array
    {
        $attributes = [
            'type' => 'button',
            'name' => $field['name'] ?? '',
            'id' => $field['id'] ?? $field['name'] ?? '',
            'value' => $field['value'] ?? '',
            'disabled' => $field['disabled'] ?? false,
            'class' => $field['class'] ?? '',
            'style' => $field['style'] ?? '',
            'form' => $field['form'] ?? null,
            'formaction' => $field['formaction'] ?? null,
            'formenctype' => $field['formenctype'] ?? null,
            'formmethod' => $field['formmethod'] ?? null,
            'formnovalidate' => $field['formnovalidate'] ?? false,
            'formtarget' => $field['formtarget'] ?? null,
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
