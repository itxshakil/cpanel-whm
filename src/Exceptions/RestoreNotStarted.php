<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Exceptions;

/**
 * The restore was queued, but starting the queue failed. The task is still
 * waiting on the server: start it with Backups::activateQueue() rather than
 * queueing it again, or the account is restored twice.
 */
final class RestoreNotStarted extends WhmException
{
    public function __construct(
        private readonly ?string $queueId,
        WhmException $previous,
    ) {
        parent::__construct("The restore was queued (queue id {$queueId}), but the queue could not be started: {$previous->getMessage()}", 0, $previous);
    }

    /**
     * The id WHM gave the queued restore, to track with Backups::queue().
     */
    public function queueId(): ?string
    {
        return $this->queueId;
    }

    public function hint(): string
    {
        return 'Do not queue the restore again. Start the waiting task with Whm::backups()->activateQueue(), or from WHM › Restore a Full Backup/cpmove File.';
    }
}
