# Sistem Manajemen Produksi & Stok - Documentation

**Perusahaan**: CV. Merubali Natural  
**Versi**: 1.0 (Laravel Implementation)  
**Penyusun**: Yogi

---

## 📌 Overview

This Laravel application replaces the previous Google Sheets + Apps Script system with a robust **Laravel + MySQL** solution for tracking **packaging stock** and **finished goods per batch**.

### System Flow

1. **Receipt Forms** → Add packaging stock
2. **Production Forms** → Consume packaging per BOM, add finished goods per batch
3. **Shipment Forms** → Reduce finished goods per batch
4. All movements recorded in **Transaction Log (Ledger)** for audit

---

## 📚 Documentation Index

### 🏗️ Architecture & Design

-   [Database Schema](architecture/database-schema.md) - ERD and table structures
-   [Business Logic](architecture/business-logic.md) - Core business rules
-   [Data Flow](architecture/data-flow.md) - System data flow diagrams

### 🚀 Features

-   [Production Management](features/production-management.md) - Production batch system
-   [Stock Tracking](features/stock-tracking.md) - Inventory management
-   [Shipment System](features/shipment-system.md) - Order fulfillment

### 🔧 Development

-   [Local Setup](development/setup.md) - Development environment setup
-   [Coding Standards](development/coding-standards.md) - Code style guidelines
-   [Testing](development/testing.md) - Testing procedures

### 📡 API Reference

-   [Endpoints](api/endpoints.md) - API endpoints documentation
-   [Authentication](api/authentication.md) - API authentication guide

### 🚢 Deployment

-   [Docker Setup](deployment/docker.md) - Docker configuration
-   [Production Setup](deployment/production-setup.md) - Production deployment

### 📝 Updates & Changes

-   [Changelog](updates/CHANGELOG.md) - Version history
-   [Migration Guides](updates/migration-guides/) - Update procedures
    -   [Stock Movement Update](updates/migration-guides/stock-movement-update.md) - ProductionBatchItem migration

---

## 🗃️ Database Structure (Quick Reference)

### Master Data

-   **products** - Finished goods (CCO-CTN50, CCO-CTN24)
-   **packaging_items** - Packaging materials (STP-CCO-50, CTN50, CTN24)
-   **boms** - Bill of Materials (packaging requirements per product)

### Transactions

-   **receipts** + **receipt_items** - Packaging receipt records
-   **production_batches** + **production_batch_items** - Production batch records
-   **shipments** + **shipment_items** - Shipment records

### Audit Trail

-   **stock_movements** - All stock movements (single source of truth)

---

## 📊 Sample Data

### Products

| product_code | name       |
| ------------ | ---------- |
| CCO-CTN50    | STP-CCO-50 |
| CCO-CTN24    | STP-CCO-24 |

### Packaging Items

| packaging_code | name               | base_uom |
| -------------- | ------------------ | -------- |
| STP-CCO-50     | Standing Pouch 50g | pcs      |
| CTN50          | Karton Box 50 pcs  | pcs      |
| CTN24          | Karton Box 24 pcs  | pcs      |

### BOM Example

| product_code | packaging_code | qty_per_unit |
| ------------ | -------------- | ------------ |
| CCO-CTN50    | STP-CCO-50     | 50           |
| CCO-CTN50    | CTN50          | 1            |

---

## 🔍 Key Queries

### Current Packaging Stock

```sql
SELECT packaging_item_id, SUM(qty) AS stock_pcs
FROM stock_movements
WHERE item_type='packaging'
GROUP BY packaging_item_id;
```

### Current Finished Goods Stock

```sql
SELECT item_id, batch_id, SUM(qty) AS stock_pcs
FROM stock_movements
WHERE item_type='finished_goods'
GROUP BY item_id, batch_id;
```

---

## 🛠️ Quick Start

1. **Setup**: Follow [Development Setup](development/setup.md)
2. **Architecture**: Review [Database Schema](architecture/database-schema.md)
3. **Features**: Explore [Production Management](features/production-management.md)
4. **API**: Check [API Endpoints](api/endpoints.md)

---

## 📋 Documentation Guidelines

Before creating or modifying documentation, please read our [Documentation Guidelines](GUIDELINES.md) for:

-   File naming conventions
-   Directory structure
-   Content templates
-   Best practices for AI assistants

---

## 🔗 External Resources

-   [Laravel Documentation](https://laravel.com/docs)
-   [Filament Documentation](https://filamentphp.com/docs)
-   [MySQL Documentation](https://dev.mysql.com/doc/)

---

**Last Updated**: January 2025  
**Maintained by**: Development Team
