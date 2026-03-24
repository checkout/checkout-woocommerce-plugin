import js from "@eslint/js";
import globals from "globals";

export default [
    js.configs.recommended,
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "script",
            globals: {
                ...globals.browser,
                ...globals.jquery,
                // WordPress globals
                wp: "readonly",
                jQuery: "readonly",
                $: "readonly",
                ajaxurl: "readonly",
                // Plugin-specific globals
                cko_flow_vars: "readonly",
                ckoFlow: "writable",
                ckoLogger: "readonly",
                CheckoutWebComponents: "readonly",
                FlowSessionStorage: "readonly",
                FlowState: "readonly",
                // WooCommerce globals
                wc_checkout_params: "readonly",
                wc_cart_params: "readonly",
                woocommerce_params: "readonly",
            },
        },
        rules: {
            // Security rules
            "no-eval": "error",
            "no-implied-eval": "error",
            "no-new-func": "error",
            
            // Code quality
            "no-unused-vars": ["warn", { 
                "argsIgnorePattern": "^_",
                "varsIgnorePattern": "^_"
            }],
            "no-undef": "error",
            "no-redeclare": "error",
            "no-shadow": "warn",
            
            // Best practices
            "eqeqeq": ["warn", "smart"],
            "no-var": "warn",
            "prefer-const": "warn",
            
            // Potential bugs
            "no-dupe-keys": "error",
            "no-duplicate-case": "error",
            "no-empty": "warn",
            "no-extra-boolean-cast": "warn",
            "no-unreachable": "error",
            "use-isnan": "error",
            "valid-typeof": "error",
            
            // Relaxed rules for legacy code
            "no-prototype-builtins": "off",
            "no-async-promise-executor": "warn",
        },
    },
    {
        // Ignore patterns
        ignores: [
            "vendor/**",
            "node_modules/**",
            "e2e-tests/**",
            "**/*.min.js",
            "assets/js/lib/**",
        ],
    },
];
