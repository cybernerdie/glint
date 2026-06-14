# Upgrading

This document lists breaking changes between major versions and how to handle them.

## Unreleased

No releases yet. Once `v1.0.0` ships, upgrade notes for each breaking change will be recorded here.

### Migrations

If you upgrade and new migrations ship, publish and run them:

```bash
php artisan vendor:publish --tag=glint-migrations
php artisan migrate
```
