import { Page, TestInfo } from '@playwright/test';
import { getEnvConfig } from '../config/env-loader';

export type LogLevel = 'debug' | 'info' | 'warn' | 'error';

const LOG_LEVELS: Record<LogLevel, number> = {
  debug: 0,
  info: 1,
  warn: 2,
  error: 3,
};

/**
 * Test logger with structured output and artifact attachment.
 */
export class Logger {
  private testInfo: TestInfo | null = null;
  private logs: Array<{ level: LogLevel; message: string; timestamp: Date; data?: unknown }> = [];
  private minLevel: LogLevel;

  constructor(minLevel: LogLevel = 'info') {
    this.minLevel = minLevel;
    
    // Check for debug mode from env
    try {
      const config = getEnvConfig();
      if (config.DEBUG_MODE) {
        this.minLevel = 'debug';
      }
    } catch {
      // Config not loaded yet
    }
  }

  /**
   * Attach test info for artifact attachment.
   */
  attachTestInfo(testInfo: TestInfo): void {
    this.testInfo = testInfo;
  }

  /**
   * Log at specified level.
   */
  private log(level: LogLevel, message: string, data?: unknown): void {
    if (LOG_LEVELS[level] < LOG_LEVELS[this.minLevel]) {
      return;
    }

    const entry = {
      level,
      message,
      timestamp: new Date(),
      data,
    };

    this.logs.push(entry);

    const prefix = this.getPrefix(level);
    const timestamp = entry.timestamp.toISOString().split('T')[1].slice(0, -1);
    const dataStr = data ? ` ${JSON.stringify(data)}` : '';

    console.log(`${prefix} [${timestamp}] ${message}${dataStr}`);
  }

  private getPrefix(level: LogLevel): string {
    switch (level) {
      case 'debug':
        return '🔍';
      case 'info':
        return 'ℹ️ ';
      case 'warn':
        return '⚠️ ';
      case 'error':
        return '❌';
    }
  }

  debug(message: string, data?: unknown): void {
    this.log('debug', message, data);
  }

  info(message: string, data?: unknown): void {
    this.log('info', message, data);
  }

  warn(message: string, data?: unknown): void {
    this.log('warn', message, data);
  }

  error(message: string, data?: unknown): void {
    this.log('error', message, data);
  }

  /**
   * Log a step in the test flow.
   */
  step(description: string): void {
    this.info(`📍 Step: ${description}`);
  }

  /**
   * Log an assertion being made.
   */
  assertion(description: string): void {
    this.debug(`✓ Assert: ${description}`);
  }

  /**
   * Get all logs as a string.
   */
  getLogs(): string {
    return this.logs
      .map((entry) => {
        const timestamp = entry.timestamp.toISOString();
        const data = entry.data ? ` | ${JSON.stringify(entry.data)}` : '';
        return `[${timestamp}] [${entry.level.toUpperCase()}] ${entry.message}${data}`;
      })
      .join('\n');
  }

  /**
   * Attach logs to test report on failure.
   */
  async attachLogsToReport(): Promise<void> {
    if (this.testInfo && this.logs.length > 0) {
      await this.testInfo.attach('test-logs.txt', {
        body: this.getLogs(),
        contentType: 'text/plain',
      });
    }
  }

  /**
   * Clear logs.
   */
  clear(): void {
    this.logs = [];
  }
}

/**
 * Capture console errors from the page.
 */
export function captureConsoleErrors(page: Page): string[] {
  const errors: string[] = [];

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      errors.push(`[Console Error] ${msg.text()}`);
    }
  });

  page.on('pageerror', (error) => {
    errors.push(`[Page Error] ${error.message}`);
  });

  return errors;
}

/**
 * Capture failed network requests.
 */
export function captureNetworkErrors(page: Page): Array<{ url: string; status: number }> {
  const errors: Array<{ url: string; status: number }> = [];

  page.on('response', (response) => {
    if (response.status() >= 400) {
      errors.push({
        url: response.url(),
        status: response.status(),
      });
    }
  });

  return errors;
}

/**
 * Attach console and network errors to test report.
 */
export async function attachErrorsToReport(
  testInfo: TestInfo,
  consoleErrors: string[],
  networkErrors: Array<{ url: string; status: number }>
): Promise<void> {
  if (consoleErrors.length > 0) {
    await testInfo.attach('console-errors.txt', {
      body: consoleErrors.join('\n'),
      contentType: 'text/plain',
    });
  }

  if (networkErrors.length > 0) {
    await testInfo.attach('network-errors.json', {
      body: JSON.stringify(networkErrors, null, 2),
      contentType: 'application/json',
    });
  }
}

/**
 * Default logger instance.
 */
export const logger = new Logger();
