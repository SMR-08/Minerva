import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import 'dotenv/config';

const ENV = process.env.ENV || 'dev';

const environments = {
  dev: {
    baseURL: 'http://localhost:4200',
    webServer: {
      command: 'echo "Using existing dev server"',
      url: 'http://localhost:4200',
      reuseExistingServer: true,
      timeout: 10000,
    },
  },
  prod: {
    baseURL: 'http://localhost:9122',
    webServer: {
      command: 'echo "Using existing prod server"',
      url: 'http://localhost:9122',
      reuseExistingServer: true,
      timeout: 10000,
    },
  },
};

const envConfig = environments[ENV as keyof typeof environments] || environments.dev;

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: [
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['list'],
  ],
  use: {
    baseURL: envConfig.baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
    },
  ],
  webServer: envConfig.webServer,
});
