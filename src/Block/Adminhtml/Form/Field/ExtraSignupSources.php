<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Code and label rows for sign-up sources a shop adds itself
 */
class ExtraSignupSources extends AbstractFieldArray
{
    /**
     * @inheritDoc
     */
    protected function _prepareToRender()
    {
        $this->addColumn('code', ['label' => __('Code'), 'class' => 'required-entry validate-code']);
        $this->addColumn('label', ['label' => __('Label'), 'class' => 'required-entry']);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add Source');
    }
}
