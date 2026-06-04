import React, { useState, useEffect } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    Image,
    Alert,
    ActivityIndicator,
} from 'react-native';
import { useRouter } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { spacing, fontSize, containerPadding, borderRadius, isTablet } from '../../utils/responsive';
import { API_URL, BACKEND_URL } from '../../config/backend';
import { stopLocationTracking } from '../../services/patrolLocationService';
import { stopServerWarmup } from '../../utils/serverWarmup';
import ConfirmDialog from '../../components/ConfirmDialog';

const COLORS = {
    primary: '#1D3557',
    primaryDark: '#152741',
    primaryLight: '#2a4a7a',
    accent: '#E63946',
    white: '#ffffff',
    background: '#f5f7fa',
    cardBg: '#ffffff',
    textPrimary: '#1D3557',
    textSecondary: '#6b7280',
    textMuted: '#9ca3af',
    border: '#e5e7eb',
    success: '#10b981',
    warning: '#f59e0b',
};

interface UserData {
    id: number;
    firstname: string;
    lastname: string;
    email: string;
    contact: string;
    profile_image: string;
    assigned_station_id: number;
    badge_number: string;
    user_role: string;
    is_on_duty: boolean;
    is_verified: boolean;
    created_at: string;
}

interface StationInfo {
    station_name: string;
    station_address: string;
}

export default function PatrolProfile() {
    const router = useRouter();
    const [userData, setUserData] = useState<UserData | null>(null);
    const [stationInfo, setStationInfo] = useState<StationInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [showLogoutDialog, setShowLogoutDialog] = useState(false);

    useEffect(() => {
        loadUserData();
    }, []);

    const loadUserData = async () => {
        try {
            const stored = await AsyncStorage.getItem('userData');
            if (stored) {
                const user = JSON.parse(stored);
                setUserData(user);

                // Fetch station info if user has assigned station
                if (user.assigned_station_id) {
                    fetchStationInfo(user.assigned_station_id);
                }

                // Fetch fresh data from API to get decrypted contact info
                if (user.id) {
                    try {
                        const response = await fetch(`${API_URL}/users/${user.id}`);
                        const data = await response.json();
                        if (data.success && data.data) {
                            const freshStationId = data.data.assigned_station_id || data.data.station_id;
                            const freshUser = {
                                ...user,
                                contact: data.data.contact || user.contact,
                                phone: data.data.contact || user.contact,
                                email: data.data.email || user.email,
                                firstname: data.data.firstname || user.firstname,
                                lastname: data.data.lastname || user.lastname,
                                address: data.data.address || user.address,
                                assigned_station_id: freshStationId || user.assigned_station_id,
                            };
                            setUserData(freshUser);
                            // Update AsyncStorage with decrypted data so it persists
                            AsyncStorage.setItem('userData', JSON.stringify(freshUser)).catch(() => { });

                            // If fresh data has station info from API, use it directly
                            if (data.data.station && data.data.station.station_name) {
                                setStationInfo({
                                    station_name: data.data.station.station_name,
                                    station_address: data.data.station.address || '',
                                });
                            } else if (freshStationId) {
                                fetchStationInfo(freshStationId);
                            }
                        }
                    } catch (apiError) {
                        console.warn('Could not fetch fresh profile data:', apiError);
                    }
                }
            }
        } catch (error) {
            console.error('Error loading user data:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchStationInfo = async (stationId: number) => {
        try {
            const response = await fetch(`${API_URL}/police-stations/${stationId}`);
            const data = await response.json();
            if (data.success && data.data) {
                setStationInfo({
                    station_name: data.data.station_name,
                    station_address: data.data.address || '',
                });
            }
        } catch (error) {
            console.error('Error fetching station info:', error);
        }
    };

    const handleLogout = async () => {
        setShowLogoutDialog(false);
        try {
            stopLocationTracking();
            stopServerWarmup();

            // Stop inactivity manager
            const { inactivityManager } = await import('../../services/inactivityManager');
            inactivityManager.stop();

            if (userData?.id || userData?.email) {
                // Clear patrol-specific server state
                fetch(`${BACKEND_URL}/patrol-logout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'ngrok-skip-browser-warning': 'true'
                    },
                    body: JSON.stringify({
                        odId: userData?.id,
                        odEmail: userData?.email
                    })
                }).catch(err => console.warn('Server patrol logout failed:', err));

                // Also clear push token from users_public
                fetch(`${BACKEND_URL}/logout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'ngrok-skip-browser-warning': 'true'
                    },
                    body: JSON.stringify({
                        userId: userData?.id,
                        email: userData?.email
                    })
                }).catch(err => console.warn('Server logout failed:', err));
            }

            // Clear local storage thoroughly to prevent sticky Google sessions
            await AsyncStorage.clear();

            // Navigate to login screen
            router.replace('/(tabs)/login');
        } catch (error) {
            console.error('Error logging out:', error);
            router.replace('/(tabs)/login');
        }
    };

    const getInitials = (firstName: string, lastName: string) => {
        return `${firstName?.[0] || ''}${lastName?.[0] || ''}`.toUpperCase() || 'PO';
    };

    const formatDate = (dateStr: string) => {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    if (loading) {
        return (
            <View style={styles.loadingContainer}>
                <ActivityIndicator size="large" color={COLORS.primary} />
            </View>
        );
    }

    return (
        <View style={styles.container}>
            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity
                    style={styles.backButton}
                    onPress={() => router.back()}
                >
                    <Ionicons name="arrow-back" size={24} color={COLORS.primary} />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>Profile</Text>
                <View style={{ width: 40 }} />
            </View>

            <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
                {/* Profile Card */}
                <View style={styles.profileCard}>
                    <View style={styles.avatarSection}>
                        {userData?.profile_image ? (
                            <Image
                                source={{ uri: userData.profile_image }}
                                style={styles.avatar}
                            />
                        ) : (
                            <View style={styles.avatarPlaceholder}>
                                <Text style={styles.avatarText}>
                                    {getInitials(userData?.firstname || '', userData?.lastname || '')}
                                </Text>
                            </View>
                        )}
                        <View style={[
                            styles.statusBadge,
                            { backgroundColor: userData?.is_on_duty ? COLORS.success : COLORS.textMuted }
                        ]}>
                            <Text style={styles.statusBadgeText}>
                                {userData?.is_on_duty ? 'On Duty' : 'Off Duty'}
                            </Text>
                        </View>
                    </View>

                    <Text style={styles.userName}>
                        {userData?.firstname} {userData?.lastname}
                    </Text>
                    <Text style={styles.userRole}>Patrol Officer</Text>

                    {userData?.badge_number && (
                        <View style={styles.badgeRow}>
                            <Ionicons name="shield-checkmark" size={16} color={COLORS.primary} />
                            <Text style={styles.badgeNumber}>Badge #{userData.badge_number}</Text>
                        </View>
                    )}
                </View>

                {/* Info Section */}
                <View style={styles.section}>
                    <Text style={styles.sectionTitle}>Contact Information</Text>

                    <View style={styles.infoCard}>
                        <View style={styles.infoRow}>
                            <View style={styles.infoIcon}>
                                <Ionicons name="mail-outline" size={20} color={COLORS.primary} />
                            </View>
                            <View style={styles.infoContent}>
                                <Text style={styles.infoLabel}>Email</Text>
                                <Text style={styles.infoValue}>{userData?.email || 'Not set'}</Text>
                            </View>
                        </View>

                        <View style={styles.infoDivider} />

                        <View style={styles.infoRow}>
                            <View style={styles.infoIcon}>
                                <Ionicons name="call-outline" size={20} color={COLORS.primary} />
                            </View>
                            <View style={styles.infoContent}>
                                <Text style={styles.infoLabel}>Phone</Text>
                                <Text style={styles.infoValue}>{userData?.contact || 'Not set'}</Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* Station Section */}
                <View style={styles.section}>
                    <Text style={styles.sectionTitle}>Assigned Station</Text>

                    <View style={styles.infoCard}>
                        <View style={styles.infoRow}>
                            <View style={styles.infoIcon}>
                                <Ionicons name="business-outline" size={20} color={COLORS.primary} />
                            </View>
                            <View style={styles.infoContent}>
                                <Text style={styles.infoLabel}>Station</Text>
                                <Text style={styles.infoValue}>
                                    {stationInfo?.station_name || `Station #${userData?.assigned_station_id || 'N/A'}`}
                                </Text>
                            </View>
                        </View>

                        {stationInfo?.station_address && (
                            <>
                                <View style={styles.infoDivider} />
                                <View style={styles.infoRow}>
                                    <View style={styles.infoIcon}>
                                        <Ionicons name="location-outline" size={20} color={COLORS.primary} />
                                    </View>
                                    <View style={styles.infoContent}>
                                        <Text style={styles.infoLabel}>Address</Text>
                                        <Text style={styles.infoValue}>{stationInfo.station_address}</Text>
                                    </View>
                                </View>
                            </>
                        )}
                    </View>
                </View>

                {/* Account Info Section */}
                <View style={styles.section}>
                    <Text style={styles.sectionTitle}>Account</Text>

                    <View style={styles.infoCard}>
                        <View style={styles.infoRow}>
                            <View style={styles.infoIcon}>
                                <Ionicons name="calendar-outline" size={20} color={COLORS.primary} />
                            </View>
                            <View style={styles.infoContent}>
                                <Text style={styles.infoLabel}>Member Since</Text>
                                <Text style={styles.infoValue}>{formatDate(userData?.created_at || '')}</Text>
                            </View>
                        </View>

                        <View style={styles.infoDivider} />

                        <View style={styles.infoRow}>
                            <View style={styles.infoIcon}>
                                <Ionicons name="checkmark-circle-outline" size={20} color={userData?.is_verified ? COLORS.success : COLORS.warning} />
                            </View>
                            <View style={styles.infoContent}>
                                <Text style={styles.infoLabel}>Verification Status</Text>
                                <Text style={[styles.infoValue, { color: userData?.is_verified ? COLORS.success : COLORS.warning }]}>
                                    {userData?.is_verified ? 'Verified' : 'Pending Verification'}
                                </Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* Logout Button */}
                <TouchableOpacity
                    style={styles.logoutButton}
                    onPress={() => setShowLogoutDialog(true)}
                >
                    <Ionicons name="log-out-outline" size={20} color={COLORS.accent} />
                    <Text style={styles.logoutButtonText}>Logout</Text>
                </TouchableOpacity>

                <View style={{ height: 40 }} />
            </ScrollView>

            <ConfirmDialog
                visible={showLogoutDialog}
                title="Confirm Logout"
                message="Are you sure you want to logout? Your location tracking will stop."
                confirmText="Logout"
                onCancel={() => setShowLogoutDialog(false)}
                onConfirm={handleLogout}
            />
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: COLORS.background,
    },
    loadingContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: COLORS.background,
    },
    header: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingHorizontal: containerPadding.horizontal,
        paddingTop: containerPadding.vertical + 10,
        paddingBottom: spacing.md,
        backgroundColor: COLORS.white,
        borderBottomWidth: 1,
        borderBottomColor: COLORS.border,
    },
    backButton: {
        width: 40,
        height: 40,
        justifyContent: 'center',
        alignItems: 'center',
    },
    headerTitle: {
        fontSize: fontSize.lg,
        fontWeight: 'bold',
        color: COLORS.textPrimary,
    },
    content: {
        flex: 1,
        padding: containerPadding.horizontal,
    },
    profileCard: {
        backgroundColor: COLORS.white,
        borderRadius: borderRadius.lg,
        padding: spacing.xl,
        alignItems: 'center',
        marginTop: spacing.lg,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 8,
        elevation: 2,
    },
    avatarSection: {
        position: 'relative',
        marginBottom: spacing.md,
    },
    avatar: {
        width: isTablet ? 120 : 100,
        height: isTablet ? 120 : 100,
        borderRadius: isTablet ? 60 : 50,
    },
    avatarPlaceholder: {
        width: isTablet ? 120 : 100,
        height: isTablet ? 120 : 100,
        borderRadius: isTablet ? 60 : 50,
        backgroundColor: COLORS.primary,
        justifyContent: 'center',
        alignItems: 'center',
    },
    avatarText: {
        fontSize: fontSize.xxl,
        fontWeight: 'bold',
        color: COLORS.white,
    },
    statusBadge: {
        position: 'absolute',
        bottom: 0,
        right: -10,
        paddingHorizontal: spacing.sm,
        paddingVertical: spacing.xs,
        borderRadius: borderRadius.md,
    },
    statusBadgeText: {
        fontSize: fontSize.xs,
        fontWeight: '600',
        color: COLORS.white,
    },
    userName: {
        fontSize: fontSize.xl,
        fontWeight: 'bold',
        color: COLORS.textPrimary,
        marginBottom: spacing.xs,
    },
    userRole: {
        fontSize: fontSize.md,
        color: COLORS.textSecondary,
        marginBottom: spacing.sm,
    },
    badgeRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.xs,
    },
    badgeNumber: {
        fontSize: fontSize.sm,
        color: COLORS.primary,
        fontWeight: '500',
    },
    section: {
        marginTop: spacing.xl,
    },
    sectionTitle: {
        fontSize: fontSize.md,
        fontWeight: '600',
        color: COLORS.textSecondary,
        marginBottom: spacing.md,
        paddingLeft: spacing.xs,
    },
    infoCard: {
        backgroundColor: COLORS.white,
        borderRadius: borderRadius.lg,
        padding: spacing.md,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 8,
        elevation: 2,
    },
    infoRow: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: spacing.sm,
    },
    infoIcon: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: `${COLORS.primary}10`,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: spacing.md,
    },
    infoContent: {
        flex: 1,
    },
    infoLabel: {
        fontSize: fontSize.sm,
        color: COLORS.textMuted,
        marginBottom: 2,
    },
    infoValue: {
        fontSize: fontSize.md,
        color: COLORS.textPrimary,
        fontWeight: '500',
    },
    infoDivider: {
        height: 1,
        backgroundColor: COLORS.border,
        marginLeft: 56,
    },
    logoutButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        gap: spacing.sm,
        backgroundColor: COLORS.white,
        borderRadius: borderRadius.lg,
        padding: spacing.lg,
        marginTop: spacing.xl,
        borderWidth: 1,
        borderColor: COLORS.accent,
    },
    logoutButtonText: {
        fontSize: fontSize.md,
        fontWeight: '600',
        color: COLORS.accent,
    },
});
