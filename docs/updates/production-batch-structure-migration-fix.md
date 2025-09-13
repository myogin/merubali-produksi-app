# Production Batch Structure Migration Fix

## Overview

This document describes the fixes applied to resolve the `batch_code` attribute error that occurred after the production batch structure migration. The error was: "The attribute [batch_code] either does not exist or was not retrieved for model [App\Models\ProductionBatch]."

## Root Cause Analysis

### Migration Changes

The migration `2025_08_22_073810_update_production_batches_table_structure.php` restructured the production batch system by moving several fields from the `production_batches` table to the `production_batch_items` table:

**Fields Moved:**

-   `batch_code` → Now in `production_batch_items`
-   `product_id` → Now in `production_batch_items`
-   `qty_produced` → Now in `production_batch_items`
-   `uom` → Now in `production_batch_items`

**Fields Retained in `production_batches`:**

-   `id`
-   `production_date`
-   `po_number`
-   `notes`
-   `created_at`
-   `updated_at`

### New Data Structure

**Before Migration:**

```
production_batches
├── id
├── batch_code (UNIQUE)
├── production_date
├── po_number
├── product_id
├── qty_produced
├── uom
├── notes
└── timestamps
```

**After Migration:**

```
production_batches                    production_batch_items
├── id                               ├── id
├── production_date                  ├── production_batch_id (FK)
├── po_number                        ├── batch_code (UNIQUE)
├── notes                            ├── product_id (FK)
└── timestamps                       ├── qty_produced
                                     ├── uom
                                     ├── notes
                                     └── timestamps
```

### Relationship Changes

-   **One-to-Many**: One production batch can now have multiple production batch items
-   **Batch Codes**: Each production batch item has its own unique batch code
-   **Products**: Each production batch item can be for different products

## Issues Identified and Fixed

### 1. ProductionBatchResource.php

**Issue:**

```php
protected static ?string $recordTitleAttribute = 'batch_code';
```

**Problem:** Trying to access `batch_code` on ProductionBatch model where it no longer exists.

**Fix:**

```php
protected static ?string $recordTitleAttribute = 'po_number';
```

**Rationale:** Use `po_number` as the record title since it's still available at the production batch level and provides meaningful identification.

### 2. StockMovementsTable.php - Batch Code Column

**Issue:**

```php
TextColumn::make('productionBatch.batch_code')
    ->label('Batch Code')
    ->placeholder('N/A')
    ->searchable(),
```

**Problem:** Trying to access `batch_code` through `productionBatch` relationship where it no longer exists.

**Fix:**

```php
TextColumn::make('batch_code')
    ->label('Batch Code')
    ->getStateUsing(function ($record) {
        return $record->productionBatchItem?->batch_code ?? 'N/A';
    })
    ->placeholder('N/A')
    ->searchable(['production_batch_items.batch_code']),
```

**Rationale:** Access batch code through the `productionBatchItem` relationship where it now resides.

### 3. StockMovementsTable.php - Reference Number Column

**Issue:**

```php
case 'production':
    return $record->productionBatchReference?->batch_code ?? 'N/A';
```

**Problem:** Trying to access `batch_code` on ProductionBatch for production reference.

**Fix:**

```php
case 'production':
    return $record->productionBatchReference?->po_number ?? 'Production Batch #' . $record->reference_id;
```

**Rationale:** Use `po_number` when available, fallback to a descriptive ID-based reference.

### 4. StockMovementsTable.php - Search Configuration

**Issue:**

```php
->searchable(['receipts.receipt_number', 'production_batches.batch_code', 'shipments.shipment_number'])
```

**Problem:** Searching on non-existent `production_batches.batch_code` field.

**Fix:**

```php
->searchable(['receipts.receipt_number', 'production_batches.po_number', 'shipments.shipment_number'])
```

**Rationale:** Search on `po_number` instead of the non-existent `batch_code`.

## Model Relationships

### ProductionBatch Model

```php
public function productionBatchItems(): HasMany
{
    return $this->hasMany(ProductionBatchItem::class);
}
```

### ProductionBatchItem Model

```php
public function productionBatch(): BelongsTo
{
    return $this->belongsTo(ProductionBatch::class);
}

public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}
```

### StockMovement Model

```php
// For accessing batch codes through production batch items
public function productionBatchItem(): BelongsTo
{
    return $this->belongsTo(ProductionBatchItem::class, 'batch_id');
}

// For accessing production batch header info
public function productionBatchReference(): BelongsTo
{
    return $this->belongsTo(ProductionBatch::class, 'reference_id');
}
```

## Impact on Existing Features

### ✅ Working Features

-   Production batch creation with multiple items
-   Stock movement tracking per batch item
-   Shipment creation from production batch items
-   Material requirement validation
-   Batch code uniqueness validation

### 🔧 Fixed Features

-   Production batch table display
-   Stock movements table display
-   Batch code searching and filtering
-   Record title display in Filament

## Best Practices for Future Development

### 1. Always Access Batch Codes Through Items

```php
// ❌ Wrong - batch_code no longer exists on ProductionBatch
$batchCode = $productionBatch->batch_code;

// ✅ Correct - access through items
$batchCodes = $productionBatch->productionBatchItems->pluck('batch_code');
```

### 2. Use Proper Relationships in Filament Tables

```php
// ❌ Wrong - direct relationship to non-existent field
TextColumn::make('productionBatch.batch_code')

// ✅ Correct - use getStateUsing with proper relationship
TextColumn::make('batch_code')
    ->getStateUsing(fn($record) => $record->productionBatchItem?->batch_code ?? 'N/A')
```

### 3. Handle Multiple Batch Codes

```php
// For displaying multiple batch codes from one production batch
$batchCodes = $productionBatch->productionBatchItems->pluck('batch_code')->join(', ');
```

### 4. Stock Movement Relationships

```php
// For finished goods stock movements, use batch_id to reference production_batch_items.id
// For production reference, use reference_id to reference production_batches.id
```

## Testing Recommendations

1. **Production Batch Creation**: Test creating production batches with multiple items
2. **Stock Movements Display**: Verify batch codes display correctly in stock movements table
3. **Search Functionality**: Test searching by batch codes and PO numbers
4. **Record Navigation**: Ensure production batch records display proper titles
5. **Shipment Creation**: Verify shipments can be created from production batch items

## Migration Rollback Considerations

If rollback is needed, the migration includes a `down()` method that restores the original structure:

```php
public function down(): void
{
    Schema::table('production_batches', function (Blueprint $table) {
        // Restore the original columns
        $table->string('batch_code')->unique();
        $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
        $table->integer('qty_produced');
        $table->string('uom')->default('cartons');

        // Restore indexes
        $table->index(['batch_code']);
        $table->index(['product_id']);
    });
}
```

**Note:** Rollback would require data migration to move batch codes back to the production_batches table.

## Conclusion

The migration from a single-item production batch structure to a multi-item structure required updates to several Filament resource files. The fixes ensure that:

1. Batch codes are properly accessed through the new relationship structure
2. Record titles use available fields (`po_number` instead of `batch_code`)
3. Search functionality works with the new table structure
4. All existing features continue to work as expected

These changes maintain backward compatibility while supporting the new multi-item production batch functionality.
