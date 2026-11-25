<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Model\ResourceModel\Subscriber\Grid;

use Magento\Newsletter\Model\ResourceModel\Subscriber\Grid\Collection as SubscriberGridCollection;
use PixelPerfect\NewsletterSignup\Model\ResourceModel\SignupSource;

/**
 * Admin subscriber grid collection with the sign-up source joined in
 */
class Collection extends SubscriberGridCollection
{
    /**
     * @inheritDoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['signup_source' => $this->getTable(SignupSource::TABLE)],
            'signup_source.subscriber_id = main_table.subscriber_id',
            ['signup_source' => 'signup_source.source_code']
        );
        $this->addFilterToMap('signup_source', 'signup_source.source_code');

        return $this;
    }
}
