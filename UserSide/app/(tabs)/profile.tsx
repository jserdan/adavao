import React, { useState, useEffect } from 'react';
import { View, Text, Image, StyleSheet, TouchableOpacity, ScrollView, Alert, Platform } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import styles from './styles';
import FlagStatusBadge from '../../components/FlagStatusBadge';
import { useUser } from '../../contexts/UserContext';
import { dbTest } from '../../utils/dbTest';
import { verificationService, VerificationStatus } from '../../services/verificationService';
import { notificationService } from '../../services/notificationService';
import { BACKEND_URL } from '../../config/backend';
import ChangePasswordModal from '../../components/ChangePasswordModal';

// Dashboard-matching color palette
const COLORS = {
  primary: '#1D3557',
  accent: '#E63946',
  success: '#10b981',
  warning: '#f59e0b',
  background: '#F8FAFC',
  white: '#FFFFFF',
  text: '#1e293b',
  textSecondary: '#64748b',
  textMuted: '#94a3b8',
  border: '#e2e8f0',
  cardBg: '#FFFFFF',
};

export default function ProfileScreen() {
  // 📊 Performance Timing - Start
  const pageStartTime = React.useRef(Date.now());
  React.useEffect(() => {
    const loadTime = Date.now() - pageStartTime.current;
    console.log(`📊 [Profile] Page Load Time: ${loadTime}ms`);
  }, []);
  // 📊 Performance Timing - End

  const { user, refreshProfile } = useUser();
  const [verificationStatus, setVerificationStatus] = useState<VerificationStatus | null>(null);
  const [loadingVerification, setLoadingVerification] = useState(true);
  const [idPicture, setIdPicture] = useState<string | null>(null);
  const [idSelfie, setIdSelfie] = useState<string | null>(null);
  const [billingDocument, setBillingDocument] = useState<string | null>(null);

  const [uploading, setUploading] = useState(false);
  const [flagStatus, setFlagStatus] = useState<{ totalFlags: number; restrictionLevel: string | null } | null>(null);
  const [showPasswordModal, setShowPasswordModal] = useState(false);

  const handleDatabaseTest = async () => {
    console.log('\n\n=== MANUAL DATABASE TEST STARTED ===');
    dbTest.logUserContext(user);
    await dbTest.runFullTest();

    // Refresh profile to get latest data
    if (user?.id) {
      await refreshProfile(user.id);
    }
    console.log('=== MANUAL DATABASE TEST COMPLETED ===\n\n');
  };

  // Load verification status and flag status
  useEffect(() => {
    const loadData = async () => {
      console.log('🔄 useEffect triggered, user:', user);
      if (user?.id) {
        console.log(`🚀 Loading verification status for user ID: ${user.id}`);
        setLoadingVerification(true);
        try {
          // Load verification status
          const result = await verificationService.getVerificationStatus(user.id);
          console.log('📥 Verification status result:', JSON.stringify(result, null, 2));
          if (result.success && result.data) {
            console.log('✅ Setting verification status:', result.data);
            setVerificationStatus(result.data);

            // If rejected, clear the uploaded files so user can start fresh
            if (result.data.status === 'rejected') {
              console.log('🔄 Verification rejected - resetting form');
              setIdPicture(null);
              setIdSelfie(null);
              setBillingDocument(null);
            } else if (result.data.status === 'pending' || result.data.status === 'verified') {
              // Only set existing document URLs if pending or verified
              if (result.data.id_picture) setIdPicture(result.data.id_picture);
              if (result.data.id_selfie) setIdSelfie(result.data.id_selfie);
              if (result.data.billing_document) setBillingDocument(result.data.billing_document);
            }
          } else {
            console.log('⚠️ No verification data found or API returned failure');
          }

          // Load flag status
          try {
            const notifications = await notificationService.getUserNotifications(user.id.toString());
            const flagNotif = notifications.find(n => n.type === 'user_flagged');
            if (flagNotif && flagNotif.data) {
              setFlagStatus({
                totalFlags: flagNotif.data.total_flags || 1,
                restrictionLevel: flagNotif.data.restriction_applied || null,
              });
            }
          } catch (error) {
            console.error('Error loading flag status:', error);
          }
        } catch (error) {
          console.error('💥 Error loading verification status:', error);
        } finally {
          console.log('🏁 Finished loading verification status');
          setLoadingVerification(false);
        }
      } else {
        console.log('🚫 No user ID available, skipping verification load');
      }
    };

    loadData();
  }, [user?.id]);

  // Auto-refresh verification status every 30 seconds
  useEffect(() => {
    if (!user?.id) return;

    const pollVerificationStatus = async () => {
      try {
        const result = await verificationService.getVerificationStatus(user.id);
        if (result.success && result.data) {
          // Only update if status changed to prevent flickering
          if (verificationStatus?.status !== result.data.status) {
            setVerificationStatus(result.data);

            // Update form state based on new status
            if (result.data.status === 'rejected') {
              setIdPicture(null);
              setIdSelfie(null);
              setBillingDocument(null);
            }
          }
        }
      } catch (error) {
        // Silent fail for background polling
      }
    };

    const intervalId = setInterval(pollVerificationStatus, 2000); // Poll every 2 seconds
    return () => clearInterval(intervalId);
  }, [user?.id, verificationStatus?.status]);

  // Request permissions for image picker
  const requestPermissions = async () => {
    if (Platform.OS !== 'web') {
      const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert('Sorry, we need camera roll permissions to make this work!');
        return false;
      }
    }
    return true;
  };

  // Pick image from library
  const pickImage = async (setImage: React.Dispatch<React.SetStateAction<string | null>>) => {
    const hasPermission = await requestPermissions();
    if (!hasPermission) return;

    try {
      let result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        aspect: [4, 3],
        quality: 1,
      });

      if (!result.canceled && result.assets && result.assets.length > 0) {
        const uri = result.assets[0].uri;
        setImage(uri);
      }
    } catch (error) {
      console.error('Error picking image:', error);
      Alert.alert('Error', 'Failed to pick image');
    }
  };

  // Upload a document to the server
  const uploadDocument = async (uri: string): Promise<string | null> => {
    try {
      setUploading(true);
      const formData = new FormData();
      const filename = uri.split('/').pop() || 'document.jpg';
      const match = /\.(\w+)$/.exec(filename);
      const type = match ? `image/${match[1]}` : 'image/jpeg';

      // Handle React Native Web file uploads properly
      if (Platform.OS === 'web' && uri.startsWith('blob:')) {
        // For web, we need to fetch the blob and create a proper File object
        const response = await fetch(uri);
        const blob = await response.blob();
        const file = new File([blob], filename, { type });
        formData.append('document', file);
      } else {
        // For mobile platforms, use the existing approach
        formData.append('document', {
          uri,
          name: filename,
          type
        } as any);
      }

      const response = await fetch(`${BACKEND_URL}/api/verification/upload`, {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json',
        },
      });

      const result = await response.json();
      if (result.success) {
        return result.filePath;
      } else {
        throw new Error(result.message || 'Failed to upload document');
      }
    } catch (error) {
      console.error('Error uploading document:', error);
      Alert.alert('Error', 'Failed to upload document');
      return null;
    } finally {
      setUploading(false);
    }
  };

  // Submit verification documents
  const submitVerification = async () => {
    if (!user?.id) {
      Alert.alert('Error', 'User not logged in');
      return;
    }

    if (!idPicture && !idSelfie && !billingDocument) {
      Alert.alert('Error', 'Please upload at least one document');
      return;
    }

    // Check if user can submit verification
    if (isUserVerified) {
      Alert.alert('Error', 'Your account is already verified');
      return;
    }

    if (hasPendingVerification) {
      Alert.alert('Error', 'Your verification is already pending review. Please wait for admin approval.');
      return;
    }

    try {
      setUploading(true);

      // Upload documents if they are local files
      let idPicturePath = idPicture;
      let idSelfiePath = idSelfie;
      let billingDocumentPath = billingDocument;

      // Check if the paths are local URIs (not already uploaded server paths)
      // Handle both file:// URIs (mobile) and blob: URIs (web)
      if (idPicture && (idPicture.startsWith('file://') || idPicture.startsWith('blob:'))) {
        idPicturePath = await uploadDocument(idPicture);
        if (!idPicturePath) throw new Error('Failed to upload ID picture');
      }

      if (idSelfie && (idSelfie.startsWith('file://') || idSelfie.startsWith('blob:'))) {
        idSelfiePath = await uploadDocument(idSelfie);
        if (!idSelfiePath) throw new Error('Failed to upload selfie');
      }

      if (billingDocument && (billingDocument.startsWith('file://') || billingDocument.startsWith('blob:'))) {
        billingDocumentPath = await uploadDocument(billingDocument);
        if (!billingDocumentPath) throw new Error('Failed to upload billing document');
      }

      const result = await verificationService.submitVerification({
        userId: user.id,
        idPicture: idPicturePath || undefined,
        idSelfie: idSelfiePath || undefined,
        billingDocument: billingDocumentPath || undefined,
      });

      if (result.success) {
        Alert.alert('Success', 'Verification documents submitted successfully');
        // Refresh verification status
        const statusResult = await verificationService.getVerificationStatus(user.id);
        if (statusResult.success && statusResult.data) {
          setVerificationStatus(statusResult.data);
          // Clear local file URIs
          if (idPicture && (idPicture.startsWith('file://') || idPicture.startsWith('blob:'))) setIdPicture(null);
          if (idSelfie && (idSelfie.startsWith('file://') || idSelfie.startsWith('blob:'))) setIdSelfie(null);
          if (billingDocument && (billingDocument.startsWith('file://') || billingDocument.startsWith('blob:'))) setBillingDocument(null);
        }
      } else {
        Alert.alert('Error', result.message || 'Failed to submit verification');
      }
    } catch (error) {
      console.error('Error submitting verification:', error);
      Alert.alert('Error', 'Failed to submit verification');
    } finally {
      setUploading(false);
    }
  };

  if (!user) {
    return (
      <View style={styles.container}>
        <Text>Loading...</Text>
        <Text>Loading...</Text>
      </View>
    );
  }

  // Check if user is verified
  const isUserVerified = user.isVerified || (verificationStatus?.is_verified ?? false);
  const hasPendingVerification = verificationStatus?.status === 'pending';
  const wasRejected = verificationStatus?.status === 'rejected';

  // Detect encrypted-looking values and mask them while refreshing
  const looksEncrypted = (val: string) => val && val.length > 60 && /^[A-Za-z0-9+/=:]+$/.test(val);
  const displayPhone = looksEncrypted(user.phone) ? 'Loading...' : (user.phone || 'Not provided');
  const displayAddress = looksEncrypted(user.address) ? 'Loading...' : (user.address || 'Not provided');

  // Auto-refresh if encrypted data detected
  useEffect(() => {
    if (user?.id && (looksEncrypted(user.phone) || looksEncrypted(user.address))) {
      console.log('⚠️ Encrypted data detected in profile display, auto-refreshing...');
      refreshProfile(user.id);
    }
  }, [user?.phone, user?.address]);

  // Users can only submit verification once, unless they were rejected
  const canSubmitVerification = !isUserVerified && !hasPendingVerification;

  return (
    <ScrollView
      style={profileStyles.scrollView}
      contentContainerStyle={{ paddingBottom: 40 }}
      keyboardShouldPersistTaps="handled"
      showsVerticalScrollIndicator={false}
      bounces={true}
      scrollEnabled={true}
      nestedScrollEnabled={true}
    >
      {/* Header */}
      <View style={profileStyles.header}>
        <TouchableOpacity
          onPress={() => router.push('/')}
          style={profileStyles.backButton}
        >
          <Ionicons name="chevron-back" size={24} color={COLORS.primary} />
        </TouchableOpacity>
        <View style={profileStyles.headerCenter}>
          <Text style={profileStyles.headerTitle}>
            <Text style={{ color: COLORS.primary }}>Alert</Text>
            <Text style={{ color: '#000' }}>Davao</Text>
          </Text>
          <Text style={profileStyles.headerSubtitle}>Profile</Text>
        </View>
        <View style={{ width: 40 }} />
      </View>

      {/* Profile Card */}
      <View style={profileStyles.profileCard}>
        <View style={profileStyles.profileImageContainer}>
          <Image
            source={{ uri: user.profileImage || 'https://i.pinimg.com/736x/3c/ae/07/3cae079ca0b9e55ec6bfc1b358c9b1e2.jpg' }}
            style={profileStyles.profileImage}
          />
          <View style={[
            profileStyles.verificationBadge,
            { backgroundColor: isUserVerified ? COLORS.success : COLORS.warning }
          ]}>
            <Ionicons
              name={isUserVerified ? 'checkmark-circle' : 'alert-circle'}
              size={14}
              color={COLORS.white}
            />
            <Text style={profileStyles.verificationBadgeText}>
              {isUserVerified ? 'Verified' : 'Unverified'}
            </Text>
          </View>
        </View>

        {flagStatus && (
          <View style={profileStyles.flagBadgeContainer}>
            <FlagStatusBadge
              flagData={flagStatus}
              size="medium"
              showLabel={false}
            />
          </View>
        )}

        <Text style={profileStyles.userName}>{user.firstName} {user.lastName}</Text>

        <View style={profileStyles.infoGrid}>
          <View style={profileStyles.infoItem}>
            <View style={[profileStyles.infoIcon, { backgroundColor: '#e0f2fe' }]}>
              <Ionicons name="mail" size={18} color={COLORS.primary} />
            </View>
            <View style={profileStyles.infoTextContainer}>
              <Text style={profileStyles.infoLabel}>Email</Text>
              <Text style={profileStyles.infoValue}>{user.email}</Text>
            </View>
          </View>

          <View style={profileStyles.infoItem}>
            <View style={[profileStyles.infoIcon, { backgroundColor: '#dcfce7' }]}>
              <Ionicons name="call" size={18} color={COLORS.success} />
            </View>
            <View style={profileStyles.infoTextContainer}>
              <Text style={profileStyles.infoLabel}>Phone</Text>
              <Text style={profileStyles.infoValue}>{displayPhone}</Text>
            </View>
          </View>

          <View style={profileStyles.infoItem}>
            <View style={[profileStyles.infoIcon, { backgroundColor: '#fef3c7' }]}>
              <Ionicons name="location" size={18} color={COLORS.warning} />
            </View>
            <View style={profileStyles.infoTextContainer}>
              <Text style={profileStyles.infoLabel}>Address</Text>
              <Text style={profileStyles.infoValue}>{displayAddress}</Text>
            </View>
          </View>
        </View>

        {user.updatedAt && (
          <View style={profileStyles.lastUpdated}>
            <Ionicons name="time-outline" size={14} color={COLORS.textMuted} />
            <Text style={profileStyles.lastUpdatedText}>
              Last updated: {new Date(user.updatedAt).toLocaleDateString('en-US', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
              })}
            </Text>
          </View>
        )}
      </View>

      {/* Action Buttons */}
      <View style={profileStyles.actionsContainer}>
        <TouchableOpacity
          style={profileStyles.actionButton}
          onPress={() => router.push('/edit-profile')}
        >
          <View style={[profileStyles.actionIcon, { backgroundColor: '#e0f2fe' }]}>
            <Ionicons name="create-outline" size={20} color={COLORS.primary} />
          </View>
          <View style={profileStyles.actionTextContainer}>
            <Text style={profileStyles.actionTitle}>Edit Profile</Text>
            <Text style={profileStyles.actionSubtitle}>Update your personal information</Text>
          </View>
          <Ionicons name="chevron-forward" size={20} color={COLORS.textMuted} />
        </TouchableOpacity>

        <TouchableOpacity
          style={profileStyles.actionButton}
          onPress={() => setShowPasswordModal(true)}
        >
          <View style={[profileStyles.actionIcon, { backgroundColor: '#fef3c7' }]}>
            <Ionicons name="lock-closed-outline" size={20} color={COLORS.warning} />
          </View>
          <View style={profileStyles.actionTextContainer}>
            <Text style={profileStyles.actionTitle}>Change Password</Text>
            <Text style={profileStyles.actionSubtitle}>Update your account password</Text>
          </View>
          <Ionicons name="chevron-forward" size={20} color={COLORS.textMuted} />
        </TouchableOpacity>
      </View>

      {/* Verification Section */}
      <View style={profileStyles.verificationContainer}>
        <Text style={profileStyles.sectionTitle}>Account Verification</Text>

        {loadingVerification ? (
          <View style={profileStyles.loadingContainer}>
            <Text style={profileStyles.loadingText}>Loading verification status...</Text>
          </View>
        ) : (
          <>
            {/* Verified Users - Show status only */}
            {isUserVerified && (
              <View style={profileStyles.statusCard}>
                <View style={[profileStyles.statusIconContainer, { backgroundColor: '#dcfce7' }]}>
                  <Ionicons name="checkmark-circle" size={32} color={COLORS.success} />
                </View>
                <Text style={[profileStyles.statusTitle, { color: '#065f46' }]}>
                  Account Verified
                </Text>
                <Text style={profileStyles.statusDescription}>
                  Your identity has been verified by an administrator.
                </Text>
                {verificationStatus?.updated_at && (
                  <Text style={profileStyles.statusDate}>
                    Verified on: {new Date(verificationStatus.updated_at).toLocaleDateString('en-US', {
                      year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila'
                    })}
                  </Text>
                )}
              </View>
            )}

            {/* Pending Verification */}
            {hasPendingVerification && (
              <View style={profileStyles.statusCard}>
                <View style={[profileStyles.statusIconContainer, { backgroundColor: '#fef3c7' }]}>
                  <Ionicons name="time" size={32} color={COLORS.warning} />
                </View>
                <Text style={[profileStyles.statusTitle, { color: '#92400e' }]}>
                  Verification Pending
                </Text>
                <Text style={profileStyles.statusDescription}>
                  Your verification request is being reviewed. Please wait for admin approval.
                </Text>
              </View>
            )}

            {/* Rejected or Not Verified - Show upload form */}
            {!isUserVerified && !hasPendingVerification && (
              <>
                <View style={profileStyles.uploadSection}>
                  {wasRejected && (
                    <View style={profileStyles.rejectedBanner}>
                      <Ionicons name="close-circle" size={20} color={COLORS.accent} />
                      <View style={{ flex: 1 }}>
                        <Text style={profileStyles.rejectedText}>
                          Your previous request was rejected. Please upload new documents.
                        </Text>
                        {verificationStatus?.rejection_reason ? (
                          <Text style={[profileStyles.rejectedText, { marginTop: 4, fontStyle: 'italic', opacity: 0.85 }]}>
                            Reason: {verificationStatus.rejection_reason}
                          </Text>
                        ) : null}
                      </View>
                    </View>
                  )}

                  <Text style={profileStyles.uploadInstructions}>
                    Upload at least one document to verify your identity
                  </Text>

                  {/* Upload Buttons */}
                  <TouchableOpacity
                    style={[profileStyles.uploadButton, idPicture && profileStyles.uploadButtonSuccess]}
                    disabled={uploading}
                    onPress={() => pickImage(setIdPicture)}
                  >
                    <View style={profileStyles.uploadButtonContent}>
                      <Ionicons
                        name={idPicture ? 'checkmark-circle' : 'id-card-outline'}
                        size={24}
                        color={idPicture ? COLORS.success : COLORS.primary}
                      />
                      <View style={profileStyles.uploadButtonTextContainer}>
                        <Text style={profileStyles.uploadButtonTitle}>ID Picture</Text>
                        <Text style={profileStyles.uploadButtonSubtitle}>
                          {idPicture ? 'Document uploaded ✓' : 'Upload a clear photo of your ID'}
                        </Text>
                      </View>
                    </View>
                    <Ionicons name="cloud-upload-outline" size={20} color={COLORS.textMuted} />
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[profileStyles.uploadButton, idSelfie && profileStyles.uploadButtonSuccess]}
                    disabled={uploading}
                    onPress={() => pickImage(setIdSelfie)}
                  >
                    <View style={profileStyles.uploadButtonContent}>
                      <Ionicons
                        name={idSelfie ? 'checkmark-circle' : 'camera-outline'}
                        size={24}
                        color={idSelfie ? COLORS.success : COLORS.primary}
                      />
                      <View style={profileStyles.uploadButtonTextContainer}>
                        <Text style={profileStyles.uploadButtonTitle}>Selfie with ID</Text>
                        <Text style={profileStyles.uploadButtonSubtitle}>
                          {idSelfie ? 'Document uploaded ✓' : 'Take a selfie holding your ID'}
                        </Text>
                      </View>
                    </View>
                    <Ionicons name="cloud-upload-outline" size={20} color={COLORS.textMuted} />
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[profileStyles.uploadButton, billingDocument && profileStyles.uploadButtonSuccess]}
                    disabled={uploading}
                    onPress={() => pickImage(setBillingDocument)}
                  >
                    <View style={profileStyles.uploadButtonContent}>
                      <Ionicons
                        name={billingDocument ? 'checkmark-circle' : 'document-text-outline'}
                        size={24}
                        color={billingDocument ? COLORS.success : COLORS.primary}
                      />
                      <View style={profileStyles.uploadButtonTextContainer}>
                        <Text style={profileStyles.uploadButtonTitle}>Billing Document</Text>
                        <Text style={profileStyles.uploadButtonSubtitle}>
                          {billingDocument ? 'Document uploaded ✓' : 'Upload a utility bill or similar'}
                        </Text>
                      </View>
                    </View>
                    <Ionicons name="cloud-upload-outline" size={20} color={COLORS.textMuted} />
                  </TouchableOpacity>

                  <TouchableOpacity
                    style={[
                      profileStyles.submitButton,
                      ((!idPicture && !idSelfie && !billingDocument) || uploading) && profileStyles.submitButtonDisabled
                    ]}
                    disabled={(!idPicture && !idSelfie && !billingDocument) || uploading}
                    onPress={submitVerification}
                  >
                    <Ionicons name="shield-checkmark" size={20} color={COLORS.white} />
                    <Text style={profileStyles.submitButtonText}>
                      {uploading ? 'Submitting...' : wasRejected ? 'Resubmit Verification' : 'Submit for Verification'}
                    </Text>
                  </TouchableOpacity>
                </View>
              </>
            )}
          </>
        )}
      </View>

      <ChangePasswordModal
        visible={showPasswordModal}
        onClose={() => setShowPasswordModal(false)}
      />
    </ScrollView>
  );
}

const profileStyles = StyleSheet.create({
  // Main Container
  scrollView: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  // Header
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    paddingTop: Platform.OS === 'android' ? 40 : 50,
    backgroundColor: COLORS.white,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: COLORS.background,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerCenter: {
    flex: 1,
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
  },
  headerSubtitle: {
    fontSize: 14,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  // Profile Card
  profileCard: {
    backgroundColor: COLORS.white,
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 16,
    padding: 20,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: COLORS.border,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
  profileImageContainer: {
    position: 'relative',
    marginBottom: 12,
  },
  profileImage: {
    width: 100,
    height: 100,
    borderRadius: 50,
    borderWidth: 3,
    borderColor: COLORS.primary,
  },
  verificationBadge: {
    position: 'absolute',
    bottom: 0,
    right: -10,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    gap: 4,
  },
  verificationBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: COLORS.white,
  },
  flagBadgeContainer: {
    marginBottom: 8,
  },
  userName: {
    fontSize: 22,
    fontWeight: '700',
    color: COLORS.text,
    marginBottom: 16,
  },
  // Info Grid
  infoGrid: {
    width: '100%',
    gap: 12,
  },
  infoItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.background,
    padding: 12,
    borderRadius: 12,
  },
  infoIcon: {
    width: 40,
    height: 40,
    borderRadius: 10,
    justifyContent: 'center',
    alignItems: 'center',
  },
  infoTextContainer: {
    flex: 1,
    marginLeft: 12,
  },
  infoLabel: {
    fontSize: 12,
    color: COLORS.textMuted,
    marginBottom: 2,
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '500',
    color: COLORS.text,
  },
  lastUpdated: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 16,
    gap: 6,
  },
  lastUpdatedText: {
    fontSize: 12,
    color: COLORS.textMuted,
    fontStyle: 'italic',
  },
  // Actions Container
  actionsContainer: {
    marginHorizontal: 16,
    marginTop: 16,
    gap: 10,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.white,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  actionIcon: {
    width: 44,
    height: 44,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  actionTextContainer: {
    flex: 1,
    marginLeft: 14,
  },
  actionTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: COLORS.text,
  },
  actionSubtitle: {
    fontSize: 12,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  // Verification Container
  verificationContainer: {
    marginHorizontal: 16,
    marginTop: 20,
    backgroundColor: COLORS.white,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: COLORS.text,
    marginBottom: 16,
  },
  loadingContainer: {
    padding: 20,
    alignItems: 'center',
  },
  loadingText: {
    fontSize: 14,
    color: COLORS.textSecondary,
  },
  // Status Card
  statusCard: {
    alignItems: 'center',
    padding: 20,
    borderRadius: 12,
    backgroundColor: COLORS.background,
  },
  statusIconContainer: {
    width: 64,
    height: 64,
    borderRadius: 32,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  statusTitle: {
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 8,
  },
  statusDescription: {
    fontSize: 14,
    color: COLORS.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
  },
  statusDate: {
    fontSize: 12,
    color: COLORS.textMuted,
    marginTop: 12,
  },
  // Upload Section
  uploadSection: {
    gap: 12,
  },
  rejectedBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fee2e2',
    padding: 12,
    borderRadius: 10,
    gap: 10,
    marginBottom: 4,
  },
  rejectedText: {
    flex: 1,
    fontSize: 13,
    color: COLORS.accent,
    lineHeight: 18,
  },
  uploadInstructions: {
    fontSize: 14,
    color: COLORS.textSecondary,
    textAlign: 'center',
    marginBottom: 8,
  },
  uploadButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.background,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  uploadButtonSuccess: {
    borderColor: COLORS.success,
    backgroundColor: '#f0fdf4',
  },
  uploadButtonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  uploadButtonTextContainer: {
    marginLeft: 12,
    flex: 1,
  },
  uploadButtonTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.text,
  },
  uploadButtonSubtitle: {
    fontSize: 12,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.primary,
    padding: 16,
    borderRadius: 12,
    marginTop: 8,
    gap: 10,
  },
  submitButtonDisabled: {
    backgroundColor: COLORS.textMuted,
    opacity: 0.6,
  },
  submitButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.white,
  },
});