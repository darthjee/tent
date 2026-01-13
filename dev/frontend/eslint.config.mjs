import js from '@eslint/js';
import complexity from 'eslint-plugin-complexity';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import jasmine from 'eslint-plugin-jasmine';
import globals from 'globals';

export default [
  {
    ignores: ['node_modules/**/*.js', 'dist/**/*.js', 'report/**'],
  },
  js.configs.recommended,
  {
    files: ['**/*.{js,jsx,mjs}'],
    plugins: {
      complexity,
      react,
      'react-hooks': reactHooks,
    },
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: {
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.es2021,
      },
    },
    settings: {
      react: {
        version: 'detect',
      },
    },
    rules: {
      // Complexity rules
      complexity: ['warn', { max: 10 }],
      'max-lines': ['warn', { max: 300 }],
      'max-depth': ['warn', { max: 4 }],

      // Code style
      indent: ['error', 2, { SwitchCase: 1 }],
      'linebreak-style': ['error', 'unix'],
      quotes: ['error', 'single', { avoidEscape: true }],
      semi: ['error', 'always'],

      // Best practices
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      eqeqeq: ['error', 'always'],
      'no-var': 'error',
      'prefer-const': 'error',

      // React rules
      'react/jsx-uses-react': 'error',
      'react/jsx-uses-vars': 'error',
      'react/prop-types': 'off', // Disabled - project doesn't use PropTypes
      'react/react-in-jsx-scope': 'off', // React 17+ doesn't need this

      // React Hooks rules
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'warn',
    },
  },
  // Jasmine spec files configuration
  {
    files: ['spec/**/*_spec.js', 'spec/**/*[sS]pec.js'],
    plugins: {
      jasmine,
    },
    languageOptions: {
      globals: {
        ...globals.jasmine,
      },
    },
    rules: {
      'jasmine/no-focused-tests': 'error',
      'jasmine/no-disabled-tests': 'warn',
    },
  },
];