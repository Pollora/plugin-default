# %plugin_name%

%plugin_description%

## Description

This plugin is built using the Pollora Framework, which provides a modern development experience by bridging Laravel and WordPress.

## Requirements

- WordPress 6.0 or higher
- PHP 8.2 or higher
- Pollora Framework

## Directory Structure

```
%plugin_name%/
├── app/                                  # Application code (PSR-4 autoloaded)
│   ├── Providers/                       # Service providers
│   └── %plugin_namespace%Plugin.php      # Main plugin class
├── config/                              # Configuration files
├── resources/
│   ├── assets/                          # CSS, JS files (Vite)
│   └── views/                           # Blade templates
└── %plugin_name%.php              # Main plugin file
```

## Development

```bash
# Install dependencies
npm install

# Development with HMR
npm run dev

# Production build
npm run build
```

## License

GPL v2 or later.

## Credits

- Built with [Pollora Framework](https://pollora.dev)
