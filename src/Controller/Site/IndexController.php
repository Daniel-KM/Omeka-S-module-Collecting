<?php
namespace Collecting\Controller\Site;

use Collecting\Api\Representation\CollectingFormRepresentation;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('site', [], true);
        }
        $collectingForm = $this->api()
            ->read('collecting_forms', $this->params('form-id'))
            ->getContent();
        $form = $collectingForm->getForm();
        $form->setData($this->params()->fromPost());
        if ($form->isValid()) {
            $collectingData = $this->getCollectingData($collectingForm);
            $response = $this->api($form)->create('items', $collectingData['itemData']);
            if ($response->isSuccess()) {
                // @todo save input data (along with newly created item)
                exit('item created');
            }
        } else {
            $this->messenger()->addErrors($form->getMessages());
        }
        $view = new ViewModel;
        $view->setVariable('collectingForm', $collectingForm);
        $view->setVariable('refererUri', $this->getRequest()->getHeader('Referer')->getUri());
        return $view;
    }

    protected function getCollectingData(CollectingFormRepresentation $collectingForm)
    {
        // Derive the prompt IDs from the form names.
        $postedPrompts = [];
        foreach ($this->params()->fromPost() as $key => $value) {
            if (preg_match('/^prompt_(\d+)$/', $key, $matches)) {
                $postedPrompts[$matches[1]] = $value;
            }
        }

        $itemData = [];
        $inputData = [];

        // Note that we're iterating the known prompts, not the ones submitted
        // with the form. This way we accept only valid prompts.
        foreach ($collectingForm->prompts() as $prompt) {
            if (!isset($postedPrompts[$prompt->id()])) {
                // This prompt was not found in the POSTed data.
                continue;
            }
            switch ($prompt->type()) {
                case 'property':
                    $itemData[$prompt->property()->term()][] = [
                        'type' => 'literal',
                        'property_id' => $prompt->property()->id(),
                        '@value' => $postedPrompts[$prompt->id()],
                    ];
                    // Note that there's no break here. We need to save all
                    // property types as inputs so the relationship between the
                    // prompt and the user input isn't lost.
                case 'input':
                    $inputData[] = [
                        'item_id' => null,
                        'property_id' => $prompt->property() ? $prompt->property()->id() : null,
                        'prompt_id' => $prompt->id(),
                        'text' => $postedPrompts[$prompt->id()],
                    ];
                    break;
                case 'media':
                    break;
                default:
                    // Invalid prompt type. Do nothing.
                    break;
            }
        }

        return [
            'itemData' => $itemData,
            'inputData' => $inputData,
        ];
    }
}
