<?php

namespace Simp\Pindrop\Form;

use Symfony\Component\HttpFoundation\Request;

abstract class FormBase implements FormInterface
{
    public function formHandlerBuilder(Request $request, string $route_name): object
    {
        $fields = [];
        $formState = new FormState();
        $fields = $this->buildForm($fields, $formState);
        // build post-data in form state
        $formState->buildFormState($fields, $request);
        if ($request->isMethod(Request::METHOD_POST)) {
            $this->validateForm($fields, $formState);

            if ($formState->isValidated()) {
                $this->submitForm($fields, $formState);
            }
        }
        $formHtml = new FormBuilder()->buildFormRender($fields, $formState, $request);
        return new \Symfony\Component\HttpFoundation\Response($formHtml->__toString());
    }
}