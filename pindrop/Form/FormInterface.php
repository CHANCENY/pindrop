<?php

namespace Simp\Pindrop\Form;

interface FormInterface
{
    public function getFormId(): string;

    public function buildForm(array $form, FormStateInterface $formState);

    public function validateForm(array $form, FormStateInterface $formState);

    public function submitForm(array $form, FormStateInterface $formState);

}