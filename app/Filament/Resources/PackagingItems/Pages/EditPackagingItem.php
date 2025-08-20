<?php

namespace App\Filament\Resources\PackagingItems\Pages;

use App\Filament\Resources\PackagingItems\PackagingItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPackagingItem extends EditRecord
{
    protected static string $resource = PackagingItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
