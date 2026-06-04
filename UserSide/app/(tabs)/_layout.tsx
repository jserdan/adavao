import { Tabs } from 'expo-router';
import React, { useEffect, useCallback } from 'react';
import { Platform, BackHandler } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { router, usePathname } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';

import { HapticTab } from '@/components/HapticTab';
import { IconSymbol } from '@/components/ui/IconSymbol';
import TabBarBackground from '@/components/ui/TabBarBackground';
import { Appearance } from 'react-native';
import { Colors } from '@/constants/Colors';
import { useColorScheme } from '@/hooks/useColorScheme';

export default function TabLayout() {
  const colorScheme = Appearance.getColorScheme();
  const pathname = usePathname();

  const theme = colorScheme === 'dark' ? Colors.dark : Colors.light;

  // Auth guard: Check auth on every screen focus
  useFocusEffect(
    useCallback(() => {
      const checkAuth = async () => {
        try {
          const userData = await AsyncStorage.getItem('userData');
          const isLoginScreen = pathname === '/login' || pathname === '/(tabs)/login';
          const isRegisterScreen = pathname === '/register' || pathname === '/(tabs)/register';
          const isForgotPasswordScreen = pathname === '/forgot-password' || pathname === '/(tabs)/forgot-password';

          // If not on auth screens and no userData, redirect to login
          const isReportScreen = pathname === '/report' || pathname === '/(tabs)/report';

          if (!isLoginScreen && !isRegisterScreen && !isForgotPasswordScreen && !isReportScreen && !userData) {
            console.log('🔒 Auth guard: No user data, redirecting to login');
            router.replace('/(tabs)/login');
          } else if (userData) {
            const user = JSON.parse(userData);
            const effectiveRole = String(user.user_role || user.role || '').toLowerCase();
            
            // Prevent Patrol officers from accessing Citizen tabs like report/history/profile
            if (effectiveRole.includes('patrol') && !isLoginScreen) {
              console.log('👮 Auth guard: Patrol officer trying to access citizen tabs, redirecting to patrol layout');
              router.replace('/(patrol)/dashboard' as any);
            }
          }
        } catch (error) {
          console.error('Auth guard error:', error);
        }
      };

      checkAuth();
    }, [pathname])
  );

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: Colors[colorScheme ?? 'light'].tint,
        headerShown: false,
        tabBarButton: HapticTab,
        tabBarBackground: TabBarBackground,
        tabBarStyle: {
          display: 'none'
        },
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: '',
          headerShown: false,
          tabBarIcon: ({ color }) => <IconSymbol size={28} name="house.fill" color={color} />,
        }}
      />
    </Tabs>
  );
}
