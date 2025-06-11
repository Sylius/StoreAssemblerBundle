# Sylius Store Assembler

Sylius Store Assembler is a Symfony Bundle that scaffolds and deploys Sylius-based stores from JSON/YAML presets.

## Requirements

- PHP 8.2+
- Symfony 6.0 or 7.0
- Composer 2
- Node.js & Yarn (for asset building via Webpack Encore)

## Installation

```bash
composer require sylius/store-assembler
```

## Usage

Add the bundle to your `config/bundles.php` if not auto-registered:

```php
Sylius\StoreAssemblerBundle\SyliusStoreAssemblerBundle::class => ['all' => true],
```

Ensure you have a store preset in `store-preset/store-preset.json`, for example:

```json
{
   "name": "my_store"
}
```

Then run:

```bash
make store-assembler
```

For more granular control, use the underlying console commands:

```bash
php bin/console sylius:store-assembler:plugin:prepare
php bin/console sylius:store-assembler:plugin:install
php bin/console sylius:store-assembler:fixture:prepare
php bin/console sylius:store-assembler:fixture:load
php bin/console sylius:store-assembler:theme:prepare
```

## Configuration

See `config/plugins` for sample plugin manifests and `config/services.php` for DI configuration.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.