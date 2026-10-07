<p align="center">
  <a href="https://pollora.dev">
    <img src="https://raw.githubusercontent.com/Pollora/.github/main/brand/banners/plugin-default.png" width="100%" alt="Pollora plugin template: the starting point for a Pollora WordPress plugin">
  </a>
</p>

<p align="center">
  <a href="https://github.com/Pollora/plugin-default/tags"><img src="https://img.shields.io/github/v/tag/Pollora/plugin-default?label=version" alt="Version"></a>
  <a href="../LICENSE"><img src="https://img.shields.io/github/license/Pollora/plugin-default" alt="License"></a>
</p>

The plugin template that `php artisan pollora:make:plugin` downloads: a WordPress plugin registered with [Pollora](https://pollora.dev), with a PSR-4 `app/` directory, service providers, hooks declared with PHP attributes, Blade views and an optional Vite build. You start from a plugin that already loads, instead of wiring `add_action()` calls and a bootstrap file by hand.

## Installation

```bash
php artisan pollora:make:plugin my-plugin
```

The command downloads the latest tag of this template, replaces its placeholders with your plugin's name, namespace and header, and offers to activate the plugin. With assets (`--asset`), it also runs `npm install` and `npm run build`; without them, the Vite files are left out.

Requirements: a Pollora project (PHP 8.4+ and WordPress 7.1+ for a new one; the framework itself runs on PHP 8.3+), and Node.js 20.19+ or 22.12+ (Vite 8) when the plugin has assets.

## Structure

```
%plugin_name%/
├── app/                                  # Application code (PSR-4 autoloaded)
│   ├── Providers/                        # Service providers (plugin, assets)
│   └── %plugin_namespace%Plugin.php      # Main plugin class: activation, hooks
├── config/plugin.php                     # Name, version, text domain, assets path
├── resources/
│   ├── assets/                           # CSS, JS files (Vite)
│   └── views/                            # Blade templates
└── %plugin_name%.php                     # Main plugin file: header, pollora_register()
```

The main class declares its hooks with attributes, which Pollora discovers on its own:

```php
#[Action('init', priority: 10)]
public function onInit(): void
{
    // …
}
```

## Development

```bash
npm install     # once
npm run dev     # Vite dev server with hot reload
npm run build   # production build
```

## Documentation

- [Plugins](https://pollora.dev/advanced/plugins/): creating a plugin, its architecture, the command's options and asset management
- [Hooks](https://pollora.dev/hooks/actions-filters/), [post types](https://pollora.dev/content/post-types/) and the rest of the framework at [pollora.dev](https://pollora.dev)

## Template development

This repository is a template: its files carry placeholders that `pollora:make:plugin` substitutes, and the PHP classes in `app/` are `.stub` files, so it does not run as is. Develop on a plugin generated under the code name set in `bin/package-plugin.sh`, then copy it back with `./bin/package-plugin.sh /path/to/the/plugin`, which turns the names back into placeholders. `php bin/tests/run.php` checks the result.

## Contributing

Contributions are welcome: see the [contributing guide](https://github.com/Pollora/.github/blob/main/CONTRIBUTING.md). Report security issues privately, as described in the [security policy](https://github.com/Pollora/.github/blob/main/SECURITY.md).

## License

The Pollora plugin template is open-source software licensed under the [MIT license](../LICENSE). © [RuBee group](https://rubee.group)
