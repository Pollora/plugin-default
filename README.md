# %plugin_name%

%plugin_description%

Built with [Pollora](https://pollora.dev) from the [plugin-default](https://github.com/Pollora/plugin-default) template.

## What's inside

```
%plugin_name%/
├── app/                              # PHP classes, namespace Plugin\%plugin_namespace%
│   ├── Providers/                    # Service providers (plugin, assets)
│   └── %plugin_namespace%Plugin.php  # Main class: activation, hooks declared with attributes
├── config/plugin.php                 # Name, version, text domain, assets path
├── resources/
│   ├── assets/                       # CSS and JS, built with Vite (when generated with assets)
│   └── views/                        # Blade templates
└── %plugin_name%.php                 # Plugin header, registers the plugin with Pollora
```

Hooks are declared on methods with attributes and discovered by Pollora, no `add_action()` needed:

```php
#[Action('init', priority: 10)]
public function onInit(): void
{
    // …
}
```

## Commands

Run from the plugin's directory, when it has assets:

```bash
npm run dev      # Vite dev server with hot reload
npm run build    # production assets
```

From the project root:

```bash
php artisan pollora:make:block my-block --plugin=%plugin_name%   # a new Gutenberg block
php artisan discovery:clear                                      # after adding attribute-based classes
php artisan pollora:doctor                                       # when something fails without an error
```

## Read more

- [Plugins](https://pollora.dev/advanced/plugins/): architecture, namespaces, discovery, assets
- [Actions and filters](https://pollora.dev/hooks/actions-filters/)
- [Gutenberg blocks](https://pollora.dev/blocks/gutenberg-blocks/)
