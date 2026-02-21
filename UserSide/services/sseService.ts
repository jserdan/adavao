import { API_URL } from '../config/backend';
import { AppState, AppStateStatus } from 'react-native';

type SseHandle = {
  close: () => void;
};

type UpdateHandler = () => void;

// ---------- Global refresh event bus ----------
const listeners = new Set<() => void>();
let refreshCounter = 0;

/** Register a callback that fires whenever SSE detects a data change.
 *  Returns an unsubscribe function. */
export function onDataRefresh(cb: () => void): () => void {
  listeners.add(cb);
  return () => { listeners.delete(cb); };
}

export function getRefreshCounter(): number {
  return refreshCounter;
}

function emitRefresh() {
  refreshCounter++;
  listeners.forEach((cb) => {
    try { cb(); } catch { /* ignore */ }
  });
}

import { io, Socket } from 'socket.io-client';

// ---------- Socket.io connection ----------

export function createSseConnection(onUpdate?: UpdateHandler): SseHandle {
  const url = API_URL;
  let socket: Socket | null = null;
  let closed = false;
  let appStateSubscription: any = null;
  let lastEmit = 0;
  const THROTTLE_MS = 1000;

  const notify = () => {
    const now = Date.now();
    if (now - lastEmit < THROTTLE_MS) return;
    lastEmit = now;
    emitRefresh();
    if (onUpdate) onUpdate();
  };

  const cleanup = () => {
    if (socket) {
      socket.disconnect();
      socket = null;
    }
  };

  const connect = () => {
    if (closed) return;
    if (socket) return; // already connected or connecting

    // Extract base URL from API_URL (remove any /api path if present, though socket.io handles paths)
    // Actually API_URL is used directly
    socket = io(url, {
      transports: ['websocket', 'polling'], // Allow fallback to polling if websockets fail
      reconnectionDelayMax: 5000,
    });

    socket.on('update', () => {
      if (!closed) notify();
    });
  };

  // Reconnect when app comes to foreground
  appStateSubscription = AppState.addEventListener('change', (state: AppStateStatus) => {
    if (closed) return;
    if (state === 'active') {
      if (!socket || !socket.connected) {
        cleanup();
        connect();
      }
      notify();
    }
  });

  connect();

  return {
    close: () => {
      closed = true;
      cleanup();
      if (appStateSubscription) {
        appStateSubscription.remove();
        appStateSubscription = null;
      }
    },
  };
}