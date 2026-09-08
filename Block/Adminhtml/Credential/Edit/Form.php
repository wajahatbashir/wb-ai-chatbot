<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Credential\Edit;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use WB\AiChatbot\Model\Credential;
use WB\AiChatbot\Model\Provider\Pool;
use WB\AiChatbot\Model\Source\ProviderCode;

class Form extends Generic
{
    /**
     * @var ProviderCode
     */
    private $providerCode;

    /**
     * @var Pool
     */
    private $pool;

    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        ProviderCode $providerCode,
        Pool $pool,
        array $data = []
    ) {
        $this->providerCode = $providerCode;
        $this->pool = $pool;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    protected function _construct(): void
    {
        parent::_construct();
        $this->setId('credential_edit_form');
        $this->setTitle(__('Provider Credential'));
    }

    protected function _prepareForm(): self
    {
        /** @var Credential $model */
        $model = $this->_coreRegistry->registry('current_model');

        $form = $this->_formFactory->create([
            'data' => ['id' => 'edit_form', 'action' => $this->getUrl('*/*/save', ['_current' => true]), 'method' => 'post'],
        ]);
        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Provider Credential')]);

        if ($model->getCredentialId()) {
            $fieldset->addField('credential_id', 'hidden', ['name' => 'credential_id']);
        }

        $fieldset->addField('alias', 'text', [
            'label' => __('Alias'),
            'name' => 'alias',
            'required' => true,
            'note' => __('A name for this key, e.g. "OpenAI - main" or "Claude - backup".'),
        ]);

        $fieldset->addField('provider_code', 'select', [
            'label' => __('Provider'),
            'name' => 'provider_code',
            'required' => true,
            'values' => $this->providerCode->toOptionArray(),
        ]);

        $fieldset->addField('api_key', 'password', [
            'label' => __('API Key'),
            'name' => 'api_key',
            'autocomplete' => 'new-password',
            'note' => $model->getApiKey()
                ? __('A key is stored (encrypted). Leave this empty to keep it, or paste a new key to replace it.')
                : __('Paste the key from your provider account (OpenAI: sk-..., Anthropic: sk-ant-..., Google AI Studio, xAI). Stored encrypted; never sent to the browser. Not needed for the Mock provider.'),
        ]);

        $fieldset->addField('model', 'text', [
            'label' => __('Chat Model'),
            'name' => 'model',
            'note' => __('Leave empty for the provider\'s recommended default. Known models: %1', $this->modelHint('chat')),
        ]);

        $fieldset->addField('embedding_model', 'text', [
            'label' => __('Embedding Model'),
            'name' => 'embedding_model',
            'note' => __('Used to index the knowledge base. Leave empty for the default. Known models: %1', $this->modelHint('embedding')),
        ]);

        $fieldset->addField('supports_vision', 'select', [
            'label' => __('Supports Image Input'),
            'name' => 'supports_vision',
            'values' => [
                ['value' => '1', 'label' => __('Yes')],
                ['value' => '0', 'label' => __('No')],
            ],
            'note' => __('Enables "search by photo" when this credential is the active one.'),
        ]);

        $fieldset->addField('sort_order', 'text', [
            'label' => __('Fallback Order'),
            'name' => 'sort_order',
            'class' => 'validate-number',
            'note' => __('Lower numbers are tried first. If a key fails (quota, rate limit, outage) the next one is used automatically.'),
        ]);

        $fieldset->addField('is_enabled', 'select', [
            'label' => __('Enabled'),
            'name' => 'is_enabled',
            'values' => [
                ['value' => '1', 'label' => __('Yes')],
                ['value' => '0', 'label' => __('No')],
            ],
        ]);

        if ($model->getCredentialId()) {
            $fieldset->addField('status_display', 'note', [
                'label' => __('Last Check'),
                'text' => $this->escapeHtml(sprintf(
                    '%s - %s (%s)',
                    $model->getStatus(),
                    (string)$model->getStatusMessage(),
                    (string)($model->getLastCheckedAt() ?: __('never'))
                )),
            ]);
        }

        if (!$model->getId()) {
            $model->setData('is_enabled', '1');
            $model->setData('supports_vision', '1');
            $model->setData('sort_order', '10');
        }
        $values = $model->getData();
        unset($values['api_key']); // never echo the stored (encrypted) key into the form
        $form->setValues($values);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    private function modelHint(string $type): string
    {
        $hints = [];
        foreach ($this->pool->getProviders() as $provider) {
            $models = $type === 'chat' ? $provider->getChatModels() : $provider->getEmbeddingModels();
            if ($models === []) {
                continue;
            }
            $hints[] = $provider->getLabel() . ': ' . implode(', ', array_keys($models));
        }
        return implode(' | ', $hints);
    }
}
