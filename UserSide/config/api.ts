import { getBackendUrlSync } from '../utils/networkUtils';

// PRODUCTION CONFIG (Render)
export const API_CONFIG = {
  // Use environment variable if available (set in eas.json), otherwise fallback to the correct Node server URL
  BASE_URL: (process.env.EXPO_PUBLIC_API_BASE_URL || 'https://adavao-1-mawm.onrender.com') + '/api',

  // Endpoints
  ENDPOINTS: {
    USERS: '/users',
    PROFILE: '/profile',
    LOGIN: '/login',
    REGISTER: '/register',
    REPORTS: '/reports',
  },

  // Request timeout in milliseconds
  TIMEOUT: 10000,

  // Headers
  DEFAULT_HEADERS: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    // Bypass ngrok browser warning for API calls
    'ngrok-skip-browser-warning': 'true',
  },
};

// Helper function to build full URL
export const buildApiUrl = (endpoint: string, id?: string) => {
  const baseUrl = API_CONFIG.BASE_URL + endpoint;
  return id ? `${baseUrl}/${id}` : baseUrl;
};
