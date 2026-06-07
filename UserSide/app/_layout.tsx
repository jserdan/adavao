import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Stack, usePathname, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import React, { useState, useEffect } from 'react';
import 'react-native-reanimated';
import * as SplashScreen from 'expo-splash-screen';
import * as Font from 'expo-font';
import * as Updates from 'expo-updates';
import { View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons, MaterialIcons, FontAwesome } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as WebBrowser from 'expo-web-browser';
import { BACKEND_URL } from '../config/backend';

import { useColorScheme } from '@/hooks/useColorScheme';

WebBrowser.maybeCompleteAuthSession();

import LoadingScreen from '../components/LoadingScreen';
import LoadingOverlay from '../components/LoadingOverlay';
import GradientBackground from '../components/GradientBackground';
import NetworkBanner from '../components/NetworkBanner';
import { UserProvider } from '../contexts/UserContext';
import { LoadingProvider, useLoading } from '../contexts/LoadingContext';
import { inactivityManager } from '../services/inactivityManager';
import { startServerWarmup, stopServerWarmup, pingServer } from '../utils/serverWarmup';
import { createSseConnection } from '../services/sseService';

// Prevent auto-hiding splash screen
SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const [isAppReady, setIsAppReady] = useState(false);
  const [showLoadingScreen, setShowLoadingScreen] = useState(true);
  const [statusText, setStatusText] = useState('Preparing your experience...');

  useEffect(() => {
    async function prepare() {
      try {
        // IMPORTANT: Hide native splash IMMEDIATELY so LoadingScreen can show
        // This ensures the custom animation is visible
        await SplashScreen.hideAsync();

        // Clear session on cold start (exit/swipe away)
        try {
          const storedUser = await AsyncStorage.getItem('userData');
          if (storedUser) {
            const parsedUser = JSON.parse(storedUser);
            if (parsedUser?.id || parsedUser?.email) {
              fetch(`${BACKEND_URL}/logout`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'ngrok-skip-browser-warning': 'true'
                },
                body: JSON.stringify({
                  userId: parsedUser.id,
                  email: parsedUser.email
                })
              }).catch(err => console.warn('Background server logout failed:', err));
            }
          }
        } catch (e) {
          console.warn('Error reading userData for backend logout:', e);
        }
        await AsyncStorage.removeItem('userData');

        // Start warming up the server immediately (non-blocking) - DISABLED
        // pingServer();

        // Preload fonts to prevent FontFaceObserver timeout
        await Font.loadAsync({
          ...Ionicons.font,
          ...MaterialIcons.font,
          ...FontAwesome.font,
        });

        // Check for updates if in production build
        if (!__DEV__) {
          const checkStart = Date.now();
          try {
            setStatusText("Checking for updates...");
            
            // Timeout after 4 seconds for the check
            const checkTimeout = new Promise((_, reject) => 
              setTimeout(() => reject(new Error('Update check timeout')), 4000)
            );
            const update = await Promise.race([
              Updates.checkForUpdateAsync(),
              checkTimeout
            ]) as Updates.UpdateCheckResult;

            if (update.isAvailable) {
              setStatusText("Downloading updates...");
              
              // Timeout after 8 seconds for the download
              const downloadTimeout = new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Update download timeout')), 8000)
              );
              await Promise.race([
                Updates.fetchUpdateAsync(),
                downloadTimeout
              ]);

              setStatusText("Applying updates...");
              await new Promise(resolve => setTimeout(resolve, 1500));
              await Updates.reloadAsync();
              return; // Reload will restart the app
            } else {
              // Ensure we show "Checking for updates..." for at least 5 seconds
              const elapsed = Date.now() - checkStart;
              const remaining = 5000 - elapsed;
              if (remaining > 0) {
                await new Promise(resolve => setTimeout(resolve, remaining));
              }
            }
          } catch (updateError) {
            console.warn('Expo OTA updates check/download failed or timed out:', updateError.message || updateError);
            // Fallback: show status for the remainder of the 5 seconds
            const elapsed = Date.now() - checkStart;
            const remaining = 5000 - elapsed;
            if (remaining > 0) {
              await new Promise(resolve => setTimeout(resolve, remaining));
            }
          }
        }

        setStatusText("Preparing your experience...");

        // Wait for the AlertDavao animation to complete
        // Animation timing: 1000ms (A letter) + 1500ms (rest letters staggered) = 2500ms
        // Adding 500ms buffer for smooth transition = 3000ms total
        await new Promise(resolve => setTimeout(resolve, 3000));

        // Hide LoadingScreen and show app
        setShowLoadingScreen(false);
        setIsAppReady(true);
      } catch (e) {
        console.warn('Error during app initialization:', e);
        setShowLoadingScreen(false);
        setIsAppReady(true);
      }
    }

    prepare();
  }, []);

  // Show loading screen until app is ready
  if (showLoadingScreen || !isAppReady) {
    return <LoadingScreen visible={true} statusText={statusText} />;
  }

  return (
    <SafeAreaProvider>
      <LoadingProvider>
        <UserProvider>
          <AppContent />
        </UserProvider>
      </LoadingProvider>
    </SafeAreaProvider>
  );
}

function AppContent() {
  const colorScheme = useColorScheme();
  const { isLoading, loadingMessage } = useLoading();
  const router = useRouter();
  const pathname = usePathname();

  // Start inactivity manager when app loads (only if logged in, skip patrol officers)
  useEffect(() => {
    const checkAndStartInactivity = async () => {
      const AsyncStorage = (await import('@react-native-async-storage/async-storage')).default;
      const userData = await AsyncStorage.getItem('userData');
      if (userData) {
        const user = JSON.parse(userData);
        const role = String(user?.user_role || user?.role || '').toLowerCase();
        const email = String(user?.email || '').toLowerCase();
        const stationId = parseInt(user?.assigned_station_id || user?.stationId || '0', 10);
        const isPatrol = role.includes('patrol') || email.includes('patrol') || stationId > 0;
        if (!isPatrol) {
          inactivityManager.start();
        }
      }
    };
    checkAndStartInactivity();

    // Start server warmup to prevent cold start delays - DISABLED
    // startServerWarmup();

    return () => {
      inactivityManager.stop();
      // stopServerWarmup();
    };
  }, []);

  // SSE connection: runs in background, screens use onDataRefresh() to listen
  useEffect(() => {
    const connection = createSseConnection();
    return () => connection.close();
  }, []);

  // Reset inactivity timer on any touch - using onStartShouldSetResponder to capture all touches
  const handleUserActivity = () => {
    inactivityManager.resetActivity();
  };

  return (
    <View
      onTouchStart={handleUserActivity}
      style={{ flex: 1 }}
    >
      <GradientBackground>
        <SafeAreaView style={{ flex: 1 }} edges={['top', 'bottom', 'left', 'right']}>
          <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
            <Stack
              screenOptions={{
                // Add page transition animations
                animation: 'simple_push',
                animationDuration: 300,
              }}
            >
              <Stack.Screen
                name="index"
                options={{
                  headerShown: false,
                  animation: 'none',
                }}
              />
              <Stack.Screen
                name="(tabs)"
                options={{
                  headerShown: false,
                  animation: 'none',
                }}
              />
              <Stack.Screen
                name="(patrol)"
                options={{
                  headerShown: false,
                  animation: 'none',
                }}
              />
              <Stack.Screen
                name="register"
                options={{
                  headerShown: false,
                  animation: 'slide_from_right',
                  animationDuration: 300,
                }}
              />
              <Stack.Screen
                name="edit-profile"
                options={{
                  headerShown: false,
                  animation: 'slide_from_right',
                  animationDuration: 300,
                }}
              />
              <Stack.Screen
                name="+not-found"
                options={{
                  animation: 'fade_from_bottom',
                  animationDuration: 200,
                }}
              />
            </Stack>
            <StatusBar style="auto" />
            <NetworkBanner />
          </ThemeProvider>
        </SafeAreaView>
        <LoadingOverlay visible={isLoading} message={loadingMessage} />
      </GradientBackground>
    </View>
  );
}