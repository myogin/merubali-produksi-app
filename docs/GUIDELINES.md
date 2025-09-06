# Documentation Guidelines

This document outlines the standards and best practices for creating and organizing documentation in this Laravel project.

## 📁 Directory Structure

```
laravel/docs/
├── README.md                       # Main documentation index
├── GUIDELINES.md                   # This file - documentation standards
├── api/                           # API documentation
│   ├── endpoints.md               # API endpoints reference
│   └── authentication.md          # Authentication guide
├── architecture/                  # System architecture docs
│   ├── database-schema.md         # Database design and ERD
│   ├── business-logic.md          # Business rules and logic
│   └── data-flow.md              # Data flow diagrams
├── development/                   # Development guides
│   ├── setup.md                  # Local development setup
│   ├── coding-standards.md       # Code style and standards
│   └── testing.md                # Testing guidelines
├── features/                      # Feature documentation
│   ├── production-management.md   # Production system docs
│   ├── stock-tracking.md         # Stock management docs
│   └── shipment-system.md        # Shipment system docs
├── updates/                       # Change logs and updates
│   ├── CHANGELOG.md              # Project changelog
│   └── migration-guides/         # Migration and update guides
│       └── *.md                  # Specific migration docs
└── deployment/                    # Deployment guides
    ├── docker.md                 # Docker setup and usage
    └── production-setup.md       # Production deployment
```

## 📝 File Naming Conventions

### ✅ Good Examples

-   `database-schema.md`
-   `stock-tracking.md`
-   `api-authentication.md`
-   `production-setup.md`

### ❌ Avoid

-   `Database Schema.md` (spaces)
-   `stockTracking.md` (camelCase)
-   `API_AUTH.md` (underscores, all caps)

### Rules

1. Use **kebab-case** (lowercase with hyphens)
2. Be descriptive but concise
3. Use `.md` extension for all markdown files
4. Avoid spaces, underscores, or special characters

## 📂 Where to Place Different Types of Documentation

| Documentation Type | Directory                   | Example                                  |
| ------------------ | --------------------------- | ---------------------------------------- |
| API Reference      | `api/`                      | `api/user-endpoints.md`                  |
| Database Design    | `architecture/`             | `architecture/database-schema.md`        |
| Business Logic     | `architecture/`             | `architecture/inventory-rules.md`        |
| Setup Instructions | `development/`              | `development/local-setup.md`             |
| Feature Specs      | `features/`                 | `features/production-batches.md`         |
| Migration Guides   | `updates/migration-guides/` | `updates/migration-guides/v2-upgrade.md` |
| Deployment         | `deployment/`               | `deployment/docker-compose.md`           |
| General Changes    | `updates/`                  | `updates/CHANGELOG.md`                   |

## 🎯 Instructions for AI Assistants (Cline)

When creating documentation files, **always**:

1. **Specify the full path** from project root:

    ```
    Create laravel/docs/features/new-feature.md
    ```

2. **Use the directory structure above** - don't create files in random locations

3. **Follow naming conventions** - use kebab-case

4. **Reference this guidelines file** when unsure about placement

5. **Ask for confirmation** if the placement isn't clear:
    ```
    Should I create this in laravel/docs/features/ or laravel/docs/architecture/?
    ```

## 📋 Documentation Templates

### Feature Documentation Template

```markdown
# Feature Name

## Overview

Brief description of the feature

## Business Requirements

-   Requirement 1
-   Requirement 2

## Technical Implementation

### Database Changes

### API Changes

### UI Changes

## Usage Examples

### Code Examples

### Screenshots (if applicable)

## Testing

### Test Cases

### Manual Testing Steps

## Related Documentation

-   [Related Doc 1](../path/to/doc.md)
-   [Related Doc 2](../path/to/doc.md)
```

### API Documentation Template

````markdown
# API Endpoint Name

## Endpoint

`POST /api/endpoint-name`

## Description

What this endpoint does

## Parameters

| Parameter | Type   | Required | Description |
| --------- | ------ | -------- | ----------- |
| param1    | string | Yes      | Description |

## Request Example

```json
{
    "example": "request"
}
```
````

## Response Example

```json
{
    "example": "response"
}
```

## Error Codes

| Code | Description |
| ---- | ----------- |
| 400  | Bad Request |

```

## 🔗 Cross-Referencing

- Use relative paths for internal links: `[Link Text](../folder/file.md)`
- Always test links after creating them
- Update related documents when making changes
- Maintain a consistent linking style

## ✅ Quality Standards

### Content Requirements
- Clear, concise writing
- Proper grammar and spelling
- Up-to-date information
- Code examples that work
- Screenshots when helpful

### Structure Requirements
- Use proper markdown headers (H1, H2, H3)
- Include table of contents for long documents
- Use consistent formatting
- Add metadata (author, date, version) when relevant

### Maintenance
- Review documentation quarterly
- Update when code changes
- Remove outdated information
- Keep examples current

## 🚀 Getting Started

1. **Before creating new docs**: Check if similar documentation exists
2. **Choose the right location**: Use the directory structure above
3. **Use templates**: Start with the appropriate template
4. **Follow conventions**: Use proper naming and formatting
5. **Cross-reference**: Link to related documentation
6. **Review**: Check for accuracy and completeness

## 📞 Questions?

If you're unsure about:
- Where to place documentation
- How to structure content
- Naming conventions

Refer to this guide or ask for clarification before creating files.

---

**Last Updated**: January 2025
**Version**: 1.0
```
