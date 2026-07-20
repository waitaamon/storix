<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Mockery\MockInterface;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\DispatchEntriesResources\DispatchEntryResource;
use Storix\Filament\Resources\DispatchEntriesResources\Schemas\ReceiveContainerReturnForm;

final class DispatchEntryResourceSchemaTestComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function render(): string
    {
        return '<div></div>';
    }
}

it('configures the reusable container return fields', function (): void {
    $schema = ReceiveContainerReturnForm::configure(Schema::make(new DispatchEntryResourceSchemaTestComponent));
    $components = $schema->getFlatComponents(withHidden: true);
    $returnDate = $components['return_date'] ?? null;
    $returnCondition = $components['return_condition'] ?? null;
    $returnNote = $components['return_note'] ?? null;

    expect($returnDate)->toBeInstanceOf(DatePicker::class)
        ->and($returnCondition)->toBeInstanceOf(Select::class)
        ->and($returnNote)->toBeInstanceOf(Textarea::class);

    if (! $returnDate instanceof DatePicker || ! $returnCondition instanceof Select) {
        throw new LogicException('The receive container form fields are not configured correctly.');
    }

    expect(CarbonImmutable::parse($returnDate->getDefaultState())->isToday())->toBeTrue()
        ->and($returnDate->isNative())->toBeFalse()
        ->and($returnDate->shouldCloseOnDateSelection())->toBeTrue()
        ->and($returnDate->isRequired())->toBeTrue()
        ->and($returnCondition->getDefaultState())->toBe(ReturnCondition::Good)
        ->and($returnCondition->isNative())->toBeFalse()
        ->and($returnCondition->isRequired())->toBeTrue();
});

it('uses the configured container label in the receive modal heading', function (): void {
    config()->set('storix.labels.container', 'gas cylinder');

    /** @var HasTable&MockInterface $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = DispatchEntryResource::table(Table::make($livewire));
    $receiveAction = array_find($table->getRecordActions(), fn ($action): bool => $action instanceof EditAction && $action->getName() === 'edit');

    expect($receiveAction)->toBeInstanceOf(EditAction::class);

    if (! $receiveAction instanceof EditAction) {
        throw new LogicException('The receive container action is not configured.');
    }

    expect($receiveAction->getModalHeading())->toBe('Receive Gas Cylinder');
});
