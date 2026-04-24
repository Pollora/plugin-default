import './app.css';

/**
 * Main JavaScript file for %plugin_name% plugin.
 *
 * This file is the entry point for the plugin's frontend JavaScript.
 */

class %plugin_namespace%Plugin {
    constructor() {
        this.init();
    }

    /**
     * Initialize the plugin
     */
    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.onDOMReady());
        } else {
            this.onDOMReady();
        }
    }

    /**
     * Called when DOM is ready
     */
    onDOMReady() {
        console.log('%plugin_name% plugin loaded');
        this.setupPlugin();
    }

    /**
     * Setup plugin functionality
     */
    setupPlugin() {
        // Plugin-specific initialization
    }

    /**
     * Utility method for AJAX requests
     *
     * @param {string} action WordPress AJAX action name
     * @param {FormData|Object} data Data to send
     * @returns {Promise} Promise resolving to response data
     */
    async ajaxRequest(action, data = {}) {
        const formData = data instanceof FormData ? data : new FormData();

        if (!(data instanceof FormData)) {
            Object.entries(data).forEach(([key, value]) => {
                formData.append(key, value);
            });
        }

        formData.append('action', action);
        formData.append('nonce', window.%plugin_function_name%_ajax?.nonce || '');

        try {
            const response = await fetch(window.%plugin_function_name%_ajax?.ajax_url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            });

            return await response.json();
        } catch (error) {
            console.error('AJAX request failed:', error);
            throw error;
        }
    }
}

// Initialize the plugin
new %plugin_namespace%Plugin();
