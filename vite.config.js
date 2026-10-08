import { defineConfig } from 'vite';
import pollora from '@pollora/vite-config';

export default defineConfig({
    plugins: [pollora({ type: 'plugin' })],
});
