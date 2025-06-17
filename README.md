# Rapid Database Operations

A PHP library for high-performance database operations with Doctrine ORM support.

## Installation

```bash
composer require shredio/rapid-database-operations
```

## Requirements

- PHP 8.3 or higher
- Doctrine ORM 3.0+ (optional, for Doctrine integration)
- Doctrine DBAL 4.0+ (optional, for Doctrine integration)

## Features

- **High-performance database operations** - Optimized bulk insert and update operations
- **Doctrine ORM integration** - Seamless integration with Doctrine entities
- **Multiple database platforms** - Support for MySQL and SQLite
- **Type-safe operations** - Generic templates for type safety
- **Symfony Bundle** - Easy integration with Symfony applications
- **Flexible escaping** - Customizable value escaping strategies

## Basic Usage

### Using RapidOperationFactory

The `RapidOperationFactory` is the main entry point for creating database operations:

```php
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidOperationFactory;

$factory = new DoctrineRapidOperationFactory($entityManager);
```

### Insert Operations

```php
// Basic insert - fails on duplicate keys
$inserter = $factory->createInsert(Article::class);
$inserter->addRaw([
    'id' => 1,
    'title' => 'Article Title',
    'content' => 'Article content...'
]);
$inserter->execute();

// Unique insert - silently ignores duplicates
$uniqueInserter = $factory->createUniqueInsert(Article::class);
$uniqueInserter->addRaw([
    'id' => 1,
    'title' => 'This will be ignored if ID 1 exists'
]);
$uniqueInserter->execute();

// Upsert - insert or update on conflict
$upserter = $factory->createUpsert(Article::class, ['title', 'content']);
$upserter->addRaw([
    'id' => 1,
    'title' => 'Updated Title',
    'content' => 'Updated content...'
]);
$upserter->execute();
```

### Update Operations

```php
// Standard update for smaller datasets
$updater = $factory->createUpdate(Article::class, ['id']);
$updater->addRaw([
    'id' => 1,
    'title' => 'Updated Title',
    'updated_at' => new DateTime()
]);
$updater->execute();

// Big update for large datasets - uses temporary tables
$bigUpdater = $factory->createBigUpdate(Article::class, ['id']);
// Add thousands of records...
$bigUpdater->execute();
```

### Direct Usage (Alternative)

You can also create operations directly:

```php
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidInserter;
use Shredio\RapidDatabaseOperations\Doctrine\DoctrineRapidUpdater;

// Direct inserter creation
$inserter = new DoctrineRapidInserter(Article::class, $entityManager);
$inserter->addRaw(['id' => 1, 'title' => 'Title']);
$inserter->execute();

// Direct updater creation
$updater = new DoctrineRapidUpdater(Article::class, $entityManager);
$updater->addRaw(['id' => 1, 'title' => 'Updated Title']);
$updater->execute();
```

## Operation Types

### createInsert(string $entity)
Creates a basic insert operation that will fail if duplicate keys are encountered.

### createUniqueInsert(string $entity)
Creates an insert operation that silently ignores duplicate records using `INSERT ... ON DUPLICATE KEY ... DO NOTHING`.

### createUpsert(string $entity, array $fieldsToUpdate = [])
Creates an upsert operation that inserts new records or updates existing ones on conflict. You can specify which fields to update, or leave empty to update all fields.

### createUpdate(string $entity, array $conditions)
Creates a standard update operation using direct `UPDATE` statements. Best for smaller datasets.

### createBigUpdate(string $entity, array $conditions)
Creates an optimized update operation that uses temporary tables for better performance with large datasets.

### Symfony Integration

The library includes a Symfony bundle for easy integration:

```php
// config/bundles.php
return [
    // ...
    Shredio\RapidDatabaseOperations\Symfony\RapidDatabaseOperationsBundle::class => ['all' => true],
];
```

## Development

### Running Tests

```bash
composer test
```

### Static Analysis

```bash
composer phpstan
```
