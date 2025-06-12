# Sylius Store Assembler

Sylius Store Assembler is a Symfony bundle that streamlines creating and configuring Sylius-based e-commerce stores using store presets.

## Installation

Install the package via Composer:

```bash
composer require sylius/store-assembler
```

## Enable the Bundle

If your application does not auto-register bundles, add the following entry to `config/bundles.php`:

```php
<?php
return [
    // ...
    Sylius\StoreAssemblerBundle\SyliusStoreAssemblerBundle::class => ['all' => true],
];
```

## Store Preset Structure

Create a store preset directory with this structure:

```
store-preset/
├── fixtures/
│   ├── fixtures.yaml
│   └── images/
│       └── <your_image_files>
├── store-preset.json
└── themes/
    └── shop/
        ├── banner.png
        └── logo.png
```

You can find example presets in the [Sylius StorePreset repository](https://github.com/Sylius/StorePreset).

## Usage

Add the following targets to your `Makefile` for common tasks:

```makefile
store-assembler:
    vendor/bin/store-assembler

store-assembler-fixtures:
    bin/console sylius:store-assembler:fixture:prepare
    bin/console cache:clear
    bin/console cache:warmup
    bin/console sylius:store-assembler:fixture:load

store-assembler-theme:
    bin/console sylius:store-assembler:theme:prepare
    bin/console cache:clear
    bin/console cache:warmup
```

Run all steps with:

```bash
make store-assembler
```

For granular control, use individual console commands:

```bash
php bin/console sylius:store-assembler:plugin:prepare
php bin/console sylius:store-assembler:plugin:install
php bin/console sylius:store-assembler:fixture:prepare
php bin/console sylius:store-assembler:fixture:load
php bin/console sylius:store-assembler:theme:prepare
```

## Configuration

- **Plugin Manifests:** Place JSON manifests in `config/plugins/{vendor}/{plugin-name}/{major.minor}/manifest.json`.  

Manifests support:

- **Steps:** List of shell commands (e.g., `composer require`).
- **Configurators:** Implementations of `Sylius\StoreAssemblerBundle\Configurator\ConfiguratorInterface`.

Example manifest:

```json
{
  "steps": [
    "yarn add some/package"
  ],
  "configurators": [
    {
      "class": "Sylius\\\\StoreAssemblerBundle\\\\Configurator\\\\YamlNodeConfigurator",
      "file": "config/packages/my_package.yaml",
      "key": "my_package.some_configuration_key.enabled",
      "value": true
    }
  ]
}
```

## Contributing

Contributions are welcome! By submitting a pull request, you agree to license your changes under the MIT License. Please follow the existing project conventions and tests.

## License

This project is released under the [MIT License](LICENSE).
