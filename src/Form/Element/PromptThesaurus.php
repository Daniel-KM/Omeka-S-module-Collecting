<?php
namespace Collecting\Form\Element;

use Thesaurus\Form\Element\ThesaurusSelect;

class PromptThesaurus extends ThesaurusSelect
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
