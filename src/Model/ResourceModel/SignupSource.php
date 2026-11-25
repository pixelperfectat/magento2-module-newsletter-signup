<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * One sign-up source row per newsletter subscriber
 */
class SignupSource extends AbstractDb
{
    public const TABLE = 'pixelperfect_newsletter_signup_source';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'subscriber_id');
    }

    /**
     * Source code recorded for the subscriber, or null when none is
     *
     * @param int $subscriberId
     * @return string|null
     */
    public function getSourceCode(int $subscriberId): ?string
    {
        $connection = $this->getConnection();
        $code = (string)$connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), ['source_code'])
                ->where('subscriber_id = ?', $subscriberId)
        );

        return $code === '' ? null : $code;
    }

    /**
     * Store the source; an existing row for the subscriber is left as it is
     *
     * @param int $subscriberId
     * @param string $code
     * @param string $recordedAt
     * @return void
     */
    public function saveSource(int $subscriberId, string $code, string $recordedAt): void
    {
        // Updating only the key column keeps a row a parallel request wrote first
        $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            ['subscriber_id' => $subscriberId, 'source_code' => $code, 'recorded_at' => $recordedAt],
            ['subscriber_id']
        );
    }
}
