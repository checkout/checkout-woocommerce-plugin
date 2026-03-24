import { z } from 'zod';

/**
 * Environment configuration schema with Zod validation.
 * Provides type safety and clear error messages for missing/invalid config.
 */
export const envSchema = z.object({
  // Store URLs - Required
  BASE_URL: z
    .string()
    .url('BASE_URL must be a valid URL')
    .describe('The base URL of the WooCommerce store'),
  ADMIN_URL: z
    .string()
    .url('ADMIN_URL must be a valid URL')
    .describe('The WordPress admin URL'),

  // Admin credentials - Required
  ADMIN_USER: z
    .string()
    .min(1, 'ADMIN_USER is required')
    .describe('WordPress admin username'),
  ADMIN_PASS: z
    .string()
    .min(1, 'ADMIN_PASS is required')
    .describe('WordPress admin password'),

  // Customer credentials - Optional
  CUSTOMER_USER: z
    .string()
    .optional()
    .default('')
    .describe('Customer account username (optional)'),
  CUSTOMER_PASS: z
    .string()
    .optional()
    .default('')
    .describe('Customer account password (optional)'),

  // WooCommerce REST API - Optional
  WC_CONSUMER_KEY: z
    .string()
    .optional()
    .default('')
    .describe('WooCommerce REST API consumer key'),
  WC_CONSUMER_SECRET: z
    .string()
    .optional()
    .default('')
    .describe('WooCommerce REST API consumer secret'),

  // Payment test flags
  PAYMENT_TEST_MODE: z
    .string()
    .transform((val) => val === 'true')
    .default('true')
    .describe('Enable payment test mode'),
  PAYMENT_3DS_REQUIRED: z
    .string()
    .transform((val) => val === 'true')
    .default('false')
    .describe('Simulate 3DS required scenario'),
  PAYMENT_FORCE_FAILURE: z
    .string()
    .transform((val) => val === 'true')
    .default('false')
    .describe('Force payment failure for testing'),

  // Test configuration
  RUN_ID: z
    .string()
    .optional()
    .default('')
    .describe('Unique run identifier'),
  DEBUG_MODE: z
    .string()
    .transform((val) => val === 'true')
    .default('false')
    .describe('Enable verbose debug logging'),
  SLOW_MO: z
    .string()
    .transform((val) => parseInt(val, 10) || 0)
    .default('0')
    .describe('Slow down actions by specified ms'),

  // Visual testing - Optional
  APPLITOOLS_API_KEY: z
    .string()
    .optional()
    .default('')
    .describe('Applitools API key for visual testing'),
});

export type EnvConfig = z.infer<typeof envSchema>;

/**
 * Validate environment configuration and return typed config object.
 * Throws detailed error messages on validation failure.
 */
export function validateEnv(env: Record<string, string | undefined>): EnvConfig {
  const result = envSchema.safeParse(env);

  if (!result.success) {
    const errors = result.error.issues
      .map((issue) => `  - ${issue.path.join('.')}: ${issue.message}`)
      .join('\n');

    throw new Error(
      `\n❌ Environment Configuration Error:\n${errors}\n\n` +
        `Please check your .env file or environment variables.\n` +
        `See .env.example for required configuration.\n`
    );
  }

  return result.data;
}
