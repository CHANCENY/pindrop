<?php

namespace Simp\Pindrop\Form;

use Simp\Pindrop\Form\FieldTypeResolvers\FieldResolverFactory;
use Symfony\Component\HttpFoundation\Request;

class FormBuilder implements FormBuilderInterface
{
    protected array $form;
    protected Request $request;
    protected string $formHtml;

    public function buildFormRender(array $form, FormStateInterface $formState, Request $request): static
    {
        $this->form = $form;
        $this->request = $request;

        // Build the HTML form
        $this->formHtml = $this->renderForm($form, $formState, $request);

        return $this;
    }

    public function __toString(): string
    {
        return $this->formHtml ?? '';
    }

    /**
     * Render complete form HTML
     */
    private function renderForm(array $form, FormStateInterface $formState, Request $request): string
    {
        $html = '<form method="POST" action="' . $request->getRequestUri() . '">';
        
        // Check if form has fieldsets or details
        $hasFieldsets = false;
        $hasDetails = false;
        foreach ($form as $fieldName => $field) {
            if (isset($field['#fieldset'])) {
                $hasFieldsets = true;
            }
            if (isset($field['#details'])) {
                $hasDetails = true;
            }
            if ($hasFieldsets || $hasDetails) {
                break;
            }
        }
        
        if ($hasFieldsets || $hasDetails) {
            // Render with fieldsets and/or details
            foreach ($form as $fieldName => $field) {
                if (isset($field['#fieldset'])) {
                    $html .= '<fieldset>';
                    if (!empty($field['#fieldset']['legend'])) {
                        $html .= '<legend>' . htmlspecialchars($field['#fieldset']['legend']) . '</legend>';
                    }
                    
                    // Handle both nested and flat fieldset structures
                    $fieldsetFields = $field['#fieldset']['fields'] ?? [];
                    if (empty($fieldsetFields) && isset($field['fields'])) {
                        // Flat structure: fields at same level as #fieldset
                        $fieldsetFields = $field['fields'];
                    }
                    
                    // Render fields - check for nested fieldsets
                    foreach ($fieldsetFields as $subFieldName => $subField) {
                        if (isset($subField['#fieldset'])) {
                            // Nested fieldset
                            $html .= '<fieldset>';
                            if (!empty($subField['#fieldset']['legend'])) {
                                $html .= '<legend>' . htmlspecialchars($subField['#fieldset']['legend']) . '</legend>';
                            }
                            
                            // Handle nested fieldset fields
                            $nestedFields = $subField['#fieldset']['fields'] ?? [];
                            if (empty($nestedFields) && isset($subField['fields'])) {
                                $nestedFields = $subField['fields'];
                            }
                            
                            foreach ($nestedFields as $nestedFieldName => $nestedField) {
                                $html .= $this->renderFieldWrapper($nestedFieldName, $nestedField, $formState);
                            }
                            $html .= '</fieldset>';
                        } elseif (isset($subField['#details'])) {
                            // Nested details within fieldset
                            $open = $subField['#details']['open'] ?? false;
                            $html .= '<details' . ($open ? ' open' : '') . '>';
                            if (!empty($subField['#details']['summary'])) {
                                $html .= '<summary>' . htmlspecialchars($subField['#details']['summary']) . '</summary>';
                            }
                            
                            // Handle nested details fields
                            $detailsFields = $subField['#details']['fields'] ?? [];
                            if (empty($detailsFields) && isset($subField['fields'])) {
                                $detailsFields = $subField['fields'];
                            }
                            
                            foreach ($detailsFields as $detailsFieldName => $detailsField) {
                                $html .= $this->renderFieldWrapper($detailsFieldName, $detailsField, $formState);
                            }
                            $html .= '</details>';
                        } else {
                            // Regular field
                            $html .= $this->renderFieldWrapper($subFieldName, $subField, $formState);
                        }
                    }
                    $html .= '</fieldset>';
                } elseif (isset($field['#details'])) {
                    // Render details section
                    $open = $field['#details']['open'] ?? false;
                    $html .= '<details' . ($open ? ' open' : '') . '>';
                    if (!empty($field['#details']['summary'])) {
                        $html .= '<summary>' . htmlspecialchars($field['#details']['summary']) . '</summary>';
                    }
                    
                    // Handle details fields
                    $detailsFields = $field['#details']['fields'] ?? [];
                    if (empty($detailsFields) && isset($field['fields'])) {
                        $detailsFields = $field['fields'];
                    }
                    
                    foreach ($detailsFields as $detailFieldName => $detailField) {
                        $html .= $this->renderFieldWrapper($detailFieldName, $detailField, $formState);
                    }
                    $html .= '</details>';
                } else {
                    // Regular field
                    $html .= $this->renderFieldWrapper($fieldName, $field, $formState);
                }
            }
        } else {
            // Render simple form without fieldsets
            foreach ($form as $fieldName => $field) {
                $html .= $this->renderFieldWrapper($fieldName, $field, $formState);
            }
        }
        
        // Add submit button
        $html .= '<div class="form-actions">';
        $html .= '<button type="submit">Submit</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        
        return $html;
    }

    /**
     * Render field with proper wrapper
     */
    private function renderFieldWrapper(string $fieldName, array $field, FormStateInterface $formState): string
    {
        $html = '<div class="form-field" id="field-wrapper-' . htmlspecialchars($fieldName) . '">';
        
        // Add label if title exists
        if (!empty($field['title'])) {
            $html .= '<label for="' . htmlspecialchars($fieldName ?? '') . '" class="form-label">' . htmlspecialchars($field['title'] ?? '') . '</label>';
        }
        
        // Render the field
        $html .= '<div class="form-input">';
        $html .= $this->renderField($fieldName, $field, $formState);
        $html .= '</div>';
        
        // Add file upload script if it's a file field
        if (in_array($field['type'] ?? '', ['file', 'image', 'audio', 'video'])) {
            $html .= $this->addFileUploadScript($fieldName, $field);
        }
        
        // Add error if exists
        $error = $formState->getErrors()[$fieldName] ?? '';
        if (!empty($error)) {
            $html .= '<div class="form-error">' . htmlspecialchars($error) . '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Add file upload script for file fields
     */
    private function addFileUploadScript(string $fieldName, array $field): string
    {
        // Read the file upload script template
        $scriptPath = __DIR__ . '/formJsScript/file-upload.js';
        if (!file_exists($scriptPath)) {
            return '';
        }
        
        $scriptTemplate = file_get_contents($scriptPath);
        if ($scriptTemplate === false) {
            return '';
        }
        
        // Prepare field settings JSON
        $fieldSettings = [
            'accept' => $field['accept'] ?? '',
            'multiple' => $field['multiple'] ?? false,
            'maxSize' => $field['max_size'] ?? null,
            'maxFiles' => $field['max_files'] ?? 1,
            'required' => $field['required'] ?? false
        ];
        
        // Replace placeholders in script
        $script = str_replace(
            [
                '[FIELD_SETTING_JSON]',
                '[FIELD_NAME]',
                '[FIELD_WRAPPER_ID]'
            ],
            [
                json_encode($fieldSettings),
                $fieldName,
                'field-wrapper-' . $fieldName
            ],
            $scriptTemplate
        );
        
        return '<script>' . $script . '</script>';
    }

    /**
     * Render individual field
     */
    private function renderField(string $fieldName, array $field, FormStateInterface $formState): string
    {
        $type = $field['type'] ?? 'text';
        $title = $field['title'] ?? '';
        $value = $formState->getValue($fieldName, $field['value'] ?? '');
        $error = $formState->getErrors()[$fieldName] ?? '';
        
        // Debug logging
        $container = \getAppContainer();
        $logger = $container->get('logger');
        $logger->debug("FormBuilder: Rendering field '$fieldName' with type '$type'");
        
        // Get field resolver
        if (FieldResolverFactory::isSupported($type)) {
            $resolver = FieldResolverFactory::create($type);
            
            $fieldData = [
                'name' => $fieldName,
                'id' => $fieldName,
                'value' => $value,
                'title' => $title,
                'error' => $error,
            ] + $field;
            
            $resolvedField = $resolver->resolve($fieldData);
            return $this->buildFieldHtml($resolvedField, $type);
        }
        
        // Fallback for unsupported field types
        $logger->error("FormBuilder: Unsupported field type '$type' for field '$fieldName'");
        return '<div>Unsupported field type: ' . htmlspecialchars($type) . '</div>';
    }

    /**
     * Build HTML for resolved field
     */
    private function buildFieldHtml(array $resolvedField, string $type): string
    {
        $html = '';
        
        // Add label if title exists
        if (!empty($resolvedField['title'])) {
            $html .= '<label>' . htmlspecialchars($resolvedField['title']) . '</label>';
        }
        
        // Build field based on type
        switch ($type) {
            case 'textarea':
                $html .= '<textarea name="' . htmlspecialchars($resolvedField['name'] ?? '') . '" placeholder="' . htmlspecialchars($resolvedField['placeholder'] ?? '') . '" rows="' . htmlspecialchars($resolvedField['rows'] ?? '4') . '">' . htmlspecialchars($resolvedField['value'] ?? '') . '</textarea>';
                break;
            case 'select':
                $html .= '<select name="' . htmlspecialchars($resolvedField['name'] ?? '') . '">';
                foreach ($resolvedField['options'] ?? [] as $option) {
                    $selected = ($option['value'] ?? '') === ($resolvedField['value'] ?? '') ? 'selected' : '';
                    $html .= '<option value="' . htmlspecialchars($option['value'] ?? '') . '"' . $selected . '>' . htmlspecialchars($option['label'] ?? '') . '</option>';
                }
                $html .= '</select>';
                break;
            case 'checkbox':
                $checked = $resolvedField['value'] ? 'checked' : '';
                $html .= '<input type="checkbox" name="' . htmlspecialchars($resolvedField['name'] ?? '') . '" value="1" ' . $checked . '>';
                break;
            case 'email':
                $html .= '<input type="email" name="' . htmlspecialchars($resolvedField['name'] ?? '') . '" value="' . htmlspecialchars($resolvedField['value'] ?? '') . '" placeholder="' . htmlspecialchars($resolvedField['placeholder'] ?? '') . '">';
                break;
            case 'url':
                $html .= '<input type="url" name="' . htmlspecialchars($resolvedField['name'] ?? '') . '" value="' . htmlspecialchars($resolvedField['value'] ?? '') . '" placeholder="' . htmlspecialchars($resolvedField['placeholder'] ?? '') . '">';
                break;
            case 'file':
                $html .= '<input type="file" name="' . htmlspecialchars($resolvedField['name'] ?? '') . '"';
                if (!empty($resolvedField['accept'])) {
                    $html .= ' accept="' . htmlspecialchars($resolvedField['accept']) . '"';
                }
                if (!empty($resolvedField['multiple'])) {
                    $html .= ' multiple';
                }
                if (!empty($resolvedField['required'])) {
                    $html .= ' required';
                }
                $html .= '>';
                break;
            case 'image':
                $html .= '<input type="image" name="' . htmlspecialchars($resolvedField['name'] ?? '') . '"';
                if (!empty($resolvedField['accept'])) {
                    $html .= ' accept="' . htmlspecialchars($resolvedField['accept']) . '"';
                }
                if (!empty($resolvedField['alt'])) {
                    $html .= ' alt="' . htmlspecialchars($resolvedField['alt']) . '"';
                }
                $html .= '>';
                break;
            case 'audio':
                $html .= '<input type="audio" name="' . htmlspecialchars($resolvedField['name'] ?? '') . '"';
                if (!empty($resolvedField['accept'])) {
                    $html .= ' accept="' . htmlspecialchars($resolvedField['accept']) . '"';
                }
                $html .= '>';
                break;
            case 'video':
                $html .= '<input type="video" name="' . htmlspecialchars($resolvedField['name'] ?? '') . '"';
                if (!empty($resolvedField['accept'])) {
                    $html .= ' accept="' . htmlspecialchars($resolvedField['accept']) . '"';
                }
                $html .= '>';
                break;
            default:
                $html .= '<input type="' . htmlspecialchars($type) . '" name="' . htmlspecialchars($resolvedField['name'] ?? '') . '" value="' . htmlspecialchars($resolvedField['value'] ?? '') . '">';
                break;
        }
        
        // Add error if exists
        if (!empty($resolvedField['error'])) {
            $html .= '<div class="error">' . htmlspecialchars($resolvedField['error']) . '</div>';
        }
        
        return $html;
    }
}