<?php

namespace Simp\Pindrop\Form;

use Symfony\Component\HttpFoundation\Request;

class FormState implements FormStateInterface
{
    protected array $fields;
    protected Request $request;
    protected array $data;
    protected array $errors;
    protected array $messages;
    protected array $rawData;
    protected array $rawFiles;
    
    public function __construct()
    {
        $this->fields = [];
        $this->data = [];
        $this->errors = [];
        $this->messages = [];
        $this->rawData = [];
        $this->rawFiles = [];
    }
    public function buildFormState(array $fields, Request $request)
    {
        $this->fields = $fields;
        $this->request = $request;
        $this->data = [];
        $this->errors = [];
        $this->messages = [];
        $this->rawData = [];
        $this->rawFiles = [];

        // Extract all submitted data
        if ($request->isMethod(Request::METHOD_POST)) {
            $this->rawData = $request->request->all();
            $this->rawFiles = $request->files->all();
        } else {
            $this->rawData = $request->query->all();
            $this->rawFiles = [];
        }
        
        // Extract data from nested field structures
        $this->extractFormData($fields, $this->rawData, $this->data);
        $this->extractFileData($fields, $this->rawFiles, $this->data);
    }

    /**
     * Recursively extract form data from nested field structures
     */
    private function extractFormData(array $fields, array $rawData, array &$data, string $prefix = ''): void
    {
        foreach ($fields as $fieldName => $field) {
            $fullFieldName = $prefix ? $prefix . '.' . $fieldName : $fieldName;
            
            if (isset($field['#fieldset'])) {
                // Handle fieldset - extract from nested fields
                $fieldsetFields = $field['#fieldset']['fields'] ?? $field['fields'] ?? [];
                $this->extractFormData($fieldsetFields, $rawData, $data, $fullFieldName);
            } elseif (isset($field['#details'])) {
                // Handle details - extract from nested fields
                $detailsFields = $field['#details']['fields'] ?? $field['fields'] ?? [];
                $this->extractFormData($detailsFields, $rawData, $data, $fullFieldName);
            } else {
                // Regular field - extract data
                if (isset($rawData[$fieldName])) {
                    $data[$fieldName] = $rawData[$fieldName];
                }
            }
        }
    }

    /**
     * Recursively extract file data from nested field structures
     */
    private function extractFileData(array $fields, array $rawFiles, array &$data, string $prefix = ''): void
    {
        foreach ($fields as $fieldName => $field) {
            $fullFieldName = $prefix ? $prefix . '.' . $fieldName : $fieldName;
            
            if (isset($field['#fieldset'])) {
                // Handle fieldset - extract from nested fields
                $fieldsetFields = $field['#fieldset']['fields'] ?? $field['fields'] ?? [];
                $this->extractFileData($fieldsetFields, $rawFiles, $data, $fullFieldName);
            } elseif (isset($field['#details'])) {
                // Handle details - extract from nested fields
                $detailsFields = $field['#details']['fields'] ?? $field['fields'] ?? [];
                $this->extractFileData($detailsFields, $rawFiles, $data, $fullFieldName);
            } else {
                // Regular field - check if it's a file field
                if (isset($rawFiles[$fieldName])) {
                    $data[$fieldName] = $rawFiles[$fieldName];
                }
            }
        }
    }

    public function isValidated(): bool
    {
       if (!empty($this->errors)) return false;
       return true;
    }

    public function setErrorByName(string $fieldName, string $errorMessage)
    {
        if (isset($this->fields[$fieldName])) {
            $this->errors[$fieldName] = $errorMessage;
        }
    }

    public function setError(string $errorMessage)
    {
        if (!isset($this->errors['form_errors'])) {
            $this->errors['form_errors'] = [];
        }
        $this->errors['form_errors'][] = $errorMessage;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValues(): array
    {
       return $this->data ?? [];
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getValue(string $fieldName, $default = null)
    {
       return $this->data[$fieldName] ?? $default;
    }

    public function setMessage(string $message)
    {
        if (!isset($this->messages['form_messages'])) {
            $this->messages['form_messages'] = [];
        }
        $this->messages['form_messages'][] = $message;
    }

    public function setMessageByName(string $fieldName, string $message)
    {
        if (isset($this->fields[$fieldName])) {
            $this->messages[$fieldName] = $message;
        }
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getFile(string $fieldName, $default = null)
    {
        return $this->data[$fieldName] ?? $default;
    }

    public function getFiles(): array
    {
        $files = [];
        foreach ($this->data as $fieldName => $value) {
            if (is_array($value) && isset($value['tmp_name'])) {
                // Symfony UploadedFile structure
                $files[$fieldName] = $value;
            }
        }
        return $files;
    }
}