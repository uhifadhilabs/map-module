# Development

```bash
composer install
composer check   # cs + phpstan (max) + phpunit
```

No database: this bundle owns no entities, so there is no test-database URL and no postgres service
in CI. A map is machinery; what it draws belongs to the modules that own the records.
