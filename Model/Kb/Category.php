<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

use Magento\Framework\Model\AbstractModel;

class Category extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Category::class);
    }

    public function getCategoryId(): ?int
    {
        $id = $this->getData('category_id');
        return $id === null ? null : (int)$id;
    }

    public function getName(): string
    {
        return (string)$this->getData('name');
    }

    public function getIdentifier(): string
    {
        return (string)$this->getData('identifier');
    }
}
