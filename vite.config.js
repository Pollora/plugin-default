import { defineConfig } from "vite";
import laravel, { refreshPaths } from 'laravel-vite-plugin';
import { wordpressPlugin } from '@roots/vite-plugin';
import { globSync } from 'glob';
import path from 'path';
import tailwindcss from '@tailwindcss/vite';

// Environment detection
const isDocker = process.env.IS_DOCKER || process.env.DOCKER_ENV || process.env.DDEV_PRIMARY_URL;
const port = 5174; // Different port for plugins (5173 for themes)
const publicDirectory = "../../../../public";
const pluginName = path.basename(__dirname);

const getBaseUrl = () => {
    return process.env.APP_URL || process.env.DDEV_PRIMARY_URL || 'http://localhost';
};

const isHttps = getBaseUrl().startsWith('https');

const blockEntries = globSync([
    './resources/views/blocks/*/{index,view}.{js,jsx,ts,tsx}',
    './resources/views/blocks/*/{editor,style}.css',
])
    .reduce((acc, file) => {
        // Keyed by path: blocks sharing a file name never overwrite each other
        acc[file.replace(/^\.\//, '').replace(/\.\w+$/, '')] = file;
        return acc;
    }, {});
const hasBlocks = Object.keys(blockEntries).length > 0;

const getDevServerConfig = () => {
    const commonConfig = {
        server: {
            port,
            strictPort: true,
            cors: {
                origin: '*',
                methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                credentials: true
            },
        }
    };

    if (isDocker) {
        return {
            server: {
                ...commonConfig.server,
                host: '0.0.0.0',
                origin: `${getBaseUrl()}:${port}`,
                hmr: {
                    protocol: isHttps ? 'wss' : 'ws',
                    host: new URL(getBaseUrl()).hostname,
                }
            },
        };
    }

    return {
        server: {
            ...commonConfig.server,
            https: isHttps,
            host: isHttps ? new URL(getBaseUrl()).hostname : 'localhost',
            hmr: {
                protocol: isHttps ? 'wss' : 'ws',
                host: new URL(getBaseUrl()).hostname
            }
        },
    };
};

const getPluginConfig = () => ({
    base: "/build/plugin/" + pluginName,
    input: ["./resources/assets/app.js", ...Object.values(blockEntries)],
    publicDirectory,
    hotFile: path.join(publicDirectory, `${pluginName}.hot`),
    buildDirectory: path.join("build", "plugins", pluginName),
    refresh: [
        ...refreshPaths.filter((refreshPath) => refreshPath !== 'resources/views/**'),
        'public/content/plugins/'+pluginName+'/resources/views/**/*.blade.php',
        'public/content/plugins/'+pluginName+'/app/**/*.php',
    ],
});

export default defineConfig({
    base: "/build/plugin/" + pluginName,
    build: {
        emptyOutDir: false,
    },
    plugins: [
        tailwindcss(),
        laravel(getPluginConfig()),
        ...(hasBlocks ? [wordpressPlugin()] : []),
        {
            name: "blade",
            handleHotUpdate({ file, server }) {
                if (file.endsWith(".blade.php") || file.endsWith(".php")) {
                    server.ws.send({
                        type: "full-reload",
                        path: "*",
                    });
                }
            },
        },
    ],
    ...getDevServerConfig()
});
