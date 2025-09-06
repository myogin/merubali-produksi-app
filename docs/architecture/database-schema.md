# Database Schema

This document describes the database structure for the Production & Stock Management System.

## Overview

The system uses a **header-detail pattern** for transactions and maintains a **single source of truth** through the `stock_movements` table for all inventory tracking.

## Entity Relationship Diagram

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│    products     │    │ packaging_items │    │      boms       │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ id (PK)         │    │ id (PK)         │    │ id (PK)         │
│ product_code    │◄───┤ packaging_code  │◄───┤ product_id (FK) │
│ name            │    │ name            │    │ packaging_id(FK)│
│ created_at      │    │ base_uom        │    │ qty_per_unit    │
│ updated_at      │    │ created_at      │    │ uom             │
└─────────────────┘    │ updated_at      │    │ created_at      │
                       └─────────────────┘    │ updated_at      │
                                              └─────────────────┘

┌─────────────────┐    ┌─────────────────┐
│    receipts     │    │  receipt_items  │
├─────────────────┤    ├─────────────────┤
│ id (PK)         │◄───┤ id (PK)         │
│ receipt_date    │    │ receipt_id (FK) │
│ supplier        │    │ packaging_id(FK)│
│ notes           │    │ qty_received    │
│ created_at      │    │ unit_price      │
│ updated_at      │    │ created_at      │
└─────────────────┘    │ updated_at      │
                       └─────────────────┘

┌─────────────────┐    ┌─────────────────┐
│production_batches│   │production_batch │
├─────────────────┤    │     _items      │
│ id (PK)         │◄───┤─────────────────┤
│ po_number       │    │ id (PK)         │
│ production_date │    │ batch_id (FK)   │
│ notes           │    │ product_id (FK) │
│ created_at      │    │ batch_code      │
│ updated_at      │    │ qty_produced    │
└─────────────────┘    │ created_at      │
                       │ updated_at      │
                       └─────────────────┘

┌─────────────────┐    ┌─────────────────┐
│   shipments     │    │ shipment_items  │
├─────────────────┤    ├─────────────────┤
│ id (PK)         │◄───┤ id (PK)         │
│ shipment_number │    │ shipment_id (FK)│
│ shipment_date   │    │ prod_batch_item │
│ destination_id  │    │     _id (FK)    │
│ notes           │    │ qty_shipped     │
│ created_at      │    │ created_at      │
│ updated_at      │    │ updated_at      │
└─────────────────┘    └─────────────────┘

┌─────────────────┐
│ stock_movements │ ◄── Single Source of Truth
├─────────────────┤
│ id (PK)         │
│ item_type       │ ◄── 'packaging' | 'finished_goods'
│ item_id (FK)    │ ◄── products.id | packaging_items.id
│ batch_id (FK)   │ ◄── production_batch_items.id (nullable)
│ movement_type   │ ◄── 'in' | 'out'
│ qty             │
│ reference_type  │ ◄── 'receipt' | 'production' | 'shipment'
│ reference_id    │
│ notes           │
│ created_at      │
│ updated_at      │
└─────────────────┘
```

## Table Definitions

### Master Data Tables

#### products

Finished goods that can be produced.

| Column       | Type         | Constraints        | Description                    |
| ------------ | ------------ | ------------------ | ------------------------------ |
| id           | bigint       | PK, AUTO_INCREMENT | Primary key                    |
| product_code | varchar(50)  | UNIQUE, NOT NULL   | Product code (e.g., CCO-CTN50) |
| name         | varchar(255) | NOT NULL           | Product name                   |
| created_at   | timestamp    |                    | Creation timestamp             |
| updated_at   | timestamp    |                    | Last update timestamp          |

#### packaging_items

Raw materials/packaging used in production.

| Column         | Type         | Constraints        | Description                       |
| -------------- | ------------ | ------------------ | --------------------------------- |
| id             | bigint       | PK, AUTO_INCREMENT | Primary key                       |
| packaging_code | varchar(50)  | UNIQUE, NOT NULL   | Packaging code (e.g., STP-CCO-50) |
| name           | varchar(255) | NOT NULL           | Packaging name                    |
| base_uom       | varchar(10)  | NOT NULL           | Base unit of measure (pcs)        |
| created_at     | timestamp    |                    | Creation timestamp                |
| updated_at     | timestamp    |                    | Last update timestamp             |

#### boms (Bill of Materials)

Defines packaging requirements for each product.

| Column            | Type          | Constraints             | Description                      |
| ----------------- | ------------- | ----------------------- | -------------------------------- |
| id                | bigint        | PK, AUTO_INCREMENT      | Primary key                      |
| product_id        | bigint        | FK → products.id        | Product reference                |
| packaging_item_id | bigint        | FK → packaging_items.id | Packaging reference              |
| qty_per_unit      | decimal(10,2) | NOT NULL                | Quantity needed per product unit |
| uom               | varchar(10)   | NOT NULL                | Unit of measure                  |
| created_at        | timestamp     |                         | Creation timestamp               |
| updated_at        | timestamp     |                         | Last update timestamp            |

### Transaction Tables

#### receipts

Header table for packaging receipts.

| Column       | Type         | Constraints        | Description           |
| ------------ | ------------ | ------------------ | --------------------- |
| id           | bigint       | PK, AUTO_INCREMENT | Primary key           |
| receipt_date | date         | NOT NULL           | Receipt date          |
| supplier     | varchar(255) |                    | Supplier name         |
| notes        | text         |                    | Additional notes      |
| created_at   | timestamp    |                    | Creation timestamp    |
| updated_at   | timestamp    |                    | Last update timestamp |

#### receipt_items

Detail table for packaging receipt items.

| Column            | Type          | Constraints             | Description              |
| ----------------- | ------------- | ----------------------- | ------------------------ |
| id                | bigint        | PK, AUTO_INCREMENT      | Primary key              |
| receipt_id        | bigint        | FK → receipts.id        | Receipt header reference |
| packaging_item_id | bigint        | FK → packaging_items.id | Packaging reference      |
| qty_received      | decimal(10,2) | NOT NULL                | Quantity received        |
| unit_price        | decimal(10,2) |                         | Unit price (optional)    |
| created_at        | timestamp     |                         | Creation timestamp       |
| updated_at        | timestamp     |                         | Last update timestamp    |

#### production_batches

Header table for production batches.

| Column          | Type         | Constraints        | Description           |
| --------------- | ------------ | ------------------ | --------------------- |
| id              | bigint       | PK, AUTO_INCREMENT | Primary key           |
| po_number       | varchar(100) |                    | Purchase order number |
| production_date | date         | NOT NULL           | Production date       |
| notes           | text         |                    | Production notes      |
| created_at      | timestamp    |                    | Creation timestamp    |
| updated_at      | timestamp    |                    | Last update timestamp |

#### production_batch_items

Detail table for individual batch items.

| Column              | Type          | Constraints                | Description            |
| ------------------- | ------------- | -------------------------- | ---------------------- |
| id                  | bigint        | PK, AUTO_INCREMENT         | Primary key            |
| production_batch_id | bigint        | FK → production_batches.id | Batch header reference |
| product_id          | bigint        | FK → products.id           | Product reference      |
| batch_code          | varchar(100)  | NOT NULL                   | Batch code (MFD)       |
| qty_produced        | decimal(10,2) | NOT NULL                   | Quantity produced      |
| created_at          | timestamp     |                            | Creation timestamp     |
| updated_at          | timestamp     |                            | Last update timestamp  |

#### shipments

Header table for shipments.

| Column          | Type         | Constraints          | Description           |
| --------------- | ------------ | -------------------- | --------------------- |
| id              | bigint       | PK, AUTO_INCREMENT   | Primary key           |
| shipment_number | varchar(100) | UNIQUE, NOT NULL     | Shipment number       |
| shipment_date   | date         | NOT NULL             | Shipment date         |
| destination_id  | bigint       | FK → destinations.id | Destination reference |
| notes           | text         |                      | Shipment notes        |
| created_at      | timestamp    |                      | Creation timestamp    |
| updated_at      | timestamp    |                      | Last update timestamp |

#### shipment_items

Detail table for shipment items.

| Column                   | Type          | Constraints                    | Description               |
| ------------------------ | ------------- | ------------------------------ | ------------------------- |
| id                       | bigint        | PK, AUTO_INCREMENT             | Primary key               |
| shipment_id              | bigint        | FK → shipments.id              | Shipment header reference |
| production_batch_item_id | bigint        | FK → production_batch_items.id | Batch item reference      |
| qty_shipped              | decimal(10,2) | NOT NULL                       | Quantity shipped          |
| created_at               | timestamp     |                                | Creation timestamp        |
| updated_at               | timestamp     |                                | Last update timestamp     |

### Audit Trail Table

#### stock_movements

**Single source of truth** for all inventory movements.

| Column         | Type          | Constraints                         | Description                                    |
| -------------- | ------------- | ----------------------------------- | ---------------------------------------------- |
| id             | bigint        | PK, AUTO_INCREMENT                  | Primary key                                    |
| item_type      | enum          | 'packaging', 'finished_goods'       | Type of item                                   |
| item_id        | bigint        | NOT NULL                            | Reference to products.id or packaging_items.id |
| batch_id       | bigint        | NULLABLE                            | Reference to production_batch_items.id         |
| movement_type  | enum          | 'in', 'out'                         | Movement direction                             |
| qty            | decimal(10,2) | NOT NULL                            | Quantity (positive for in, negative for out)   |
| reference_type | enum          | 'receipt', 'production', 'shipment' | Source transaction type                        |
| reference_id   | bigint        | NOT NULL                            | Source transaction ID                          |
| notes          | text          |                                     | Movement notes                                 |
| created_at     | timestamp     |                                     | Creation timestamp                             |
| updated_at     | timestamp     |                                     | Last update timestamp                          |

## Key Relationships

1. **Products ↔ BOM ↔ Packaging Items**: Many-to-many relationship defining material requirements
2. **Production Batches → Production Batch Items**: One-to-many header-detail relationship
3. **Stock Movements**: References all other tables for complete audit trail
4. **Batch Tracking**: `batch_id` in stock_movements enables lot tracking for finished goods

## Indexes

### Primary Indexes

-   All tables have primary key indexes on `id`

### Foreign Key Indexes

-   All foreign key columns have indexes for performance

### Business Logic Indexes

```sql
-- Stock movement queries
CREATE INDEX idx_stock_movements_item ON stock_movements(item_type, item_id);
CREATE INDEX idx_stock_movements_batch ON stock_movements(batch_id);
CREATE INDEX idx_stock_movements_reference ON stock_movements(reference_type, reference_id);

-- Product lookup
CREATE INDEX idx_products_code ON products(product_code);
CREATE INDEX idx_packaging_code ON packaging_items(packaging_code);

-- Date-based queries
CREATE INDEX idx_receipts_date ON receipts(receipt_date);
CREATE INDEX idx_production_date ON production_batches(production_date);
CREATE INDEX idx_shipments_date ON shipments(shipment_date);
```

## Data Integrity Rules

### Constraints

1. **Unique Codes**: Product codes and packaging codes must be unique
2. **Positive Quantities**: All quantity fields must be positive
3. **Valid References**: All foreign keys must reference existing records
4. **Movement Consistency**: Stock movements must have valid item_type/item_id combinations

### Business Rules

1. **Stock Validation**: Cannot ship more than available stock
2. **BOM Validation**: Cannot produce without sufficient packaging materials
3. **Batch Tracking**: Finished goods movements must reference valid batch items
4. **Audit Trail**: All inventory changes must create stock movement records

---

**Related Documentation**:

-   [System Overview](system-overview.md)
-   [Business Logic](business-logic.md)
-   [Stock Tracking](../features/stock-tracking.md)
