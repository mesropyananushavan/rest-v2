<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Orders\Application\ListTableOccupancy;
use App\Modules\Orders\Application\OpenOrder;
use App\Modules\Orders\Application\TableOccupancy;
use App\Modules\Orders\Domain\OrdersDomainException;
use App\Modules\Tables\Contracts\HallLayout;
use App\Modules\Tables\Contracts\HallLayoutReader;
use App\Modules\Tables\Contracts\TableLayout;
use App\Modules\Tenancy\Contracts\BranchContext;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class OrderBoard extends Component
{
    public ?int $selectedTableId = null;

    public int $guestCount = 1;

    public string $comment = '';

    public bool $openModalVisible = false;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $status = session()->pull('status');

        if (is_string($status) && $status !== '') {
            $this->statusMessage = $status;
        }
    }

    public function render(): View
    {
        return view('livewire.admin.order-board', [
            'halls' => $this->halls(),
        ]);
    }

    public function selectTable(int $tableId): void
    {
        $this->authorizeTakingOrders();
        $this->statusMessage = null;
        $this->errorMessage = null;

        if (! $this->tableExistsInActiveBranch($tableId)) {
            $this->resetOpenModal();
            $this->errorMessage = __('orders.board.flash.table_not_found');

            return;
        }

        if ($this->tableIsOccupied($tableId)) {
            $this->resetOpenModal();
            $this->errorMessage = __('orders.board.flash.already_occupied');

            return;
        }

        $this->selectedTableId = $tableId;
        $this->guestCount = 1;
        $this->comment = '';
        $this->openModalVisible = true;
        $this->resetValidation();
    }

    public function openOrder(): void
    {
        $this->authorizeTakingOrders();
        $this->statusMessage = null;
        $this->errorMessage = null;

        $this->validate($this->rules(), $this->validationMessages(), $this->validationAttributes());

        $tableId = $this->selectedTableId();

        try {
            app(OpenOrder::class)(
                $tableId,
                clientCount: $this->guestCount,
                comment: $this->normalizedComment(),
            );
        } catch (OrdersDomainException $exception) {
            if ($exception->errorCode() === 'orders.table_already_open') {
                $this->resetOpenModal();
                $this->errorMessage = __('orders.board.flash.already_occupied');

                return;
            }

            if ($exception->errorCode() === 'orders.table_not_found') {
                $this->resetOpenModal();
                $this->errorMessage = __('orders.board.flash.table_not_found');

                return;
            }

            throw $exception;
        }

        $this->resetOpenModal();
        $this->statusMessage = __('orders.board.flash.opened');
    }

    public function cancelOpen(): void
    {
        $this->resetOpenModal();
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     color: string,
     *     sort_order: int,
     *     tables: list<array{
     *         id: int,
     *         name: string,
     *         type: string,
     *         shape: string,
     *         sort_order: int,
     *         occupied: bool,
     *         occupancy: array{
     *             order_id: int,
     *             workspace_url: string,
     *             client_count: int,
     *             opened_at: string,
     *             duration_minutes: int,
     *             total: string
     *         }|null
     *     }>
     * }>
     */
    private function halls(): array
    {
        $branchId = app(BranchContext::class)->id();

        abort_if($branchId === null, 404);

        /** @var list<HallLayout> $layout */
        $layout = app(HallLayoutReader::class)->layoutForBranch($branchId);
        /** @var array<int, TableOccupancy> $occupancy */
        $occupancy = app(ListTableOccupancy::class)();
        $locale = app()->getLocale();

        return array_map(
            fn (HallLayout $hall): array => [
                'id' => $hall->id,
                'name' => $hall->name->forLocale($locale, 'en'),
                'color' => $hall->color,
                'sort_order' => $hall->sortOrder,
                'tables' => array_map(
                    fn (TableLayout $table): array => $this->table($table, $occupancy[$table->id] ?? null, $locale),
                    $hall->tables,
                ),
            ],
            $layout,
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     type: string,
     *     shape: string,
     *     sort_order: int,
     *     occupied: bool,
     *     occupancy: array{
     *         order_id: int,
     *         workspace_url: string,
     *         client_count: int,
     *         opened_at: string,
     *         duration_minutes: int,
     *         total: string
     *     }|null
     * }
     */
    private function table(TableLayout $table, ?TableOccupancy $occupancy, string $locale): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name->forLocale($locale, 'en'),
            'type' => $table->type,
            'shape' => $table->shape,
            'sort_order' => $table->sortOrder,
            'occupied' => $occupancy !== null,
            'occupancy' => $occupancy === null ? null : [
                'order_id' => $occupancy->orderId,
                'workspace_url' => route('admin.orders.workspace', ['order' => $occupancy->orderId]),
                'client_count' => $occupancy->clientCount,
                'opened_at' => $occupancy->openedAt->format('H:i'),
                'duration_minutes' => $this->durationMinutes($occupancy),
                'total' => MoneyFormatter::format(new Money($occupancy->totalMinor, $occupancy->currency), $locale),
            ],
        ];
    }

    private function durationMinutes(TableOccupancy $occupancy): int
    {
        return max(0, intdiv(time() - $occupancy->openedAt->getTimestamp(), 60));
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'selectedTableId' => ['required', 'integer', 'min:1'],
            'guestCount' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationMessages(): array
    {
        return [
            'guestCount.min' => __('orders.board.validation.guest_count_min'),
            'selectedTableId.required' => __('orders.board.validation.table_required'),
            'comment.max' => __('orders.board.validation.comment_max'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'guestCount' => __('orders.board.form.guests'),
            'comment' => __('orders.board.form.comment'),
            'selectedTableId' => __('orders.board.form.table'),
        ];
    }

    private function selectedTableId(): int
    {
        if ($this->selectedTableId === null) {
            abort(404);
        }

        return $this->selectedTableId;
    }

    private function normalizedComment(): ?string
    {
        $comment = trim($this->comment);

        return $comment === '' ? null : $comment;
    }

    private function tableExistsInActiveBranch(int $tableId): bool
    {
        $branchId = app(BranchContext::class)->id();

        if ($branchId === null) {
            return false;
        }

        foreach (app(HallLayoutReader::class)->layoutForBranch($branchId) as $hall) {
            foreach ($hall->tables as $table) {
                if ($table->id === $tableId) {
                    return true;
                }
            }
        }

        return false;
    }

    private function tableIsOccupied(int $tableId): bool
    {
        return isset(app(ListTableOccupancy::class)()[$tableId]);
    }

    private function resetOpenModal(): void
    {
        $this->selectedTableId = null;
        $this->guestCount = 1;
        $this->comment = '';
        $this->openModalVisible = false;
        $this->resetValidation();
    }

    private function authorizeTakingOrders(): void
    {
        abort_unless(auth()->user()?->can('orders.take') ?? false, 403);
    }
}
