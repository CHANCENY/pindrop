<?php

namespace Simp\Pindrop\Form;

use Symfony\Component\HttpFoundation\Request;

interface FormStateInterface
{
    public function buildFormState(array $fields, Request $request);

    public function isValidated(): bool;

    public function setErrorByName(string $fieldName, string $errorMessage);

    public function setError(string $errorMessage);

    public function getErrors(): array;

    public function getValues(): array;

    public function getRequest(): Request;

    public function getValue(string $fieldName, $default = null);

    public function setMessage(string $message);

    public function setMessageByName(string $fieldName, string $message);

    public function getMessages();

    public function getFile(string $fieldName, $default = null);

    public function getFiles(): array;
}