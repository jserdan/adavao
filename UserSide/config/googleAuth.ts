import * as Google from 'expo-auth-session/providers/google';
import * as WebBrowser from 'expo-web-browser';
import { makeRedirectUri } from 'expo-auth-session';
import Constants from 'expo-constants';

WebBrowser.maybeCompleteAuthSession();

// Get Google Client ID from app.json configuration
export const GOOGLE_WEB_CLIENT_ID = Constants.expoConfig?.extra?.googleWebClientId || '';
export const GOOGLE_ANDROID_CLIENT_ID = Constants.expoConfig?.extra?.googleAndroidClientId || '';
// export const GOOGLE_IOS_CLIENT_ID = Constants.expoConfig?.extra?.googleIosClientId || ''; // Future use

export const useGoogleAuth = () => {
  try {
    // Check if running in Expo Go or standalone
    const isExpoGo = Constants.appOwnership === 'expo';

    // Explicitly define schema for standalone to match Google's reverse client ID
    const REVERSE_CLIENT_ID = 'com.googleusercontent.apps.662961186057-mtki3cdvn002ndjho7soa6bdl0effe5b';

    // IMPORTANT: For Native Google Auth, the redirect URI often needs to use a SINGLE slash ':/' 
    // rather than the double slash '://' that makeRedirectUri produces.
    // We manually construct it here for the standalone build.
    const redirectUrl = isExpoGo
      ? makeRedirectUri({ scheme: 'alertdavao' })
      : `${REVERSE_CLIENT_ID}:/`;

    console.log('🔐 Google Auth Config:', {
      webClientId: GOOGLE_WEB_CLIENT_ID,
      androidClientId: GOOGLE_ANDROID_CLIENT_ID,
      redirectUrl: redirectUrl,
      appOwnership: Constants.appOwnership,
      isExpoGo: isExpoGo,
    });

    // Pass the explicit redirectUri and force account selection
    const [request, response, promptAsync] = Google.useAuthRequest({
      clientId: isExpoGo ? GOOGLE_WEB_CLIENT_ID : undefined,
      androidClientId: GOOGLE_ANDROID_CLIENT_ID,
      scopes: ['profile', 'email'],
      redirectUri: redirectUrl,
      extraParams: {
        prompt: 'select_account'
      }
    });

    return {
      request,
      response,
      promptAsync,
      isConfigured: true,
    };
  } catch (error) {
    console.error('❌ Google Auth Error:', error);
    return {
      request: null,
      response: null,
      promptAsync: async () => {
        throw new Error('Google Sign-In not configured');
      },
      isConfigured: false,
      error: error,
    };
  }
};

export const getGoogleUserInfo = async (accessToken: string) => {
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
    
    const response = await fetch(
      'https://www.googleapis.com/userinfo/v2/me',
      {
        headers: { Authorization: `Bearer ${accessToken}` },
        signal: controller.signal,
      }
    );
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      throw new Error(`Google API responded with ${response.status}`);
    }
    
    return await response.json();
  } catch (error: any) {
    if (error.name === 'AbortError') {
      console.error('Google user info request timed out');
    } else {
      console.error('Error getting Google user info:', error);
    }
    return null;
  }
};
