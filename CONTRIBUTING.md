# Contributing to Sylius Store Assembler

Thank you for considering contributing to Sylius Store Assembler!

## How to Contribute

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/your-feature`.
3. Commit your changes: `git commit -m 'Add some feature'`.
4. Push to the branch: `git push origin feature/your-feature`.
5. Open a Pull Request against the `main` branch.

## Third-Party Sylius Plugins Support

To add support for a third-party Sylius plugin, create a JSON manifest describing its install and configuration steps.
Place your manifest under:

```text
config/plugins/{vendor}/{plugin-name}/{major.minor}/manifest.json
```

Store Assembler will normalize the installed plugin version to `major.minor` and automatically pick the highest
available manifest version that does not exceed the installed version.

In your `manifest.json`, you can define:

- `steps`: an array of shell commands to execute (e.g. `composer require`, `yarn add`).
- `configurators`: an optional array of configurator definitions. Each entry must include a `class`
  that implements `Sylius\StoreAssemblerBundle\Configurator\ConfiguratorInterface`, plus any
  additional options required by your configurator.

Example manifest:

```json
{
  "steps": [
    "composer require vendor/plugin-name:^1.2",
    "yarn add vendor/plugin-asset"
  ],
  "configurators": [
    {
      "class": "Vendor\\PluginName\\Configurator\\RouteConfigurator",
      "route": "plugin_home"
    }
  ]
}
```

After adding your manifest (and any custom configurator classes), verify support by running:

```bash
php bin/console sylius:store-assembler:plugin:prepare
php bin/console sylius:store-assembler:plugin:install
```

## Coding Standards

- Follow PSR-12 coding style.
- Use `composer cs` to check and fix code style issues.
- Use `composer phpstan` to catch static analysis errors.

## Tooling

- Install dependencies: `composer install`.
- Run Rector: `composer rector`.
- Run PHPStan: `composer phpstan`.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.