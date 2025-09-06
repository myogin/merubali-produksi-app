# Changelog

All notable changes to the Production & Stock Management System will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

-   Documentation organization structure
-   Comprehensive documentation guidelines
-   Database schema documentation
-   Migration guides for system updates

### Changed

-   Reorganized all markdown files into structured directories
-   Improved documentation navigation and cross-references

## [1.0.0] - 2025-01-09

### Added

-   Initial Laravel implementation replacing Google Sheets system
-   Production batch management with header-detail pattern
-   Stock movement tracking system (single source of truth)
-   Receipt management for packaging materials
-   Shipment management for finished goods
-   Bill of Materials (BOM) system
-   Filament admin panel interface
-   Docker containerization support
-   User authentication and authorization
-   Activity logging for audit trails

### Features

-   **Production Management**

    -   Production batch creation with PO numbers
    -   Individual batch item tracking with batch codes
    -   Material consumption based on BOM calculations
    -   Stock validation before production

-   **Inventory Management**

    -   Real-time stock tracking for packaging and finished goods
    -   Batch-level inventory for finished goods
    -   Automatic stock movements for all transactions
    -   Stock validation for shipments

-   **Receipt System**

    -   Packaging material receipt recording
    -   Supplier information tracking
    -   Automatic stock increase for received items

-   **Shipment System**
    -   Batch-specific shipment creation
    -   Stock validation before shipment
    -   Automatic stock reduction for shipped items
    -   Destination management

### Database Schema

-   `products` - Finished goods master data
-   `packaging_items` - Packaging materials master data
-   `boms` - Bill of materials definitions
-   `receipts` + `receipt_items` - Receipt transactions
-   `production_batches` + `production_batch_items` - Production transactions
-   `shipments` + `shipment_items` - Shipment transactions
-   `stock_movements` - Complete audit trail of all inventory movements
-   `destinations` - Shipment destinations
-   `users` - System users with role-based access

### Technical Implementation

-   Laravel 11.x framework
-   MySQL database
-   Filament 3.x admin panel
-   Docker containerization
-   Spatie Laravel Permission for role management
-   Spatie Laravel Activity Log for audit trails

## Migration from Google Sheets

### Background

The system was migrated from a Google Sheets + Apps Script solution to provide:

-   Better data integrity and validation
-   Improved performance and scalability
-   Enhanced user interface and experience
-   Comprehensive audit trails
-   Role-based access control
-   Automated calculations and validations

### Key Improvements

1. **Data Integrity**: Foreign key constraints and validation rules
2. **Performance**: Database indexing and optimized queries
3. **User Experience**: Modern web interface with Filament
4. **Audit Trail**: Complete tracking of all changes
5. **Scalability**: Designed to handle growing business needs
6. **Security**: User authentication and role-based permissions

### Migration Process

1. Data export from Google Sheets
2. Database schema creation
3. Data transformation and import
4. User training on new system
5. Parallel running period for validation
6. Full cutover to new system

---

## Version History Summary

| Version | Date       | Description                    |
| ------- | ---------- | ------------------------------ |
| 1.0.0   | 2025-01-09 | Initial Laravel implementation |

---

## Upgrade Guides

For detailed upgrade instructions between versions, see the [Migration Guides](migration-guides/) directory:

-   [Stock Movement Update](migration-guides/stock-movement-update.md) - ProductionBatchItem structure changes

---

## Contributing

When making changes to the system:

1. **Document Changes**: Update this changelog with all notable changes
2. **Version Bumping**: Follow semantic versioning guidelines
3. **Migration Guides**: Create migration guides for breaking changes
4. **Testing**: Ensure all changes are thoroughly tested
5. **Documentation**: Update relevant documentation files

### Change Categories

-   **Added** for new features
-   **Changed** for changes in existing functionality
-   **Deprecated** for soon-to-be removed features
-   **Removed** for now removed features
-   **Fixed** for any bug fixes
-   **Security** for vulnerability fixes

---

**Maintained by**: Development Team  
**Last Updated**: January 2025
