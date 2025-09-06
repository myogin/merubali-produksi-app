# Working with Cline - Documentation Usage Guide

This guide explains how to effectively use the project documentation when starting new tasks with Cline (AI assistant).

## 🚀 Quick Start for New Tasks

### 1. Always Start by Referencing Documentation

When beginning any new task with Cline, **always** start your conversation by pointing to the relevant documentation:

```
"Before we start, please read the documentation in laravel/docs/ to understand the project structure.
Specifically, check laravel/docs/README.md for the project overview and
laravel/docs/GUIDELINES.md for documentation standards."
```

### 2. Provide Context from Documentation

Give Cline specific documentation to read based on your task:

**For Database Changes:**

```
"Please read laravel/docs/architecture/database-schema.md to understand the current database structure before making any changes."
```

**For New Features:**

```
"Review laravel/docs/architecture/system-overview.md to understand the business logic and data flow."
```

**For API Work:**

```
"Check laravel/docs/api/ directory for existing API documentation patterns."
```

## 📋 Task-Specific Documentation Usage

### Database Modifications

1. **Read First**: `laravel/docs/architecture/database-schema.md`
2. **Understand**: Current table relationships and constraints
3. **Follow**: Migration patterns from existing migrations
4. **Update**: Documentation after changes

### New Feature Development

1. **Read First**: `laravel/docs/architecture/system-overview.md`
2. **Understand**: Business rules and data flow
3. **Check**: `laravel/docs/features/` for similar features
4. **Document**: Create new feature documentation

### Bug Fixes

1. **Read First**: `laravel/docs/updates/migration-guides/` for recent changes
2. **Check**: `laravel/docs/updates/CHANGELOG.md` for version history
3. **Understand**: System architecture from `laravel/docs/architecture/`

### API Development

1. **Read First**: `laravel/docs/api/` directory
2. **Follow**: API documentation templates from `laravel/docs/GUIDELINES.md`
3. **Update**: API documentation after changes

## 🎯 Best Practices for Cline Interactions

### Starting a New Task

**❌ Don't do this:**

```
"Add a new field to the products table"
```

**✅ Do this instead:**

```
"I need to add a new field to the products table. Please first read
laravel/docs/architecture/database-schema.md to understand the current
structure, then create a migration following the existing patterns."
```

### Requesting File Creation

**❌ Don't do this:**

```
"Create documentation for the new feature"
```

**✅ Do this instead:**

```
"Create documentation for the new feature at
laravel/docs/features/new-feature-name.md following the template
in laravel/docs/GUIDELINES.md"
```

### Complex Tasks

**❌ Don't do this:**

```
"Modify the shipment system"
```

**✅ Do this instead:**

```
"I need to modify the shipment system. Please read:
1. laravel/docs/architecture/system-overview.md for business logic
2. laravel/docs/architecture/database-schema.md for data structure
3. laravel/docs/features/shipment-system.md (if exists) for current implementation
Then propose the changes."
```

## 📁 Documentation Structure Reference

When directing Cline to documentation, use these paths:

```
laravel/docs/
├── README.md                    # Project overview - START HERE
├── GUIDELINES.md               # Documentation standards - IMPORTANT FOR FILE CREATION
├── architecture/
│   ├── database-schema.md      # Database structure - FOR DB WORK
│   └── system-overview.md      # Business logic - FOR FEATURE WORK
├── features/                   # Feature docs - FOR UNDERSTANDING FEATURES
├── api/                       # API docs - FOR API WORK
├── development/               # Dev guides - FOR SETUP/STANDARDS
├── deployment/                # Deploy guides - FOR DEPLOYMENT
└── updates/
    ├── CHANGELOG.md           # Version history - FOR CONTEXT
    └── migration-guides/      # Update guides - FOR RECENT CHANGES
```

## 🔄 Workflow Examples

### Example 1: Adding a New Feature

```
"I want to add a quality control feature to track product quality scores.

Please start by reading:
1. laravel/docs/README.md - for project overview
2. laravel/docs/architecture/database-schema.md - for database structure
3. laravel/docs/architecture/system-overview.md - for business logic

Then propose:
1. Database changes needed
2. Model relationships
3. Where to place the new feature documentation (following laravel/docs/GUIDELINES.md)

Create all files in the appropriate locations as specified in the guidelines."
```

### Example 2: Fixing a Bug

```
"There's an issue with stock calculations in the production system.

Please read:
1. laravel/docs/architecture/system-overview.md - to understand the stock flow
2. laravel/docs/updates/migration-guides/stock-movement-update.md - for recent changes
3. laravel/docs/architecture/database-schema.md - for stock_movements table structure

Then investigate the issue and propose a fix."
```

### Example 3: API Development

```
"I need to create a REST API for mobile app integration.

Please read:
1. laravel/docs/README.md - for project overview
2. laravel/docs/GUIDELINES.md - for API documentation templates
3. laravel/docs/architecture/database-schema.md - for data structure

Then create:
1. API endpoints following Laravel best practices
2. API documentation at laravel/docs/api/mobile-endpoints.md
3. Update the main README with API information"
```

## 📝 Documentation Maintenance

### After Completing Tasks

Always ask Cline to:

1. **Update relevant documentation**:

    ```
    "Please update laravel/docs/architecture/database-schema.md to reflect
    the new table structure"
    ```

2. **Create new documentation**:

    ```
    "Create feature documentation at laravel/docs/features/quality-control.md
    using the template from laravel/docs/GUIDELINES.md"
    ```

3. **Update changelog**:
    ```
    "Add this change to laravel/docs/updates/CHANGELOG.md under [Unreleased]"
    ```

## 🎯 Key Reminders

### For You (The User)

-   **Always reference documentation** at the start of tasks
-   **Be specific** about which docs to read
-   **Provide context** about what you're trying to achieve
-   **Ask for documentation updates** after changes

### For Cline (Include in Your Requests)

-   **Read documentation first** before making changes
-   **Follow the guidelines** in `laravel/docs/GUIDELINES.md`
-   **Use proper file paths** as specified in guidelines
-   **Update documentation** after making changes
-   **Ask for clarification** if documentation placement is unclear

## 🔗 Quick Reference Commands

Copy and paste these into your Cline conversations:

**Project Overview:**

```
Please read laravel/docs/README.md for project overview
```

**Database Work:**

```
Please read laravel/docs/architecture/database-schema.md for database structure
```

**New Features:**

```
Please read laravel/docs/architecture/system-overview.md for business logic
```

**File Creation:**

```
Follow the guidelines in laravel/docs/GUIDELINES.md for file placement and naming
```

**Documentation Update:**

```
Update the relevant documentation in laravel/docs/ after making changes
```

---

**Remember**: Good documentation usage leads to better, more consistent results from Cline!

**Last Updated**: January 2025  
**Related**: [Documentation Guidelines](../GUIDELINES.md), [Project Overview](../README.md)
