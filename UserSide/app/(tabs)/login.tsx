import React, { useState, useEffect } from "react";
import {
  View,
  Text,
  TextInput,
  Alert,
  ScrollView,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  Dimensions,
  StyleSheet,
  TouchableOpacity,
  Modal,
  BackHandler
} from "react-native";
import Checkbox from 'expo-checkbox';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from "expo-router";
import { useFocusEffect } from '@react-navigation/native';
import { useUser } from '../../contexts/UserContext';
import { BACKEND_URL } from '../../config/backend';
// import CaptchaObfuscated, { generateCaptchaWord } from '../../components/CaptchaObfuscated'; // Removed
// import Recaptcha from 'react-native-recaptcha-that-works';
import { PhoneInput, validatePhoneNumber } from '../../components/PhoneInput';
import { useGoogleAuth, getGoogleUserInfo } from '../../config/googleAuth';
import * as Google from 'expo-auth-session/providers/google';
import PoliceStationLookup from '../../components/PoliceStationLookup';
import { syncPoliceStations } from '../../services/policeStationService';
import { Ionicons } from '@expo/vector-icons';
import AnimatedButton from '../../components/AnimatedButton';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const isSmallScreen = SCREEN_WIDTH < 360;

// Sanitization helpers
const sanitizeEmail = (email: string): string => {
  return email.trim().toLowerCase().replace(/\s+/g, '').slice(0, 100);
};

const Login = () => {
  // 📊 Performance Timing - Start
  const pageStartTime = React.useRef(Date.now());
  React.useEffect(() => {
    const loadTime = Date.now() - pageStartTime.current;
    console.log(`📊 [Login] Page Load Time: ${loadTime}ms`);
  }, []);
  // 📊 Performance Timing - End

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [showAnonymousModal, setShowAnonymousModal] = useState(false);

  // Recaptcha State (Removed)
  // const [captchaValid, setCaptchaValid] = useState(false);
  // const recaptchaRef = React.useRef<any>(null);

  const [emailError, setEmailError] = useState("");
  const [passwordError, setPasswordError] = useState("");

  // Google Phone Modal State
  const [showPhoneModal, setShowPhoneModal] = useState(false);
  const [googleInfo, setGoogleInfo] = useState<any>(null);
  const [phoneForGoogle, setPhoneForGoogle] = useState("");
  // OTP State
  const [showOtpModal, setShowOtpModal] = useState(false);
  const [otpCode, setOtpCode] = useState("");
  const [userIdForOtp, setUserIdForOtp] = useState<string | null>(null);

  // Name collection modal state (for Google Sign-In when name not available)
  const [showNameModal, setShowNameModal] = useState(false);
  const [nameFirstName, setNameFirstName] = useState("");
  const [nameLastName, setNameLastName] = useState("");

  const [showPoliceStationLookup, setShowPoliceStationLookup] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const { setUser } = useUser();
  const router = useRouter();
  const { request, response, promptAsync } = useGoogleAuth();

  // Reset loading state whenever screen comes into focus (especially after logout)
  useFocusEffect(
    React.useCallback(() => {
      console.log('🔄 Login screen focused - resetting loading state');
      setIsLoading(false);

      // Clear password 
      setPassword("");

      // Handle Android back button on login screen — show exit confirmation
      const backHandler = BackHandler.addEventListener('hardwareBackPress', () => {
        Alert.alert(
          'Exit App',
          'Are you sure you want to exit AlertDavao?',
          [
            { text: 'Cancel', style: 'cancel' },
            { text: 'Exit', style: 'destructive', onPress: () => BackHandler.exitApp() },
          ]
        );
        return true; // Prevent default back action
      });

      return () => {
        backHandler.remove();
      };
    }, [])
  );

  useEffect(() => {
    // Load saved email if remember me was checked
    const loadSavedEmail = async () => {
      try {
        const savedEmail = await AsyncStorage.getItem('rememberedEmail');
        if (savedEmail) {
          setEmail(savedEmail);
          setRememberMe(true);
        }
      } catch (error) {
        console.error('Error loading saved email:', error);
      }
    };
    loadSavedEmail();

    // Check if user was logged out due to inactivity
    const checkInactivityLogout = async () => {
      try {
        const wasInactive = await AsyncStorage.getItem('inactivityLogout');
        if (wasInactive === 'true') {
          await AsyncStorage.removeItem('inactivityLogout');
          Alert.alert(
            'Session Expired',
            'You have been logged out due to 5 minutes of inactivity. Please log in again.',
            [{ text: 'OK' }]
          );
        }
      } catch (err) {
        console.error('Error checking inactivity logout:', err);
      }
    };

    checkInactivityLogout();

    // Sync police station data in the background (non-blocking)
    syncPoliceStations().catch(err => {
      console.warn('Background sync of police stations failed:', err);
    });

    // Warm up the server to prevent cold start delays on login
    fetch(`${BACKEND_URL}/health`, { method: 'GET' }).catch(() => { });
  }, []);

  useEffect(() => {
    console.log('🔍 Google Auth Response changed:', response?.type, response);
    if (response?.type === 'success') {
      console.log('✅ Google OAuth Success! Full response:', JSON.stringify(response, null, 2));
      const { authentication } = response;
      if (authentication?.accessToken) {
        console.log('🔑 Got access token, calling handleGoogleSignIn...');
        handleGoogleSignIn(authentication.accessToken);
      } else {
        console.log('⚠️ No access token in authentication object');
        setIsLoading(false); // Reset loading if no token
        Alert.alert('Sign-In Error', 'No access token received from Google. Please try again.');
      }
    } else if (response?.type === 'error') {
      console.log('❌ Google OAuth Error:', response.error);
      setIsLoading(false); // Reset loading on error
      Alert.alert('Google Sign-In Error', response.error?.message || 'Authentication failed');
    } else if (response?.type === 'dismiss') {
      console.log('🚪 Google OAuth Dismissed by user');
      setIsLoading(false); // Reset loading when dismissed
    }
  }, [response]);

  const handleGoogleSignIn = async (accessToken: string) => {
    setIsLoading(true);
    const startTime = Date.now();
    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 15000); // 15s timeout

      console.log('⏱️ [Google] Sending access token to backend for ultra-fast login...');
      const backendStart = Date.now();
      const response = await fetch(`${BACKEND_URL}/google-login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true'
        },
        body: JSON.stringify({
          accessToken: accessToken
        }),
        signal: controller.signal
      });
      clearTimeout(timeoutId);
      console.log(`⏱️ [Google] Backend response took ${Date.now() - backendStart}ms, total: ${Date.now() - startTime}ms`);

      const data = await response.json();

      if (response.ok) {
        // Case 1: New user needs phone number (Google doesn't provide it)
        if (data.requiresPhone) {
          console.log('📱 Phone number required for Google registration');
          setGoogleInfo({
            googleId: data.googleInfo.googleId,
            email: data.googleInfo.email,
            firstName: data.googleInfo.firstName,
            lastName: data.googleInfo.lastName,
            profilePicture: data.googleInfo.profilePicture,
          });
          setShowPhoneModal(true);
          setIsLoading(false);
          return;
        }

        // Case 2: New user but Google didn't provide name - need to collect it
        if (data.requiresName) {
          console.log('📝 Name required for Google registration');
          setGoogleInfo({
            googleId: data.googleInfo.googleId,
            email: data.googleInfo.email,
            profilePicture: data.googleInfo.profilePicture,
          });
          setShowNameModal(true);
          setIsLoading(false);
          return;
        }

        // Case 3: Login successful (existing user)
        if (data.user) {
          if (data.isNewUser) {
            Alert.alert('Welcome!', 'Your account has been created successfully.');
          }
          try {
            await Promise.race([
              processLoginSuccess(data.user),
              new Promise((_, reject) => setTimeout(() => reject(new Error('Login timeout')), 10000))
            ]);
          } catch (loginError: any) {
            console.error('processLoginSuccess error:', loginError);
            Alert.alert('Login Error', loginError.message || 'Failed to complete login. Please try again.');
            setIsLoading(false);
          }
          return;
        }

        // Fallback for backward compatibility
        Alert.alert('Login Failed', 'Unexpected response from server');
        setIsLoading(false);
      } else {
        Alert.alert('Login Failed', data.message || 'Google login failed');
        setIsLoading(false);
      }
    } catch (err: any) {
      console.error('Google Sign-In Error:', err);
      const errorMessage = err.message || 'Unknown error';
      if (errorMessage.includes('aborted')) {
        Alert.alert('Connection Timeout', 'The server is taking too long to respond. Please check your connection or try again later.', [{ text: 'OK' }]);
      } else if (errorMessage.includes('Failed to fetch') || errorMessage.includes('Network request failed')) {
        Alert.alert(
          'Connection Error',
          `Cannot connect to server at ${BACKEND_URL}.\nPlease check:\n1. Your internet connection\n2. Backend server is running\n3. Your device is on the same Wi-Fi as server`,
          [{ text: 'OK' }]
        );
      } else {
        Alert.alert('Network Error', errorMessage);
      }
      setIsLoading(false);
    }
  };

  // [NEW] Handle OTP Verification
  const handleVerifyOtp = async () => {
    if (!otpCode || otpCode.length !== 6) {
      Alert.alert('Invalid OTP', 'Please enter a valid 6-digit code.');
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(`${BACKEND_URL}/google-verify-otp`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId: userIdForOtp,
          otp: otpCode
        })
      });

      const data = await response.json();

      if (response.ok) {
        setShowOtpModal(false);
        await processLoginSuccess(data.user);
      } else {
        Alert.alert('Verification Failed', data.message || 'Invalid OTP');
        setIsLoading(false);
      }
    } catch (err) {
      Alert.alert('Error', 'Failed to verify OTP');
      setIsLoading(false);
    }
  };

  // [NEW] Handle name submission for Google registration
  const handleSubmitName = async () => {
    if (!nameFirstName.trim() || !nameLastName.trim()) {
      Alert.alert('Error', 'Please enter both first and last name.');
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(`${BACKEND_URL}/google-complete-registration`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          googleId: googleInfo.googleId,
          email: googleInfo.email,
          firstName: nameFirstName.trim(),
          lastName: nameLastName.trim(),
          profilePicture: googleInfo.profilePicture,
        })
      });

      const data = await response.json();

      if (response.ok && data.user) {
        setShowNameModal(false);
        setNameFirstName('');
        setNameLastName('');
        Alert.alert('Welcome!', 'Your account has been created successfully.');
        await processLoginSuccess(data.user);
      } else {
        Alert.alert('Registration Failed', data.message || 'Failed to complete registration');
        setIsLoading(false);
      }
    } catch (err) {
      console.error('Name submission error:', err);
      Alert.alert('Error', 'Failed to complete registration');
      setIsLoading(false);
    }
  };
  const handleGoogleRegisterWithPhone = async () => {
    if (!googleInfo) return;

    // Validate phone
    if (!validatePhoneNumber(phoneForGoogle)) {
      Alert.alert('Invalid Phone', 'Please enter a valid Philippine mobile number (e.g., +639...)');
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(`${BACKEND_URL}/google-register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...googleInfo,
          contact: phoneForGoogle
        })
      });

      const data = await response.json();
      if (response.ok) {
        setShowPhoneModal(false);

        // Check if OTP is now required (it should be)
        if (data.requireOtp) {
          setUserIdForOtp(data.userId);
          setShowOtpModal(true);
          setIsLoading(false);
          return;
        }

        await processLoginSuccess(data.user);
      } else {
        Alert.alert('Registration Failed', data.message || 'Failed to register with Google');
        setIsLoading(false);
      }
    } catch (err) {
      Alert.alert('Error', 'Failed to complete registration');
      setIsLoading(false);
    }
  };

  const processLoginSuccess = async (user: any): Promise<void> => {
    console.log('✅ Login successful for:', user.email);
    console.log('📦 Full user data received:', user);

    // Store complete user data in AsyncStorage
    await AsyncStorage.setItem('userData', JSON.stringify(user));

    // Clear any stale inactivity logout flag
    await AsyncStorage.removeItem('inactivityLogout');

    // Set user in context with all available fields
    setUser({
      id: user.id?.toString() || user.userId?.toString() || '0',
      firstName: user.firstname || user.firstName || '',
      lastName: user.lastname || user.lastName || '',
      email: user.email || '',
      phone: user.contact || user.phone || '',
      address: user.address || '',
      isVerified: Boolean(user.is_verified || user.isVerified),
      profileImage: user.profile_image || user.profileImage || '',
      createdAt: user.createdAt || user.created_at || '',
      updatedAt: user.updatedAt || user.updated_at || '',
    });

    if (rememberMe) {
      await AsyncStorage.setItem('rememberedEmail', user.email);
    } else {
      await AsyncStorage.removeItem('rememberedEmail');
    }

    const effectiveRole = String(user.user_role || user.role || '').toLowerCase();
    const userEmail = String(user.email || '').toLowerCase();
    const assignedStation = parseInt(user.assigned_station_id || user.stationId || '0', 10);
    const isPatrol = effectiveRole.includes('patrol') || userEmail.includes('patrol') || assignedStation > 0;

    if (userEmail.includes('ps') || userEmail.includes('patrol')) {
        Alert.alert(
            "Login Debug Info",
            `Email: ${userEmail}\nRole: ${effectiveRole}\nStation: ${assignedStation}\nisPatrol: ${isPatrol}`
        );
    }

    // Start inactivity manager (skip for patrol officers - they need persistent sessions)
    if (!isPatrol) {
      const { inactivityManager } = await import('../../services/inactivityManager');
      inactivityManager.start();
    }

    // Role-based redirect
    if (isPatrol) {
      console.log('🚓 Patrol officer detected, redirecting to patrol dashboard');
      try {
        const { initializePushNotifications } = await import('../../services/pushNotificationService');
        await initializePushNotifications(user.id);
      } catch (error) {
        console.error('Failed to initialize push notifications:', error);
      }
      setTimeout(() => {
        setIsLoading(false);
        router.replace('/(patrol)/dashboard' as any);
      }, 150);
    } else {
      console.log('🚀 Navigating to /(tabs) (home)...');
      setTimeout(() => {
        setIsLoading(false);
        router.replace('/(tabs)');
      }, 150);
    }
  };

  const handleLogin = async () => {
    setEmailError("");
    setPasswordError("");

    // Sanitize email
    const sanitizedEmail = sanitizeEmail(email);

    if (!sanitizedEmail || !password) {
      Alert.alert('Error', 'Please enter email and password');
      return;
    }

    // Validate email format
    if (!sanitizedEmail.includes('@')) {
      setEmailError('Please enter a valid email address');
      return;
    }

    // Verify captcha (Removed)
    /* if (!captchaValid) {
      Alert.alert('Security Check', 'Please verify that you are not a robot.');
      recaptchaRef.current?.open();
      return;
    } */

    setIsLoading(true);
    console.log('🔑 Starting login for:', sanitizedEmail);
    const loginUrl = `${BACKEND_URL}/login`;

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout

      const response = await fetch(loginUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "ngrok-skip-browser-warning": "true"
        },
        body: JSON.stringify({ email: sanitizedEmail, password }),
        signal: controller.signal
      });
      clearTimeout(timeoutId);

      const contentType = response.headers.get('content-type') || '';
      let data: any;
      if (contentType.includes('application/json')) {
        data = await response.json();
      } else {
        // Server returned non-JSON (e.g., HTML error page from a proxy)
        const text = await response.text();
        console.error('Non-JSON response from server:', text.substring(0, 200));
        Alert.alert('Server Error', 'The server returned an unexpected response. Please try again.');
        setIsLoading(false);
        return;
      }

      if (response.ok) {
        const user = data.user || data;

        // Check user role restrictions
        if (user.role === 'police' || user.role === 'admin') {
          Alert.alert('Error', 'Police and Admin users must log in through the AdminSide dashboard.');
          setIsLoading(false);
          return;
        }

        await processLoginSuccess(user);
      } else {
        // Display error messages
        const errorMessage = data.message || 'Login failed';
        const lowerError = errorMessage.toLowerCase();

        // Handle email not verified
        if (data.emailNotVerified) {
          Alert.alert(
            'Email Not Verified',
            'Please verify your email address before logging in.',
            [{ text: 'OK' }]
          );
        } else if (lowerError.includes('user') || lowerError.includes('email') || lowerError.includes('not found')) {
          setEmailError(errorMessage);
        } else if (lowerError.includes('password') || lowerError.includes('incorrect') || lowerError.includes('invalid')) {
          setPasswordError(errorMessage);
        } else {
          Alert.alert('Login Failed', errorMessage);
        }
        setIsLoading(false);
      }
    } catch (err: any) {
      console.error('Login Error:', err);
      const errorMessage = err.message || 'Unknown error';
      if (err.name === 'AbortError' || errorMessage.includes('aborted')) {
        Alert.alert('Connection Timeout', 'The server is taking too long to respond.', [{ text: 'OK' }]);
      } else {
        Alert.alert('Network Error', errorMessage);
      }
      setIsLoading(false);
    }
  };

  const handleForgotPassword = () => {
    router.push('/(tabs)/forgot-password');
  };

  const handleSignUp = () => {
    router.push('/(tabs)/register');
  };

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: '#fff' }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={{
          paddingTop: Platform.OS === 'ios' ? 50 : 20,
          paddingBottom: 100,
          paddingHorizontal: isSmallScreen ? 16 : 20,
          alignItems: 'center',
        }}
        keyboardShouldPersistTaps="handled"
      >
        <View style={{ width: '100%', maxWidth: 420 }}>
          {/* Police Station Lookup Icon */}
          <TouchableOpacity
            style={localStyles.policeIconButton}
            onPress={() => setShowPoliceStationLookup(true)}
          >
            <Ionicons name="ellipsis-horizontal-circle" size={28} color="#1D3557" />
          </TouchableOpacity>

          <Text style={localStyles.title}>
            <Text style={localStyles.alertText}>Alert</Text>
            <Text style={localStyles.davaoText}>Davao</Text>
          </Text>

          <Text style={localStyles.subtitle}>Welcome back to AlertDavao!</Text>
          <Text style={localStyles.description}>Sign in to your account</Text>

          {/* Email Field */}
          <View style={localStyles.fieldContainer}>
            <Text style={localStyles.label}>Email <Text style={{ color: 'red' }}>*</Text></Text>
            <TextInput
              style={[localStyles.input, emailError && localStyles.inputError]}
              placeholder="Enter your email"
              placeholderTextColor="#9ca3af"
              value={email}
              onChangeText={(text) => {
                setEmail(sanitizeEmail(text));
                setEmailError("");
              }}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
            />
            {emailError ? (
              <Text style={localStyles.errorText}>⚠️ {emailError}</Text>
            ) : null}
          </View>

          {/* Password Field */}
          <View style={localStyles.fieldContainer}>
            <Text style={localStyles.label}>Password <Text style={{ color: 'red' }}>*</Text></Text>
            <View style={localStyles.inputWrapper}>
              <TextInput
                style={[localStyles.input, localStyles.passwordInput, passwordError && localStyles.inputError]}
                placeholder="Enter your password"
                placeholderTextColor="#9ca3af"
                value={password}
                onChangeText={(text) => {
                  setPassword(text);
                  setPasswordError("");
                }}
                secureTextEntry={!showPassword}
              />
              <Pressable
                onPress={() => setShowPassword(!showPassword)}
                style={localStyles.toggleButton}
              >
                <Text style={localStyles.toggleText}>
                  {showPassword ? 'HIDE' : 'SHOW'}
                </Text>
              </Pressable>
            </View>
            {passwordError ? (
              <Text style={localStyles.errorText}>⚠️ {passwordError}</Text>
            ) : null}
          </View>

          {/* Recaptcha Section Removed */ /*}
          {/* <View style={localStyles.captchaSection}>
             ... Recaptcha removed ...
          </View> */}

          {/* Remember Me Checkbox */}
          <View style={localStyles.rememberMeContainer}>
            <Checkbox
              value={rememberMe}
              onValueChange={setRememberMe}
              color={rememberMe ? '#1D3557' : undefined}
              style={localStyles.checkbox}
            />
            <Text style={localStyles.rememberMeText}>Remember my email</Text>
          </View>

          {/* Login Button */}
          <TouchableOpacity
            style={[
              localStyles.loginButton,
              (isLoading) && localStyles.loginButtonDisabled
            ]}
            onPress={handleLogin}
            disabled={isLoading}
          >
            <Text style={localStyles.loginButtonText}>
              {isLoading ? "Logging in..." : "Login"}
            </Text>
          </TouchableOpacity>

          <Text onPress={handleForgotPassword} style={localStyles.forgotPassword}>
            Forgot Password?
          </Text>

          <Text style={localStyles.signUpPrompt}>
            Don&apos;t have an account?{' '}
            <Text onPress={handleSignUp} style={localStyles.signUpLink}>
              Sign Up
            </Text>
          </Text>

          {/* Divider for Anonymous */}
          <View style={localStyles.divider}>
            <View style={localStyles.dividerLine} />
            <Text style={localStyles.dividerText}>or</Text>
            <View style={localStyles.dividerLine} />
          </View>

          {/* Report Anonymously Button - Moved here */}
          <TouchableOpacity
            style={localStyles.anonymousButton}
            onPress={() => setShowAnonymousModal(true)}
            disabled={isLoading}
          >
            <Text style={localStyles.anonymousButtonText}>
              Report Anonymously
            </Text>
          </TouchableOpacity>

          {/* Divider */}
          <View style={localStyles.divider}>
            <View style={localStyles.dividerLine} />
            <Text style={localStyles.dividerText}>or continue with</Text>
            <View style={localStyles.dividerLine} />
          </View>

          {/* Google Sign-In */}
          <Pressable
            onPress={() => promptAsync()}
            disabled={isLoading || !request}
            style={[localStyles.googleButton, (isLoading || !request) && { opacity: 0.5 }]}
          >
            <View style={localStyles.googleIconContainer}>
              <Text style={localStyles.googleG}>G</Text>
            </View>
            <Text style={localStyles.googleButtonText}>
              {isLoading ? 'Signing in...' : 'Sign in with Google'}
            </Text>
          </Pressable>

          {/* Emergency Contact Info */}
          <View style={localStyles.emergencyBox}>
            <Text style={localStyles.emergencyIcon}>🚨</Text>
            <View style={localStyles.emergencyContent}>
              <Text style={localStyles.emergencyTitle}>Need Emergency Help?</Text>
              <Text style={localStyles.emergencyText}>
                Tap the options icon (⋯) above to find your nearest police station contact
              </Text>
            </View>
          </View>
        </View>
      </ScrollView>

      {/* Phone Number Modal */}
      <Modal
        visible={showPhoneModal}
        transparent={true}
        animationType="slide"
        onRequestClose={() => setShowPhoneModal(false)}
      >
        <View style={localStyles.modalContainer}>
          <View style={localStyles.modalContent}>
            <Text style={localStyles.modalTitle}>Complete Registration</Text>
            <Text style={localStyles.modalSubtitle}>Please enter your mobile number to continue.</Text>

            <PhoneInput
              value={phoneForGoogle}
              onChangeText={setPhoneForGoogle}
              placeholder="9XX XXX XXXX"
            />

            <TouchableOpacity
              style={localStyles.modalButton}
              onPress={handleGoogleRegisterWithPhone}
              disabled={isLoading}
            >
              <Text style={localStyles.modalButtonText}>
                {isLoading ? 'Saving...' : 'Save & Continue'}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[localStyles.modalButton, { backgroundColor: '#ccc', marginTop: 10 }]}
              onPress={() => setShowPhoneModal(false)}
            >
              <Text style={localStyles.modalButtonText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* OTP Modal */}
      <Modal
        visible={showOtpModal}
        transparent={true}
        animationType="slide"
        onRequestClose={() => setShowOtpModal(false)}
      >
        <View style={localStyles.modalContainer}>
          <View style={localStyles.modalContent}>
            <Text style={localStyles.modalTitle}>Verify OTP</Text>
            <Text style={localStyles.modalSubtitle}>
              A verification code has been sent to your phone.
            </Text>

            <TextInput
              style={[localStyles.input, { textAlign: 'center', fontSize: 24, letterSpacing: 5 }]}
              value={otpCode}
              onChangeText={setOtpCode}
              placeholder="000000"
              keyboardType="number-pad"
              maxLength={6}
            />

            <TouchableOpacity
              style={[localStyles.modalButton, { marginTop: 20 }]}
              onPress={handleVerifyOtp}
              disabled={isLoading}
            >
              <Text style={localStyles.modalButtonText}>
                {isLoading ? 'Verifying...' : 'Verify & Login'}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[localStyles.modalButton, { backgroundColor: '#ccc', marginTop: 10 }]}
              onPress={() => setShowOtpModal(false)}
            >
              <Text style={localStyles.modalButtonText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* Police Station Lookup Modal */}
      <PoliceStationLookup
        visible={showPoliceStationLookup}
        onClose={() => setShowPoliceStationLookup(false)}
      />

      {/* Name Collection Modal (for Google Sign-In when name not provided) */}
      <Modal
        visible={showNameModal}
        transparent={true}
        animationType="slide"
        onRequestClose={() => setShowNameModal(false)}
      >
        <View style={localStyles.modalContainer}>
          <View style={localStyles.modalContent}>
            <Text style={localStyles.modalTitle}>Complete Your Profile</Text>
            <Text style={localStyles.modalSubtitle}>
              Please enter your name to finish creating your account.
            </Text>

            <TextInput
              style={localStyles.input}
              value={nameFirstName}
              onChangeText={setNameFirstName}
              placeholder="First Name"
              autoCapitalize="words"
            />

            <TextInput
              style={[localStyles.input, { marginTop: 10 }]}
              value={nameLastName}
              onChangeText={setNameLastName}
              placeholder="Last Name"
              autoCapitalize="words"
            />

            <TouchableOpacity
              style={[localStyles.modalButton, { marginTop: 20 }]}
              onPress={handleSubmitName}
              disabled={isLoading}
            >
              <Text style={localStyles.modalButtonText}>
                {isLoading ? 'Creating Account...' : 'Continue'}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[localStyles.modalButton, { backgroundColor: '#ccc', marginTop: 10 }]}
              onPress={() => setShowNameModal(false)}
            >
              <Text style={localStyles.modalButtonText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* Anonymous Reporting Guidelines Modal */}
      <Modal
        visible={showAnonymousModal}
        transparent={true}
        animationType="slide"
        onRequestClose={() => setShowAnonymousModal(false)}
      >
        <View style={{ flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', padding: 20 }}>
          <View style={{ backgroundColor: 'white', borderRadius: 12, padding: 24, maxHeight: '80%' }}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
              <Text style={{ fontSize: 20, fontWeight: 'bold', color: '#1D3557' }}>Anonymous Reporting</Text>
              <TouchableOpacity onPress={() => setShowAnonymousModal(false)}>
                <Ionicons name="close" size={28} color="#666" />
              </TouchableOpacity>
            </View>

            <ScrollView>
              {/* Scroll indicator at top */}
              <View style={{ alignItems: 'center', paddingVertical: 8 }}>
                <Ionicons name="chevron-down" size={24} color="#999" />
                <Text style={{ fontSize: 11, color: '#999', marginTop: 2 }}>Scroll for more info</Text>
              </View>

              <View style={{ backgroundColor: '#fff3cd', padding: 12, borderRadius: 8, marginBottom: 16, borderLeftWidth: 4, borderLeftColor: '#ffc107' }}>
                <Text style={{ color: '#856404', fontWeight: 'bold', marginBottom: 8 }}>⚠️ Important Information</Text>
                <Text style={{ color: '#856404', fontSize: 14, lineHeight: 20 }}>
                  When you report anonymously, you will NOT receive updates about your report. Police cannot contact you for follow-up questions.
                </Text>
              </View>

              <Text style={{ fontSize: 16, fontWeight: '600', color: '#1D3557', marginBottom: 8 }}>Guidelines:</Text>
              <Text style={{ fontSize: 14, color: '#333', lineHeight: 22, marginBottom: 12 }}>
                • Provide as much detail as possible{'\n'}
                • Include photo/video evidence if available{'\n'}
                • Be accurate with location and time{'\n'}
                • Only report real incidents{'\n'}
                • You can only submit 1 anonymous report every 10 minutes
              </Text>

              <Text style={{ fontSize: 16, fontWeight: '600', color: '#1D3557', marginBottom: 8 }}>What Happens Next:</Text>
              <Text style={{ fontSize: 14, color: '#333', lineHeight: 22, marginBottom: 16 }}>
                1. Your report will be reviewed by police{'\n'}
                2. If valid, it will be assigned to the appropriate station{'\n'}
                3. You will NOT receive any updates or notifications{'\n'}
                4. Police will investigate based on the information you provide
              </Text>

              <TouchableOpacity
                style={{ backgroundColor: '#1D3557', padding: 16, borderRadius: 8, alignItems: 'center', marginTop: 8 }}
                onPress={() => {
                  setShowAnonymousModal(false);
                  router.push({ pathname: '/report', params: { anonymous: 'true' } });
                }}
              >
                <Text style={{ color: 'white', fontSize: 16, fontWeight: '600' }}>I Understand, Continue</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={{ padding: 12, alignItems: 'center', marginTop: 8 }}
                onPress={() => setShowAnonymousModal(false)}
              >
                <Text style={{ color: '#666', fontSize: 14 }}>Cancel</Text>
              </TouchableOpacity>
            </ScrollView>
          </View>
        </View>
      </Modal>
    </KeyboardAvoidingView>
  );
};

const localStyles = StyleSheet.create({
  policeIconButton: {
    position: 'absolute',
    top: -10,
    right: 0,
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#1D3557',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 3,
    zIndex: 10,
  },
  title: {
    fontSize: Math.min(32, SCREEN_WIDTH * 0.08),
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: 8,
  },
  alertText: { color: '#1D3557' },
  davaoText: { color: '#000' },
  subtitle: {
    fontSize: Math.min(18, SCREEN_WIDTH * 0.045),
    fontWeight: '600',
    textAlign: 'center',
    color: '#374151',
    marginBottom: 4,
  },
  description: {
    fontSize: isSmallScreen ? 13 : 14,
    textAlign: 'center',
    color: '#6b7280',
    marginBottom: 24,
  },
  fieldContainer: { marginBottom: 16 },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 6,
  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    position: 'relative',
  },
  input: {
    width: '100%',
    height: 48,
    borderWidth: 1.5,
    borderColor: '#d1d5db',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 15,
    backgroundColor: '#fff',
    color: '#1f2937',
  },
  passwordInput: { paddingRight: 60 },
  inputValid: { borderColor: '#22c55e' },
  inputError: { borderColor: '#ef4444' },
  toggleButton: {
    position: 'absolute',
    right: 12,
    paddingVertical: 8,
    paddingHorizontal: 4,
  },
  toggleText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#1D3557',
  },
  errorText: {
    fontSize: 12,
    color: '#ef4444',
    marginTop: 4,
  },
  captchaSection: { marginBottom: 20 },
  captchaTrigger: {
    width: '100%',
    padding: 15,
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    alignItems: 'center',
    marginBottom: 20,
    backgroundColor: '#f9fafb'
  },
  captchaTriggerValid: {
    backgroundColor: '#10B981',
    borderColor: '#10B981'
  },
  captchaText: {
    fontSize: 16,
    color: '#374151',
    fontWeight: '500'
  },
  rememberMeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  checkbox: { marginRight: 8 },
  rememberMeText: { fontSize: 14, color: '#374151' },
  loginButton: {
    backgroundColor: '#1D3557',
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginBottom: 12,
  },
  loginButtonDisabled: { backgroundColor: '#9ca3af' },
  loginButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  forgotPassword: {
    color: '#1D3557',
    textAlign: 'center',
    fontSize: 14,
    marginBottom: 12,
  },
  signUpPrompt: {
    textAlign: 'center',
    color: '#6b7280',
    fontSize: 14,
    marginBottom: 20,
  },
  signUpLink: { color: '#1D3557', fontWeight: '600' },
  divider: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 20,
  },
  dividerLine: {
    flex: 1,
    height: 1,
    backgroundColor: '#e5e7eb',
  },
  dividerText: {
    paddingHorizontal: 12,
    color: '#9ca3af',
    fontSize: 13,
  },
  googleButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 8,
    paddingVertical: 14,
    paddingHorizontal: 16,
  },
  googleButtonText: {
    fontSize: 15,
    color: '#374151',
    fontWeight: '500',
    marginLeft: 10,
  },
  googleIconContainer: {
    width: 20,
    height: 20,
    borderRadius: 2,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 0,
  },
  googleG: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#34A853',
  },
  anonymousButton: {
    marginTop: 4,
    marginBottom: 10,
    borderWidth: 1.5,
    borderColor: '#1D3557',
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    backgroundColor: 'transparent',
    width: '100%',
  },
  anonymousButtonText: {
    color: '#1D3557',
    fontSize: 16,
    fontWeight: 'bold',
  },
  emergencyBox: {
    flexDirection: 'row',
    backgroundColor: '#fef3c7',
    padding: 14,
    borderRadius: 10,
    marginTop: 20,
    gap: 10,
    borderWidth: 1,
    borderColor: '#fbbf24',
  },
  emergencyIcon: { fontSize: 22, marginTop: 2 },
  emergencyContent: { flex: 1 },
  emergencyTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: '#92400e',
    marginBottom: 4,
  },
  emergencyText: {
    fontSize: 12,
    color: '#78350f',
    lineHeight: 18,
  },
  // Modal Styles
  modalContainer: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20
  },
  modalContent: {
    backgroundColor: 'white',
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 400,
    alignItems: 'center'
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 8,
    color: '#1D3557'
  },
  modalSubtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginBottom: 20,
    textAlign: 'center'
  },
  modalButton: {
    width: '100%',
    backgroundColor: '#1D3557',
    padding: 14,
    borderRadius: 8,
    alignItems: 'center'
  },
  modalButtonText: {
    color: 'white',
    fontWeight: '600',
    fontSize: 16
  }
});

export default Login;
