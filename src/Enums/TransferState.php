<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Enums;

/**
 * A transfer session's state, from get_transfer_session_state.
 */
enum TransferState: string
{
    case Pending = 'PENDING';
    case TransferPending = 'TRANSFER_PENDING';
    case TransferInProgress = 'TRANSFER_INPROGRESS';
    case RestorePending = 'RESTORE_PENDING';
    case RestoreInProgress = 'RESTORE_INPROGRESS';
    case Running = 'RUNNING';
    case Paused = 'PAUSED';
    case Completed = 'COMPLETED';
    case Aborted = 'ABORTED';
    case Failed = 'FAILED';

    /**
     * A state this package does not know yet; newer cPanel versions may add some.
     */
    case Unknown = 'UNKNOWN';

    /**
     * Whether the session has stopped for good: completed, aborted or failed.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Aborted, self::Failed], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }
}
