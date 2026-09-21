<?php

namespace App\Support;

use App\Enums\DriverExpenseStatus;
use App\Models\Driver;
use App\Models\TransferDriverExpense;
use Carbon\Carbon;

/**
 * What the cabinet shows about one expense. Views get this, never the model, so a template cannot reach
 * the storage path of the receipt — only the authenticated URL that serves it.
 */
final class DriverExpensePresenter
{
    public readonly int $id;

    public readonly string $type;

    public readonly string $amount;

    public readonly DriverExpenseStatus $status;

    /** Only set when it was NOT entered by the viewer (a dispatcher looking at a driver's entry, and vice versa). */
    public readonly ?string $addedBy;

    public readonly Carbon $createdAt;

    public readonly string $receiptUrl;

    public readonly bool $canDelete;

    /** Why an operator rejected it — shown so the person can see what to fix. */
    public readonly ?string $reviewNote;

    public function __construct(TransferDriverExpense $expense, Driver $viewer)
    {
        $this->id = $expense->id;
        $this->type = $expense->type->getLabel();
        $this->amount = $expense->formattedAmount();
        $this->status = $expense->status;
        $this->createdAt = $expense->created_at->copy()->timezone('Asia/Tashkent');
        $this->receiptUrl = route('driver.expenses.receipt', $expense->id);
        $this->canDelete = $expense->canBeDeletedBy($viewer);
        $this->reviewNote = $expense->status === DriverExpenseStatus::Rejected && filled($expense->review_note)
            ? $expense->review_note
            : null;

        $this->addedBy = $expense->driver_id !== null && $expense->driver_id !== $viewer->getKey()
            ? ($expense->addedBy?->name ?? '—')
            : null;
    }
}
