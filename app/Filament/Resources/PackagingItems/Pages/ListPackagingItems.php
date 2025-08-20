<?php

namespace App\Filament\Resources\PackagingItems\Pages;

use App\Filament\Resources\PackagingItems\PackagingItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPackagingItems extends ListRecords
{
    protected static string $resource = PackagingItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
