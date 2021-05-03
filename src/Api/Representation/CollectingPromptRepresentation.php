<?php
namespace Collecting\Api\Representation;

use Collecting\Entity\CollectingPrompt;
use Omeka\Api\Representation\AbstractRepresentation;
use Laminas\ServiceManager\ServiceLocatorInterface;

class CollectingPromptRepresentation extends AbstractRepresentation
{
    public function __construct(CollectingPrompt $resource, ServiceLocatorInterface $serviceLocator)
    {
        $this->resource = $resource;
        $this->setServiceLocator($serviceLocator);
    }

    public function jsonSerialize()
    {
        if ($property = $this->property()) {
            $property = $property->getReference();
        }
        return [
            'o:id' => $this->id(),
            'o-module-collecting:type' => $this->type(),
            'o-module-collecting:text' => $this->text(),
            'o-module-collecting:input_type' => $this->inputType(),
            'o-module-collecting:select_options' => $this->selectOptions(),
            'o-module-collecting:resource_query' => $this->resourceQuery(),
            'o-module-collecting:custom_vocab' => $this->customVocab(),
            'o-module-collecting:media_type' => $this->mediaType(),
            'o-module-collecting:required' => $this->required(),
            'o-module-collecting:multiple' => $this->multiple(),
            'o:property' => $property,
        ];
    }

    public function id()
    {
        return $this->resource->getId();
    }

    public function type()
    {
        return $this->resource->getType();
    }

    public function text()
    {
        return $this->resource->getText();
    }

    public function inputType()
    {
        return $this->resource->getInputType();
    }

    public function selectOptions()
    {
        return $this->resource->getSelectOptions();
    }

    public function resourceQuery()
    {
        return $this->resource->getResourceQuery();
    }

    public function customVocab()
    {
        return $this->resource->getCustomVocab();
    }

    public function mediaType()
    {
        return $this->resource->getMediaType();
    }

    public function required()
    {
        return $this->resource->getRequired();
    }

    public function multiple()
    {
        return $this->resource->getMultiple();
    }

    public function property()
    {
        return $this->getAdapter('properties')
            ->getRepresentation($this->resource->getProperty());
    }

    /**
     * Get the prompt type, ready for display.
     *
     * @return string
     */
    public function displayType()
    {
        $collecting = $this->getViewHelper('collecting');
        $type = $this->type();
        $translator = $this->getTranslator();
        $displayType = $collecting->typeValue($type);
        if ('property' === $type) {
            $displayType = sprintf('%s [%s]', $translator->translate($displayType), $this->property()->term());
        } elseif ('media' === $type) {
            $displayType = sprintf('%s [%s]', $translator->translate($displayType), $collecting->mediaTypeValue($this->mediaType()));
        } elseif ('metadata' === $type) {
            $metadataType = $this->inputType();
            $metadataValue = $this->selectOptions();
            if ($metadataType === 'resource_template') {
                try {
                    $resourceTemplate = $this->getServiceLocator()->get('Omeka\ApiManager')->read('resource_templates', ['id' => $metadataValue])->getContent();
                    $metadataValue = $resourceTemplate->label();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    $metadataValue = $translator->translate('Removed resource template'); // @translate
                }
            }
            $displayType = sprintf(
                '%s [%s = %s]',
                $translator->translate($displayType),
                $translator->translate($metadataType),
                $metadataValue
            );
        }
        return $displayType;
    }

    /**
     * Get the prompt text, ready for display.
     *
     * @return string
     */
    public function displayText()
    {
        $displayText = $this->text();
        if (!$displayText && 'property' === $this->type()) {
            $displayText = $this->property()->label();
        }
        return $displayText;
    }
}
