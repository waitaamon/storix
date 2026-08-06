<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Livewire\Component;
use Livewire\Livewire;
use Storix\Filament\Resources\DispatchResources\Schemas\DispatchForm;
use Storix\Models\Dispatch;
use Storix\Support\DefaultCustomerQueryModifier;
use Storix\Support\DefaultDeliveryNoteQueryModifier;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\CustomerCategory;
use Storix\Tests\Fixtures\Models\DeliveryNote;

final class DispatchCustomerFormTestComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->getSchema('form')?->fill();
    }

    public function form(Schema $schema): Schema
    {
        return DispatchForm::configure($schema)
            ->model(Dispatch::class)
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

afterEach(function (): void {
    Config::set('storix.customer_query_modifier', DefaultCustomerQueryModifier::class);
    Config::set('storix.delivery_note_query_modifier', DefaultDeliveryNoteQueryModifier::class);
});

it('limits customer options to accounts receivable accounts', function (): void {
    $receivableCategory = CustomerCategory::query()->create([
        'name' => 'Accounts Receivable',
        'slug' => 'accounts-receivable',
    ]);
    $payableCategory = CustomerCategory::query()->create([
        'name' => 'Accounts Payable',
        'slug' => 'accounts-payable',
    ]);
    $customer = Customer::query()->create([
        'name' => 'Customer',
        'account_category_id' => $receivableCategory->id,
    ]);
    $supplier = Customer::query()->create([
        'name' => 'Supplier',
        'account_category_id' => $payableCategory->id,
    ]);

    $component = Livewire::test(DispatchCustomerFormTestComponent::class);
    $instance = $component->instance();

    if (! $instance instanceof DispatchCustomerFormTestComponent) {
        throw new LogicException('The Livewire component is not the dispatch customer form test component.');
    }

    $customerSelect = $instance->getSchema('form')?->getFlatComponents(withHidden: true)['customer_id'] ?? null;

    if (! $customerSelect instanceof Select) {
        throw new LogicException('The dispatch customer field is not a select.');
    }

    $options = $customerSelect->getOptions();

    expect($options)->toHaveKey($customer->id)
        ->and(array_key_exists($supplier->id, $options))->toBeFalse();
});

it('requires a customer and limits delivery-note options to that customer', function (): void {
    Config::set(
        'storix.delivery_note_query_modifier',
        static fn (Builder $query): Builder => $query->whereNull('dispatched_at'),
    );

    $selectedCustomer = Customer::query()->create(['name' => 'Selected Customer']);
    $otherCustomer = Customer::query()->create(['name' => 'Other Customer']);
    $selectedNote = DeliveryNote::query()->create([
        'name' => 'Selected delivery',
        'customer_id' => $selectedCustomer->id,
    ]);
    $otherNote = DeliveryNote::query()->create([
        'name' => 'Other delivery',
        'customer_id' => $otherCustomer->id,
    ]);

    $component = Livewire::test(DispatchCustomerFormTestComponent::class)
        ->set('data.customer_id', $selectedCustomer->id);
    $instance = $component->instance();

    if (! $instance instanceof DispatchCustomerFormTestComponent) {
        throw new LogicException('The Livewire component is not the dispatch customer form test component.');
    }

    $schema = $instance->getSchema('form');
    $fields = $schema?->getFlatComponents(withHidden: true) ?? [];
    $customer = $fields['customer_id'] ?? null;
    $deliveryNote = $fields['delivery_note_id'] ?? null;

    if (! $customer instanceof Select || ! $deliveryNote instanceof Select) {
        throw new LogicException('The dispatch customer fields are not selects.');
    }

    $options = $deliveryNote->getOptions();

    expect($customer->isRequired())->toBeTrue()
        ->and($options)->toHaveKey($selectedNote->id)
        ->and(array_key_exists($otherNote->id, $options))->toBeFalse();
});
