<?php

namespace JibayMcs\Tabbed\Traits;

use Filament\Tables\Table;
use JibayMcs\Tabbed\Actions\OpenInTabAction;

trait HasTabbedActions
{
    public static function configureTable(Table $table): void
    {
        parent::configureTable($table);

        $table->pushRecordActions([
            OpenInTabAction::make(),
        ]);
    }
}
