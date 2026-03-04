/**
 * Server Warmup Utility (KEEP-ALIVE / SELF-PING)
 * Prevents Render cold starts by pinging the server periodically.
 *
 * ⚠️  CURRENTLY DISABLED — callers in app/_layout.tsx are commented out.
 *     Uncomment the import and function calls in _layout.tsx to re-enable.
 */

import { BACKEND_URL } from '../config/backend';

let warmupInterval: ReturnType<typeof setInterval> | null = null;

/**
 * Ping the server health endpoint to keep it warm
 * This prevents cold start delays on Render's free tier
 */
export const pingServer = async (): Promise<boolean> => {
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);
    
    const response = await fetch(`${BACKEND_URL}/health`, {
      method: 'GET',
      signal: controller.signal,
      headers: {
        'ngrok-skip-browser-warning': 'true'
      }
    });
    
    clearTimeout(timeoutId);
    return response.ok;
  } catch {
    return false;
  }
};

/**
 * Start periodic server warmup
 * Pings every 5 minutes to keep the server awake
 */
export const startServerWarmup = (intervalMs: number = 5 * 60 * 1000): void => {
  // Clear any existing interval
  if (warmupInterval) {
    clearInterval(warmupInterval);
  }
  
  // Initial ping
  pingServer();
  
  // Set up periodic pings (default: every 5 minutes)
  warmupInterval = setInterval(pingServer, intervalMs);
};

/**
 * Stop the warmup pings (call when app goes to background)
 */
export const stopServerWarmup = (): void => {
  if (warmupInterval) {
    clearInterval(warmupInterval);
    warmupInterval = null;
  }
};

/**
 * Pre-warm the server before a critical operation
 * Returns a promise that resolves when server responds
 */
export const ensureServerWarm = async (): Promise<void> => {
  const maxRetries = 3;
  let retries = 0;
  
  while (retries < maxRetries) {
    const isWarm = await pingServer();
    if (isWarm) {
      return;
    }
    retries++;
    // Wait a bit before retrying
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  
  // Server didn't respond, but continue anyway
  console.warn('Server warmup failed after retries, proceeding...');
};
