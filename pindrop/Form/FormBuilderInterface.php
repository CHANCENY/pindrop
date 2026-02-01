<?php

namespace Simp\Pindrop\Form;

use Symfony\Component\HttpFoundation\Request;

interface FormBuilderInterface
{
    public function buildFormRender(array $form,FormStateInterface $formState, Request $request): static;

    public function __toString(): string;
}