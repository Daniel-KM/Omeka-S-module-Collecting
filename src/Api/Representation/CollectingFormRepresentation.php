<?php
namespace Collecting\Api\Representation;

use Collecting\Form\Element;
use Omeka\Api\Exception\BadRequestException;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Laminas\Form\Form;
use Laminas\Http\PhpEnvironment\RemoteAddress;

class CollectingFormRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var Form
     */
    protected $form;

    public function getControllerName()
    {
        return 'collecting';
    }

    public function getJsonLdType()
    {
        return 'o-module-collecting:Form';
    }

    public function getJsonLd()
    {
        if ($site = $this->site()) {
            $site = $site->getReference();
        }
        if ($itemSet = $this->itemSet()) {
            $itemSet = $itemSet->getReference();
        }
        return [
            'o-module-collecting:label' => $this->label(),
            'o-module-collecting:anon_type' => $this->anonType(),
            'o-module-collecting:success_text' => $this->successText(),
            'o-module-collecting:email_text' => $this->emailText(),
            'o:site' => $site,
            'o:item_set' => $itemSet,
            'o-module-collecting:prompt' => $this->prompts(),
        ];
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/site/slug/collecting/id',
            [
                'site-slug' => $this->site()->slug(),
                'controller' => $this->getControllerName(),
                'action' => $action,
                'form-id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    public function label()
    {
        return $this->resource->getLabel();
    }

    public function anonType()
    {
        return $this->resource->getAnonType();
    }

    public function itemSet()
    {
        return $this->getAdapter('item_sets')
            ->getRepresentation($this->resource->getItemSet());
    }

    public function successText()
    {
        return $this->resource->getSuccessText();
    }

    public function emailText()
    {
        return $this->resource->getEmailText();
    }

    public function owner()
    {
        return $this->getAdapter('users')
            ->getRepresentation($this->resource->getOwner());
    }

    public function site()
    {
        return $this->getAdapter('sites')
            ->getRepresentation($this->resource->getSite());
    }

    public function prompts()
    {
        $prompts = [];
        foreach ($this->resource->getPrompts() as $prompt) {
            $prompts[] = new CollectingPromptRepresentation($prompt, $this->getServiceLocator());
        }
        return $prompts;
    }

    /**
     * Get the object used to validate and render this form.
     *
     * @param bool $isValidation Hack to manage multiple values with a simpler
     * form. The name should have "[]" in render, but not in validation.
     * @return Form
     */
    public function getForm($isValidation = false)
    {
        // Check access of the current user for this form.
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $auth = $services->get('Omeka\AuthenticationService');
        $user = $auth->getIdentity();
        $allowedRoles = $siteSettings->get('collecting_roles', []);
        if ($allowedRoles) {
            if (!$user) {
                $this->form = false;
                return null;
            }

            // Check role and acl for items (allow standard roles).
            $userRole = $user->getRole();
            if (!in_array($userRole, $allowedRoles)) {
                $acl = $services->get('Omeka\Acl');
                if (!$acl->isAllowed($userRole, \Omeka\Entity\Item::class, 'create')) {
                    $this->form = false;
                    return null;
                }
            }
        }

        if (!is_null($this->form)) {
            return $this->form; // build the form object only once
        }

        /** @var \Collecting\View\Helper\Collecting $collecting */
        $url = $this->getViewHelper('Url');
        $collecting = $this->getViewHelper('collecting');
        $mediaTypes = $services->get('Collecting\MediaTypeManager');
        $api = $services->get('Omeka\ApiManager');
        $translator = $this->getTranslator();

        $dataTypeManager = $services->get('Omeka\DataTypeManager');
        $suggesters = [];
        $dataTypes = $dataTypeManager->getRegisteredNames();
        foreach ($dataTypes as $dataTypeName) {
            if (strpos($dataTypeName, 'valuesuggest:') === 0
                || strpos($dataTypeName, 'valuesuggestall:') === 0
            ) {
                $suggesters[$dataTypeName] = $dataTypeManager->get($dataTypeName)->getLabel();
            }
        }
        unset($suggesters['valuesuggest:any']);

        $form = new Form(sprintf('collecting_form_%s', $this->id()));
        $this->form = $form; // cache the form
        $form->setAttribute('action', $url('site/collecting', [
            'form-id' => $this->id(),
            'action' => 'submit',
        ], true));

        $hasUserEmailPrompt = false;
        foreach ($this->prompts() as $prompt) {
            $name = sprintf('prompt_%s', $prompt->id());
            $isMultiple = $prompt->multiple();
            if ($isMultiple && !$isValidation) {
                $name .= '[]';
            }
            $isSelect = false;
            switch ($prompt->type()) {
                // Note that there's no break here. When building the form we
                // handle property, input, and user prompts the same.
                case 'property':
                case 'input':
                case 'user_private':
                case 'user_public':
                    switch ($prompt->inputType()) {
                        case 'text':
                            $element = new Element\PromptText($name);
                            break;
                        case 'textarea':
                            $element = new Element\PromptTextarea($name);
                            break;
                        case 'select':
                            $isSelect = true;
                            if ($isMultiple) {
                                $name = rtrim($name, '[]');
                            }
                            $selectOptions = explode(PHP_EOL, $prompt->selectOptions());
                            $element = new Element\PromptSelect($name);
                            if ($isMultiple) {
                                $element->setEmptyOption('');
                            } else {
                                $element->setEmptyOption('Please choose one…'); // @translate
                            }
                            $element
                                ->setValueOptions(array_combine($selectOptions, $selectOptions));
                            break;
                        case 'item':
                            $isSelect = true;
                            if ($isMultiple) {
                                $name = rtrim($name, '[]');
                            }
                            $resourceQuery = [];
                            parse_str(ltrim($prompt->resourceQuery(), "? \t\n\r\0\x0B"), $resourceQuery);
                            $element = new Element\PromptItem($name);
                            if ($isMultiple) {
                                $element
                                    ->setEmptyOption('')
                                    ->setAttribute('data-placeholder', $translator->translate('Please choose one or more…')); // @translate
                            } else {
                                $element->setEmptyOption('Please choose one…'); // @translate
                            }
                            $element->setApiManager($api);
                            $prependId = (bool) $prompt->selectOptions();
                            if ($prependId) {
                                $element
                                    ->setResourceValueOptions('items', function ($item) {
                                        return sprintf('#%s: %s', $item->id(), mb_substr($item->displayTitle(), 0, 80));
                                    }, $resourceQuery);
                            } else {
                                $element
                                    ->setResourceValueOptions('items', function ($item) {
                                        return mb_substr($item->displayTitle(), 0, 80);
                                    }, $resourceQuery);
                            }
                            break;
                        case 'thesaurus':
                            if (!$collecting->inputTypeIsAvailable('thesaurus')) {
                                continue 3;
                            }
                            $isSelect = true;
                            if ($isMultiple) {
                                $name = rtrim($name, '[]');
                            }
                            $thesaurusOptions = [];
                            parse_str(ltrim($prompt->resourceQuery(), "? \t\n\r\0\x0B"), $thesaurusOptions);
                            if (isset($thesaurusOptions) && is_numeric($thesaurusOptions)) {
                                $thesaurusOptions = ['thesaurus' => ['term' => $thesaurusOptions]];
                            }
                            $thesaurusOptions['thesaurus']['options']['prepend_id'] = (bool) $prompt->selectOptions();
                            $element = new Element\PromptThesaurus($name);
                            $element
                                ->setApiManager($api)
                                ->setThesaurus($this->getServiceLocator()->get('ControllerPluginManager')->get('thesaurus'))
                                ->setOptions($thesaurusOptions);
                            if ($isMultiple) {
                                $element
                                    ->setEmptyOption('')
                                    ->setAttribute('data-placeholder', $translator->translate('Please choose one or more…')); // @translate
                            } else {
                                $element->setEmptyOption('Please choose one…'); // @translate
                            }
                            break;
                        case 'url':
                            $element = new Element\PromptUrl($name);
                            break;
                        case 'custom_vocab':
                            try {
                                $response = $api->read('custom_vocabs', $prompt->customVocab());
                            } catch (NotFoundException $e) {
                                // The custom vocab does not exist.
                                continue 3;
                            } catch (BadRequestException $e) {
                                // The CustomVocab module is not installed or active.
                                continue 3;
                            }
                            $isSelect = true;
                            if ($isMultiple) {
                                $name = rtrim($name, '[]');
                            }
                            $terms = array_map('trim', explode(PHP_EOL, $response->getContent()->terms()));
                            $element = new Element\PromptSelect($name);
                            if ($isMultiple) {
                                $element
                                    ->setEmptyOption('');
                            } else {
                                $element
                                    ->setEmptyOption('Please choose one...'); // @translate
                            }
                            $element
                                ->setValueOptions(array_combine($terms, $terms));
                            break;
                        case 'numeric:timestamp':
                            if (!$collecting->inputTypeIsAvailable('numeric:timestamp')) {
                                continue 3;
                            }
                            $element = new Element\PromptNumericTimestamp($name);
                            break;
                        case 'numeric:interval':
                            if (!$collecting->inputTypeIsAvailable('numeric:interval')) {
                                continue 3;
                            }
                            $element = new Element\PromptNumericInterval($name);
                            break;
                        case 'numeric:duration':
                            if (!$collecting->inputTypeIsAvailable('numeric:duration')) {
                                continue 3;
                            }
                            $element = new Element\PromptNumericDuration($name);
                            break;
                        case 'numeric:integer':
                            if (!$collecting->inputTypeIsAvailable('numeric:integer')) {
                                continue 3;
                            }
                            $element = new Element\PromptNumericInteger($name);
                            break;
                        case 'value_suggest':
                            // The ValueSuggest module is not installed or active.
                            if (!$suggesters) {
                                continue 3;
                            }
                            // The suggester does not exist or is not active.
                            $suggester = $prompt->selectOptions();
                            if (!isset($suggesters[$suggester])) {
                                continue 3;
                            }
                            $element = new Element\PromptValueSuggest($name);
                            $element->setDataType($suggester);
                            break;
                        default:
                            // Invalid prompt input type. Do nothing.
                            continue 3;
                    }
                    $label = ($prompt->property() && !$prompt->text())
                        ? $prompt->property()->label()
                        : $prompt->text();
                    $element->setLabel($label)
                        ->setIsRequired($prompt->required());
                    if ($isMultiple) {
                        if ($isSelect) {
                            $element
                                ->setAttribute('multiple', 'multiple');
                        } else {
                            $element
                                ->setAttribute('data-multiple', true);
                        }
                        if ($isValidation) {
                            $element
                                ->setOption('isArray', true);
                        }
                    }
                    $form->add($element);
                    break;
                case 'user_name':
                    $element = new Element\PromptText($name);
                    $element->setLabel($prompt->text())
                        ->setIsRequired($prompt->required());
                    if ($user) {
                        $element->setValue($user->getName());
                    }
                    $form->add($element);
                    break;
                case 'user_email':
                    $hasUserEmailPrompt = true;
                    $element = new Element\PromptEmail($name);
                    $element->setLabel($prompt->text())
                        ->setIsRequired($prompt->required());
                    if ($user) {
                        $element->setValue($user->getEmail());
                    }
                    $form->add($element);
                    break;
                case 'html':
                    $element = new Element\PromptHtml($name);
                    $element->setHtml($prompt->text());
                    $form->add($element);
                    break;
                case 'media':
                    $mediaTypes->get($prompt->mediaType())->form($form, $prompt, $name);
                    break;
                case 'metadata':
                    // Invalid prompt input type. Don't use element Hidden.
                    continue 2;
                default:
                    // Invalid prompt type. Do nothing.
                    continue 2;
            }
        }

        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');
        $translator = $services->get('MvcTranslator');

        if ('user' === $this->anonType()) {
            $form->add([
                'type' => 'checkbox',
                'name' => sprintf('anon_%s', $this->id()),
                'options' => [
                    'label' => 'I want to submit anonymously', // @translate
                ],
            ]);
        }

        if ($hasUserEmailPrompt) {
            $form->add([
                'type' => 'checkbox',
                'name' => sprintf('email_send_%s', $this->id()),
                'options' => [
                    'label' => 'Email me my submission', // @translate
                ],
            ]);
        }

        // Add the terms of service if provided in site settings.
        $tos = $siteSettings->get('collecting_tos');
        if ($tos) {
            $tosUrl = $url('site/collecting', [
                'form-id' => $this->id(),
                'action' => 'tos',
            ], true);
            $form->add([
                'type' => 'checkbox',
                'name' => sprintf('tos_accept_%s', $this->id()),
                'attributes' => [
                    'required' => true,
                    'value' => (bool) $user,
                ],
                'options' => [
                    'label' => sprintf(
                        $translator->translate('I accept the %s'),
                        sprintf(
                            '<a href="' . $tosUrl . '" target="_blank" style="text-decoration: underline;">%s</a>',
                            $translator->translate('Terms of Service')
                        )
                    ),
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'use_hidden_element' => false,
                ],
            ]);
        }

        // Add reCAPTCHA protection if keys are provided in global settings.
        $siteKey = $settings->get('recaptcha_site_key');
        $secretKey = $settings->get('recaptcha_secret_key');
        if ($siteKey && $secretKey) {
            $element = $services
                ->get('FormElementManager')
                ->get('Omeka\Form\Element\Recaptcha', [
                    'site_key' => $siteKey,
                    'secret_key' => $secretKey,
                    'remote_ip' => (new RemoteAddress)->getIpAddress(),
                ]);
            $form->add($element);
        }

        $form->add([
            'type' => 'csrf',
            'name' => sprintf('csrf_%s', $this->id()),
            'options' => [
                'csrf_options' => ['timeout' => 3600],
            ],
        ]);
        $form->add([
            'type' => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Submit',
            ],
        ]);
        return $form;
    }
}
