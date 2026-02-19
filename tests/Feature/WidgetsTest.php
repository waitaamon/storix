<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Widgets\ContainerAgingReportWidget;
use Storix\Filament\Widgets\ContainerUtilizationWidget;
use Storix\Filament\Widgets\DamageRateWidget;
use Storix\Filament\Widgets\LostExposureWidget;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

/**
 * @param  array<int, Stat>  $stats
 * @return array<string, string>
 */
function widgetStatValues(array $stats): array
{
    return collect($stats)
        ->mapWithKeys(static fn (Stat $stat): array => [(string) $stat->getLabel() => (string) $stat->getValue()])
        ->all();
}

/**
 * @return array<int, Stat>
 */
function widgetStatsFor(object $widget): array
{
    /** @var array<int, Stat> $resolved */
    $resolved = (fn (): array => $this->getStats())->call($widget);

    return $resolved;
}

it('computes container utilization from active dispatch entries', function (): void {
    $customer = Customer::query()->create(['name' => 'Acme Industries']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);

    [$containerOne, $containerTwo, $containerThree, $containerFour] = Container::factory()->count(4)->create();

    $dispatchA = Dispatch::query()->create([
        'customer_id' => $customer->id,
        'dispatched_by' => $dispatcher->id,
        'delivery_note_id' => 'Dispatch A',
        'dispatched_at' => '2026-02-12',
    ]);

    $dispatchB = Dispatch::query()->create([
        'customer_id' => $customer->id,
        'dispatched_by' => $dispatcher->id,
        'delivery_note_id' => 'Dispatch B',
        'dispatched_at' => '2026-02-13',
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatchA->id,
        'container_id' => $containerOne->id,
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatchB->id,
        'container_id' => $containerOne->id,
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatchA->id,
        'container_id' => $containerTwo->id,
        'return_date' => '2026-02-15',
        'return_condition' => ReturnCondition::Good,
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatchB->id,
        'container_id' => $containerThree->id,
        'return_date' => '2026-02-16',
        'return_condition' => ReturnCondition::Damaged,
    ]);

    $stats = widgetStatValues(widgetStatsFor(new ContainerUtilizationWidget()));

    expect($stats)->toBe([
        'Total Containers' => '4',
        'Containers In Use' => '1',
        'Utilization %' => '25.00%',
    ]);
});

it('computes damage rate from returned entries only', function (): void {
    $customer = Customer::query()->create(['name' => 'Acme Industries']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $containers = Container::factory()->count(3)->create();

    $dispatch = Dispatch::query()->create([
        'customer_id' => $customer->id,
        'dispatched_by' => $dispatcher->id,
        'delivery_note_id' => 'Damage check',
        'dispatched_at' => '2026-02-12',
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $containers[0]->id,
        'return_date' => '2026-02-14',
        'return_condition' => ReturnCondition::Good,
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $containers[1]->id,
        'return_date' => '2026-02-15',
        'return_condition' => ReturnCondition::Damaged,
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $containers[2]->id,
        'return_condition' => ReturnCondition::Damaged,
    ]);

    $stats = widgetStatValues(widgetStatsFor(new DamageRateWidget()));

    expect($stats)->toBe([
        'Returned Dispatches' => '2',
        'Damaged Returns' => '1',
        'Damage Rate' => '50.00%',
    ]);
});

it('computes aging metrics from open dispatch entries', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-17'));

    try {
        $customer = Customer::query()->create(['name' => 'Acme Industries']);
        $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
        $containers = Container::factory()->count(3)->create();

        $dispatchFiveDays = Dispatch::query()->create([
            'customer_id' => $customer->id,
            'dispatched_by' => $dispatcher->id,
            'delivery_note_id' => 'Five day',
            'dispatched_at' => '2026-02-12',
        ]);

        $dispatchTwoDays = Dispatch::query()->create([
            'customer_id' => $customer->id,
            'dispatched_by' => $dispatcher->id,
            'delivery_note_id' => 'Two day',
            'dispatched_at' => '2026-02-15',
        ]);

        $dispatchReturned = Dispatch::query()->create([
            'customer_id' => $customer->id,
            'dispatched_by' => $dispatcher->id,
            'delivery_note_id' => 'Returned',
            'dispatched_at' => '2026-02-14',
        ]);

        DispatchEntry::query()->create([
            'dispatch_id' => $dispatchFiveDays->id,
            'container_id' => $containers[0]->id,
        ]);

        DispatchEntry::query()->create([
            'dispatch_id' => $dispatchTwoDays->id,
            'container_id' => $containers[1]->id,
        ]);

        DispatchEntry::query()->create([
            'dispatch_id' => $dispatchReturned->id,
            'container_id' => $containers[2]->id,
            'return_date' => '2026-02-16',
            'return_condition' => ReturnCondition::Good,
        ]);

        $stats = widgetStatValues(widgetStatsFor(new ContainerAgingReportWidget()));

        expect($stats)->toBe([
            'Open Dispatches' => '2',
            'Average Aging (days)' => '3.5',
            'Oldest Open (days)' => '5',
        ]);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('computes lost exposure from container replacement costs', function (): void {
    $customer = Customer::query()->create(['name' => 'Acme Industries']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);

    $lostOne = Container::factory()->create(['metadata' => ['replacement_cost' => 100.50]]);
    $lostTwo = Container::factory()->create(['metadata' => ['replacement_cost' => 25]]);
    $good = Container::factory()->create(['metadata' => ['replacement_cost' => 9999]]);

    $dispatch = Dispatch::query()->create([
        'customer_id' => $customer->id,
        'dispatched_by' => $dispatcher->id,
        'delivery_note_id' => 'Loss report',
        'dispatched_at' => '2026-02-12',
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $lostOne->id,
        'return_condition' => ReturnCondition::Lost,
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $lostTwo->id,
        'return_condition' => ReturnCondition::Lost,
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $good->id,
        'return_condition' => ReturnCondition::Good,
    ]);

    $stats = widgetStatValues(widgetStatsFor(new LostExposureWidget()));

    expect($stats)->toBe([
        'Lost Containers' => '2',
        'Estimated Exposure' => '125.50',
    ]);
});
