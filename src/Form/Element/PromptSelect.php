<?php
namespace Collecting\Form\Element;

use Laminas\Form\Element\Select;

class PromptSelect extends Select
{
    use PromptIsMultipleTrait;
    use PromptIsRequiredTrait;

    public function getInputSpecification()
    {
        $spec = parent::getInputSpecification();
        $spec['required'] = $this->required;
        return $spec;
    }
}
