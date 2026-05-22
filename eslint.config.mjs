import globals from 'globals';
import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import prettierConfig from 'eslint-config-prettier/flat';

export default [
    {
        ignores: [
            '**/vendor/**',
            'public/**',
            '**/node_modules/**',
            'storage/**',
            'bootstrap/cache/**',
            '**/dist/**',
            'metronic-tailwind-html-demos/**',
        ],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    prettierConfig,
    {
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },
];
