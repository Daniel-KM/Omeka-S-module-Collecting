<?php
namespace Collecting\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Laminas\Form\Element;
use Laminas\View\Renderer\PhpRenderer;

class Collecting extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Collecting'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null)
    {
        $forms = $view->api()
            ->search('collecting_forms', ['site_id' => $site->id()])
            ->getContent();
        $formCheckboxes = new Element\MultiCheckbox('o:block[__blockIndex__][o:data][forms]');
        $valueOptions = [];
        foreach ($forms as $form) {
            $valueOptions[$form->id()] = $form->label();
        }
        $formCheckboxes->setValueOptions($valueOptions);
        if ($block) {
            $formCheckboxes->setValue($block->dataValue('forms'));
        }
        return $view->partial('common/block-layout/collecting-block-form', [
            'formCheckboxes' => $formCheckboxes,
        ]);
    }

    public function prepareRender(PhpRenderer $view)
    {
        $view->collectingPrepareForm();
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()->appendStylesheet($assetUrl('css/collecting.css', 'Collecting'));
        $view->headScript()->appendFile($assetUrl('js/collecting-block.js', 'Collecting'), 'text/javascript', ['defer' => 'defer']);

        // TODO Append value suggest js only if a property uses it.
        // To check if ValueSuggest is available, just try to get the routed url.
        try {
            $proxyUrl = $view->url('site/value-suggest/proxy', [], true);
        } catch (\Exception $e) {
            return;
        }
        $view->headLink()
            ->appendStylesheet($assetUrl('css/value-suggest.css', 'ValueSuggest'));
        $view->headScript()
            ->appendFile($assetUrl('js/jQuery-Autocomplete/1.2.26/jquery.autocomplete.min.js', 'ValueSuggest'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('js/value-suggest.js', 'ValueSuggest'), 'text/javascript', ['defer' => 'defer'])
            ->appendScript(sprintf(
                'var valueSuggestProxyUrl = "%s";',
                $view->escapeJs($proxyUrl)
            ));
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $cForms = [];

        $redirectCurrent = $view->siteSetting('collecting_redirect_current', false);
        if ($redirectCurrent) {
            $redirectHidden = new Element\Hidden('page');
            $redirectHidden->setValue($block->page()->id());
        }

        foreach ($block->dataValue('forms', []) as $formId) {
            try {
                $formContent = $view->api()->read('collecting_forms', $formId)->getContent();
                // Add the form only if user has rights to contribute.
                if ($form = $formContent->getForm()) {
                    if ($redirectCurrent) {
                        $form->add($redirectHidden);
                    }
                    $cForms[] = $formContent;
                }
            } catch (NotFoundException $e) {
                // The form was likely deleted since it was added to this block.
            }
        }

        if (1 === count($cForms)) {
            return $view->partial('common/block-layout/collecting-block-one', [
                'cForm' => $cForms[0],
            ]);
        } elseif (1 < count($cForms)) {
            return $view->partial('common/block-layout/collecting-block-multiple', [
                'cForms' => $cForms,
            ]);
        }
    }
}
