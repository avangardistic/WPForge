# WPForge Architecture

## Overview

WPForge is designed as a modular WordPress plugin that exposes a secure REST API for AI agents to interact with WordPress installations.

## Directory Structure

```
wpforge/
├── wpforge.php              # Main plugin file
├── uninstall.php            # Cleanup on uninstall
├── src/                     # Source code
│   ├── Core/               # Core utilities (Config)
│   ├── API/                # REST API base classes
│   ├── Auth/               # Authentication & Authorization
│   ├── Security/           # Security validators
│   ├── Logging/            # Audit logging
│   ├── WordPress/          # WordPress core services
│   ├── Elementor/          # Elementor integration
│   ├── Media/              # Media handling
│   ├── Plugins/            # Plugin management
│   ├── Themes/             # Theme management
│   ├── Filesystem/         # File operations
│   ├── Database/           # Database inspection
│   ├── Backup/             # Backup service
│   └── Diagnostics/        # Site diagnostics
├── routes/                  # REST route definitions
└── config/                  # Configuration schemas
```

## Key Design Decisions

### 1. Plugin vs MU-Plugin

We chose a standard plugin architecture because:
- Easier installation and removal
- Can be managed through WordPress admin
- Suitable for temporary deployment
- Clear ownership and accountability

### 2. Namespace Organization

All classes use the `WPForge\` namespace prefix to avoid conflicts with other plugins.

### 3. Service-Based Architecture

Each domain (Elementor, Media, Plugins, etc.) has its own service class that encapsulates business logic. Routes are thin wrappers that handle HTTP concerns.

### 4. Centralized Configuration

Configuration is stored in WordPress options and accessed through the `Config` class, providing:
- Type-safe access
- Default values
- Runtime modification capability

### 5. Audit Logging

All mutating operations are logged to a dedicated database table with:
- Request ID correlation
- User identification
- Operation details
- Success/failure status

## Data Flow

```
AI Agent → HTTPS → WordPress REST API → WPForge Routes → Services → WordPress APIs
                                                              ↓
                                                        Audit Log
```

## Security Layers

1. **Authentication**: WordPress Application Passwords or Cookie auth
2. **Authorization**: WordPress capabilities checked per operation
3. **Input Validation**: All inputs sanitized and validated
4. **Path Traversal Prevention**: Filesystem operations bounded to WordPress root
5. **Rate Limiting**: Configurable rate limits on sensitive operations
