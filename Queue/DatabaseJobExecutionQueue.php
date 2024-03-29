<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\BatchQueueBundle\Queue;

use Akeneo\Tool\Bundle\BatchQueueBundle\Queue\JobExecutionMessage;
use Akeneo\Tool\Bundle\BatchQueueBundle\Queue\JobExecutionQueueInterface;

/**
 * Aims to publish and consume job execution messages in a queue stored in database.
 *
 * It uses directly the DBAL to avoid any memory leak or connection problem due to the Unit of Work.
 *
 * @author    Alexandre Hocquard <alexandre.hocquard@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class DatabaseJobExecutionQueue implements JobExecutionQueueInterface
{
    /** Interval in seconds before checking if a new message is in the queue. */
    const QUEUE_CHECK_INTERVAL = 5;

    /** @var JobExecutionMessageRepository */
    private $jobExecutionMessageRepository;

    public function __construct(JobExecutionMessageRepository $jobExecutionMessageRepository)
    {
        $this->jobExecutionMessageRepository = $jobExecutionMessageRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function publish(JobExecutionMessage $jobExecutionMessage): void
    {
        $this->jobExecutionMessageRepository->createJobExecutionMessage($jobExecutionMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function consume(string $consumer, array $jobInstanceCodes = []): JobExecutionMessage
    {
        $hasBeenUpdated = false;

        do {
            if (empty($jobInstanceCodes)) {
                $jobExecutionMessage = $this->jobExecutionMessageRepository->getAvailableJobExecutionMessage();
            } else {
                $jobExecutionMessage = $this->jobExecutionMessageRepository->getAvailableJobExecutionMessageFilteredByCodes($jobInstanceCodes);
            }

            if (null !== $jobExecutionMessage) {
                $jobExecutionMessage->consumedBy($consumer);
                $hasBeenUpdated = $this->jobExecutionMessageRepository->updateJobExecutionMessage($jobExecutionMessage);
            }
        } while (!$hasBeenUpdated && 0 === sleep(self::QUEUE_CHECK_INTERVAL));

        return $jobExecutionMessage;
    }
}
