import dotenv from 'dotenv';
import path from 'path';
import { validateEnv, EnvConfig } from './env-schema';

let cachedConfig: EnvConfig | null = null;

/**
 * Load and validate environment configuration.
 * Supports multiple .env files with priority:
 * 1. .env.local (highest priority, not committed)
 * 2. .env.{NODE_ENV}
 * 3. .env (base configuration)
 */
export function loadEnvConfig(): EnvConfig {
  if (cachedConfig) {
    return cachedConfig;
  }

  const nodeEnv = process.env.NODE_ENV || 'test';
  const rootDir = path.resolve(__dirname, '../..');

  // Load .env files in order of priority (later files override earlier)
  const envFiles = [
    path.join(rootDir, '.env'),
    path.join(rootDir, `.env.${nodeEnv}`),
    path.join(rootDir, '.env.local'),
  ];

  for (const envFile of envFiles) {
    dotenv.config({ path: envFile });
  }

  // Generate RUN_ID if not provided
  if (!process.env.RUN_ID) {
    process.env.RUN_ID = generateRunId();
  }

  cachedConfig = validateEnv(process.env);
  return cachedConfig;
}

/**
 * Get the current environment configuration.
 * Throws if not yet loaded.
 */
export function getEnvConfig(): EnvConfig {
  if (!cachedConfig) {
    return loadEnvConfig();
  }
  return cachedConfig;
}

/**
 * Generate a unique run identifier for test isolation.
 */
function generateRunId(): string {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(2, 8);
  return `run-${timestamp}-${random}`;
}

/**
 * Check if customer credentials are configured.
 */
export function hasCustomerCredentials(): boolean {
  const config = getEnvConfig();
  return Boolean(config.CUSTOMER_USER && config.CUSTOMER_PASS);
}

/**
 * Check if WooCommerce REST API credentials are configured.
 */
export function hasWcApiCredentials(): boolean {
  const config = getEnvConfig();
  return Boolean(config.WC_CONSUMER_KEY && config.WC_CONSUMER_SECRET);
}

/**
 * Get the unique email suffix for this test run.
 * Used to create unique billing emails per run.
 */
export function getRunEmailSuffix(): string {
  const config = getEnvConfig();
  return config.RUN_ID.replace(/[^a-z0-9]/gi, '');
}
