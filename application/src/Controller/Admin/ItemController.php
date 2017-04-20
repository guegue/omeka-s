<?php
namespace Omeka\Controller\Admin;

use Omeka\Form\ConfirmForm;
use Omeka\Form\ResourceForm;
use Omeka\Form\ResourceBatchUpdateForm;
use Omeka\Job\Dispatcher;
use Omeka\Media\Ingester\Manager;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Form\Form;

class ItemController extends AbstractActionController
{
    /**
     * @var Manager
     */
    protected $mediaIngesters;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @param Manager $mediaIngesters
     * @param Dispatcher $dispatcher
     */
    public function __construct(Manager $mediaIngesters, Dispatcher $dispatcher)
    {
        $this->mediaIngesters = $mediaIngesters;
        $this->dispatcher = $dispatcher;
    }

    public function searchAction()
    {
        $view = new ViewModel;
        $view->setVariable('query', $this->params()->fromQuery());
        return $view;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('items', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $items = $response->getContent();
        $view->setVariable('items', $items);
        $view->setVariable('resources', $items);
        return $view;
    }

    public function showAction()
    {
        $response = $this->api()->read('items', $this->params('id'));

        $view = new ViewModel;
        $item = $response->getContent();
        $view->setVariable('item', $item);
        $view->setVariable('resource', $item);
        return $view;
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('items', $this->params('id'));
        $item = $response->getContent();
        $values = $item->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('resource', $item);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function sidebarSelectAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('items', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setVariable('items', $response->getContent());
        $value = $this->params()->fromQuery('value');
        $view->setVariable('searchValue', $value ? $value['in'][0] : '');
        $view->setVariable('showDetails', true);
        $view->setTerminal(true);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('items', $this->params('id'));
        $item = $response->getContent();
        $values = $item->valueRepresentation();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $item);
        $view->setVariable('resourceLabel', 'item');
        $view->setVariable('partialPath', 'omeka/admin/item/show-details');
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('item', $item);
        $view->setVariable('values', json_encode($values));
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('items', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Item successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/default',
            ['action' => 'browse'],
            true
        );
    }

    public function batchDeleteConfirmAction()
    {
        $form = $this->getForm(ConfirmForm::class);
        $routeAction = $this->params()->fromQuery('all') ? 'batch-delete-all' : 'batch-delete';
        $form->setAttribute('action', $this->url()->fromRoute(null, ['action' => $routeAction], true));
        $form->setButtonLabel('Confirm Delete'); // @translate
        $form->setAttribute('id', 'batch-delete-confirm');
        $form->setAttribute('class', $routeAction);

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('form', $form);
        return $view;
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one item to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('items', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Items successfully deleted'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
                $job = $this->dispatcher->dispatch('Omeka\Job\BatchDelete', [
                    'resource' => 'items',
                    'query' => $query,
                ]);
                $this->messenger()->addSuccess('Deleting items. This may take a while.'); // @translate
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function addAction()
    {
        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('action', $this->url()->fromRoute(null, [], true));
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('id', 'add-item');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $fileData = $this->getRequest()->getFiles()->toArray();
                $response = $this->api($form)->create('items', $data, $fileData);
                if ($response) {
                    $message = new Message(
                        'Item successfully created. %s', // @translate
                        sprintf(
                            '<a href="%s">%s</a>',
                            htmlspecialchars($this->url()->fromRoute(null, [], true)),
                            $this->translate('Add another item?')
                        ));
                    $message->setEscapeHtml(false);
                    $this->messenger()->addSuccess($message);
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('mediaForms', $this->getMediaForms());
        return $view;
    }

    public function editAction()
    {
        $form = $this->getForm(ResourceForm::class);
        $form->setAttribute('action', $this->url()->fromRoute(null, [], true));
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('id', 'edit-item');
        $item = $this->api()->read('items', $this->params('id'))->getContent();

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $fileData = $this->getRequest()->getFiles()->toArray();
                $response = $this->api($form)->update('items', $this->params('id'), $data, $fileData);
                if ($response) {
                    $this->messenger()->addSuccess('Item successfully updated'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('item', $item);
        $view->setVariable('mediaForms', $this->getMediaForms());
        return $view;
    }

    /**
     * Batch update selected items.
     */
    public function batchEditAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one item to batch edit.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ResourceBatchUpdateForm::class, ['resource_type' => 'item']);
        if ($this->params()->fromPost('batch_update')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                list($dataRemove, $dataAppend) = $this->preprocessBatchUpdateData($data);

                $this->api($form)->batchUpdate('items', $resourceIds, $dataRemove, [
                    'continueOnError' => true,
                    'collectionAction' => 'remove',
                ]);
                $this->api($form)->batchUpdate('items', $resourceIds, $dataAppend, [
                    'continueOnError' => true,
                    'collectionAction' => 'append',
                ]);

                $this->messenger()->addSuccess('Items successfully edited'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('resourceIds', $resourceIds);
        $view->setVariable('query', []);
        return $view;
    }

    /**
     * Batch update all items returned from a query.
     */
    public function batchEditAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);

        $form = $this->getForm(ResourceBatchUpdateForm::class, ['resource_type' => 'item']);
        if ($this->params()->fromPost('batch_update')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                list($dataRemove, $dataAppend) = $this->preprocessBatchUpdateData($data);

                $job = $this->dispatcher->dispatch('Omeka\Job\BatchUpdate', [
                    'resource' => 'items',
                    'query' => $query,
                    'data_remove' => $dataRemove,
                    'data_append' => $dataAppend,
                ]);

                $this->messenger()->addSuccess('Editing items. This may take a while.'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setTemplate('omeka/admin/item/batch-edit.phtml');
        $view->setVariable('form', $form);
        $view->setVariable('resourceIds', []);
        $view->setVariable('query', $query);
        return $view;

    }

    /**
     * Preprocess batch update data.
     *
     * Batch update data contains instructions on what to update. It needs to be
     * preprocessed before it's sent to the API.
     *
     * @param array $data
     * @return array An array containing the collectionAction=remove data as the
     * first element and the collectionAction=append data as the second.
     */
    protected function preprocessBatchUpdateData(array $data)
    {
        $dataRemove = [];
        $dataAppend = [];

        // Set the data to change and data to remove.
        if (in_array($data['is_public'], ['0', '1'])) {
            $dataRemove['o:is_public'] = $data['is_public'];
        }
        if ($data['resource_template_unset']) {
            $dataRemove['o:resource_template'] = ['o:id' => null];
        } elseif (is_numeric($data['resource_template'])) {
            $dataRemove['o:resource_template'] = ['o:id' => $data['resource_template']];
        }
        if ($data['resource_class_unset']) {
            $dataRemove['o:resource_class'] = ['o:id' => null];
        } elseif (is_numeric($data['resource_class'])) {
            $dataRemove['o:resource_class'] = ['o:id' => $data['resource_class']];
        }
        if (is_numeric($data['remove_from_item_set'][0])) {
            $dataRemove['o:item_set'] = $data['remove_from_item_set'];
        }
        if (is_numeric($data['clear_property_values'][0])) {
            $dataRemove['clear_property_values'] = $data['clear_property_values'];
        }

        // Set the data to append.
        $dataAppend['values'] = $data['value'];
        if (is_numeric($data['add_to_item_set'][0])) {
            $dataAppend['o:item_set'] = $data['add_to_item_set'];
        }

        return [$dataRemove, $dataAppend];
    }

    protected function getMediaForms()
    {
        $mediaHelper = $this->viewHelpers()->get('media');
        $forms = [];
        foreach ($this->mediaIngesters->getRegisteredNames() as $ingester) {
            $forms[$ingester] = [
                'label' => $this->mediaIngesters->get($ingester)->getLabel(),
                'form' => $mediaHelper->form($ingester),
            ];
        }
        return $forms;
    }
}
