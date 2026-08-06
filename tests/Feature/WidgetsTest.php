<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Livewire;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\ContainerResources\ContainerResource;
use Storix\Filament\Resources\ContainerResources\Pages\ListContainers;
use Storix\Filament\Widgets\ContainerFleetOverviewWidget;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

function stringifyWidgetValue(mixed $value): string
{
    if ($value instanceof Htmlable) {
        return $value->toHtml();
    }

    if (is_scalar($value) || $value === null) {
        return (string) $value;
    }

    return '';
}

/**
 * @return array<int, Stat>
 */
function containerFleetStats(): array
{
    /** @var array<int, Stat> $stats */
    $stats = (fn (): array => $this->getStats())->call(new ContainerFleetOverviewWidget());

    return $stats;
}

/**
 * @param  array<int, Stat>  $stats
 * @return array<string, Stat>
 */
function indexWidgetStats(array $stats): array
{
    return collect($stats)
        ->mapWithKeys(static fn (Stat $stat): array => [stringifyWidgetValue($stat->getLabel()) => $stat])
        ->all();
}

/**
 * @param  array<string, Stat>  $stats
 * @return array<string, string>
 */
function widgetStatValues(array $stats): array
{
    return collect($stats)
        ->map(static fn (Stat $stat): string => stringifyWidgetValue($stat->getValue()))
        ->all();
}

function recordPostedWidgetReturn(
    Dispatch $dispatch,
    Container $container,
    ReturnCondition $condition,
    string $date,
): void {
    $dispatchEntry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);
    $containerReturn = ContainerReturn::factory()->approved()->create([
        'customer_id' => $dispatch->customer_id,
        'transaction_date' => $date,
    ]);
    $returnEntry = ContainerReturnEntry::query()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $container->id,
        'return_condition' => $condition,
    ]);
    $returnEntry->forceFill(['dispatch_entry_id' => $dispatchEntry->id])->save();
}

it('registers one consolidated overview that renders exactly six stats', function (): void {
    $resourceWidgets = ContainerResource::getWidgets();
    $pageWidgets = (fn (): array => $this->getHeaderWidgets())->call(new ListContainers());
    $widget = new ContainerFleetOverviewWidget();
    $stats = (fn (): array => $this->getStats())->call($widget);
    $columns = (fn (): int|array|null => $this->getColumns())->call($widget);

    expect($resourceWidgets)
        ->toBe([ContainerFleetOverviewWidget::class])
        ->and($pageWidgets)->toBe($resourceWidgets)
        ->and($stats)->toHaveCount(6)
        ->and($columns)->toBe(['md' => 2, 'xl' => 3])
        ->and(array_keys(indexWidgetStats($stats)))->toBe([
            'Total Containers',
            'Containers In Use',
            'Fleet Utilization',
            'Return Damage Rate',
            'Average Dispatch Age',
            'Loss Exposure',
        ]);
});

it('renders a clear empty state with no division errors', function (): void {
    $stats = indexWidgetStats(containerFleetStats());

    expect(widgetStatValues($stats))->toBe([
        'Total Containers' => '0',
        'Containers In Use' => '0',
        'Fleet Utilization' => '0.00%',
        'Return Damage Rate' => '0.00%',
        'Average Dispatch Age' => '0 days',
        'Loss Exposure' => '0.00',
    ])->and(stringifyWidgetValue($stats['Fleet Utilization']->getDescription()))
        ->toBe('No fleet capacity data')
        ->and(stringifyWidgetValue($stats['Average Dispatch Age']->getDescription()))
        ->toBe('No open approved dispatches')
        ->and(stringifyWidgetValue($stats['Loss Exposure']->getDescription()))
        ->toBe('No recorded losses')
        ->and($stats['Fleet Utilization']->getColor())->toBe('gray')
        ->and($stats['Return Damage Rate']->getColor())->toBe('gray')
        ->and($stats['Average Dispatch Age']->getColor())->toBe('gray')
        ->and($stats['Loss Exposure']->getColor())->toBe('success');

    Livewire::test(ContainerFleetOverviewWidget::class)
        ->assertDontSee('Fleet overview')
        ->assertDontSee('Live lifecycle health')
        ->assertSee('No recorded losses');
});

it('combines lifecycle metrics while excluding drafts and soft-deleted containers', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-17 12:00:00'));

    try {
        $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
        $deliveryNote = DeliveryNote::query()->create(['name' => 'Fleet overview test']);
        $containers = Container::factory()->count(9)->create();

        $containers[4]->update(['replacement_cost' => 100.50, 'replacement_currency' => 'USD']);
        $containers[5]->update(['replacement_cost' => 25, 'replacement_currency' => 'USD']);
        $containers[6]->update(['is_active' => false]);

        $fiveDaysOld = Dispatch::query()->create([
            'dispatched_by' => $dispatcher->id,
            'delivery_note_id' => $deliveryNote->id,
            'quantity' => 1,
            'dispatched_at' => '2026-02-12 12:00:00',
            'state' => 'approved',
        ]);

        $twoDaysOld = Dispatch::query()->create([
            'dispatched_by' => $dispatcher->id,
            'delivery_note_id' => $deliveryNote->id,
            'quantity' => 1,
            'dispatched_at' => '2026-02-15 12:00:00',
            'state' => 'approved',
        ]);

        $returnedDispatch = Dispatch::query()->create([
            'dispatched_by' => $dispatcher->id,
            'delivery_note_id' => $deliveryNote->id,
            'quantity' => 1,
            'dispatched_at' => '2026-02-10 12:00:00',
            'state' => 'approved',
        ]);

        $draftDispatch = Dispatch::query()->create([
            'dispatched_by' => $dispatcher->id,
            'delivery_note_id' => $deliveryNote->id,
            'quantity' => 1,
            'dispatched_at' => '2026-01-01 12:00:00',
            'state' => 'draft',
        ]);

        DispatchEntry::query()->create([
            'dispatch_id' => $fiveDaysOld->id,
            'container_id' => $containers[0]->id,
        ]);

        DispatchEntry::query()->create([
            'dispatch_id' => $twoDaysOld->id,
            'container_id' => $containers[1]->id,
        ]);

        foreach ([
            [$containers[2], ReturnCondition::Good],
            [$containers[3], ReturnCondition::Damaged],
            [$containers[4], ReturnCondition::Lost],
            [$containers[5], ReturnCondition::Lost],
        ] as [$container, $condition]) {
            recordPostedWidgetReturn($returnedDispatch, $container, $condition, '2026-02-16');
        }

        DispatchEntry::query()->create([
            'dispatch_id' => $draftDispatch->id,
            'container_id' => $containers[7]->id,
        ]);

        recordPostedWidgetReturn(
            $returnedDispatch,
            $containers[8],
            ReturnCondition::Damaged,
            '2026-02-16',
        );

        $containers[8]->delete();

        $stats = indexWidgetStats(containerFleetStats());

        expect(widgetStatValues($stats))->toBe([
            'Total Containers' => '8',
            'Containers In Use' => '2',
            'Fleet Utilization' => '25.00%',
            'Return Damage Rate' => '25.00%',
            'Average Dispatch Age' => '3.5 days',
            'Loss Exposure' => 'USD 125.50',
        ])->and(stringifyWidgetValue($stats['Total Containers']->getDescription()))
            ->toBe('7 active · 1 inactive')
            ->and(stringifyWidgetValue($stats['Return Damage Rate']->getDescription()))
            ->toBe('1 damaged of 4 returned')
            ->and(stringifyWidgetValue($stats['Average Dispatch Age']->getDescription()))
            ->toBe('2 open · oldest 5 days')
            ->and(stringifyWidgetValue($stats['Loss Exposure']->getDescription()))
            ->toBe('2 lost containers')
            ->and($stats['Fleet Utilization']->getColor())->toBe('success')
            ->and($stats['Return Damage Rate']->getColor())->toBe('danger')
            ->and($stats['Average Dispatch Age']->getColor())->toBe('success')
            ->and($stats['Loss Exposure']->getColor())->toBe('danger');
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('flags tightening capacity and aging with warning treatments', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-01 12:00:00'));

    try {
        $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'warnings@example.com']);
        $deliveryNote = DeliveryNote::query()->create(['name' => 'Warning thresholds']);
        $containers = Container::factory()->count(4)->create();

        $dispatch = Dispatch::query()->create([
            'dispatched_by' => $dispatcher->id,
            'delivery_note_id' => $deliveryNote->id,
            'quantity' => 4,
            'dispatched_at' => '2026-02-15 12:00:00',
            'state' => 'approved',
        ]);

        foreach ($containers->take(3) as $container) {
            DispatchEntry::query()->create([
                'dispatch_id' => $dispatch->id,
                'container_id' => $container->id,
            ]);
        }

        foreach (range(1, 10) as $index) {
            recordPostedWidgetReturn(
                $dispatch,
                $containers[3],
                $index === 1 ? ReturnCondition::Damaged : ReturnCondition::Good,
                '2026-02-20',
            );
        }

        $stats = indexWidgetStats(containerFleetStats());

        expect($stats['Fleet Utilization']->getColor())->toBe('warning')
            ->and(stringifyWidgetValue($stats['Fleet Utilization']->getDescription()))
            ->toBe('Capacity is tightening')
            ->and($stats['Return Damage Rate']->getColor())->toBe('warning')
            ->and($stats['Average Dispatch Age']->getColor())->toBe('warning')
            ->and($stats['Fleet Utilization']->getDescriptionIcon())->toBe(Heroicon::OutlinedArrowTrendingUp)
            ->and($stats['Average Dispatch Age']->getDescriptionIcon())->toBe(Heroicon::OutlinedExclamationTriangle);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('keeps loss exposure separated by currency', function (): void {
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'losses@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Multi-currency losses']);
    $usdContainer = Container::factory()->create([
        'replacement_cost' => 100,
        'replacement_currency' => 'USD',
    ]);
    $kesContainer = Container::factory()->create([
        'replacement_cost' => 250,
        'replacement_currency' => 'KES',
    ]);

    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $dispatcher->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 2,
        'dispatched_at' => '2026-02-12',
        'state' => 'approved',
        'approved_at' => '2026-02-12 09:00:00',
    ]);

    foreach ([$usdContainer, $kesContainer] as $container) {
        recordPostedWidgetReturn($dispatch, $container, ReturnCondition::Lost, '2026-02-20');
    }

    $lossStat = indexWidgetStats(containerFleetStats())['Loss Exposure'];

    expect(stringifyWidgetValue($lossStat->getValue()))->toBe('2 currencies')
        ->and(stringifyWidgetValue($lossStat->getDescription()))
        ->toBe('2 lost · KES 250.00 · USD 100.00');
});
