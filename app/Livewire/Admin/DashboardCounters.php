<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Menu\Application\CountMenuDashboardMetrics;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class DashboardCounters extends Component
{
    public int $categoryCount = 0;

    public int $itemCount = 0;

    public function mount(): void
    {
        $this->loadCounts();
    }

    public function render(): View
    {
        return view('livewire.admin.dashboard-counters');
    }

    private function loadCounts(): void
    {
        $counter = app(CountMenuDashboardMetrics::class);
        $metrics = $counter();

        $this->categoryCount = $metrics['categories'];
        $this->itemCount = $metrics['items'];
    }
}
