import React, { useState, useEffect, useCallback } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    RefreshControl,
    Dimensions,
    Alert,
    Modal,
    Image,
    Linking,
} from 'react-native';
import { useRouter, useFocusEffect } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { spacing, fontSize, containerPadding, borderRadius, isTablet } from '../../utils/responsive';
import { API_URL, BACKEND_URL } from '../../config/backend';
import { stopServerWarmup } from '../../utils/serverWarmup';
import { onDataRefresh } from '../../services/sseService';
import ConfirmDialog from '../../components/ConfirmDialog';
import { notificationService, Notification } from '../../services/notificationService';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

// App color palette - matching AlertDavao theme
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

export default function PatrolDashboard() {
    const router = useRouter();
    const [userName, setUserName] = useState('Officer');
    const [userId, setUserId] = useState<string | null>(null);
    const [stationId, setStationId] = useState<number>(0);
    const [loading, setLoading] = useState(false);
    const [unreadNotifications, setUnreadNotifications] = useState(0);
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [showNotificationsModal, setShowNotificationsModal] = useState(false);
    const [showLogoutDialog, setShowLogoutDialog] = useState(false);
    const [pendingDispatchCount, setPendingDispatchCount] = useState(0);
    const [activeDispatchCount, setActiveDispatchCount] = useState(0);
    const [activeTab, setActiveTab] = useState('home');
    const [announcements, setAnnouncements] = useState<any[]>([]);

    const [selectedAnnouncement, setSelectedAnnouncement] = useState<any>(null);
    const [showAnnouncementModal, setShowAnnouncementModal] = useState(false);
    const [showAllAnnouncements, setShowAllAnnouncements] = useState(false);
    const [allAnnouncements, setAllAnnouncements] = useState<any[]>([]);

    useEffect(() => {
        loadUserData();
    }, []);

    // Ensure push token is registered/saved for patrol accounts (needed for AdminSide dispatch)
    useEffect(() => {
        if (!userId) return;

        (async () => {
            try {
                const { initializePushNotifications } = await import('../../services/pushNotificationService');
                const numericUserId = parseInt(userId || '0', 10);
                if (numericUserId > 0) {
                    await initializePushNotifications(numericUserId);
                }
            } catch (error) {
                console.warn('⚠️ Push notification init skipped/failed:', error);
            }
        })();
    }, [userId]);

    // Refresh data when screen comes into focus
    useFocusEffect(
        useCallback(() => {
            if (userId) {
                loadDispatchCounts();
            }
        }, [userId, stationId])
    );

    // Auto-refresh dispatch counts every 2 seconds (silent)
    useEffect(() => {
        if (!userId) return;

        const interval = setInterval(() => {
            loadDispatchCounts();
        }, 2000);

        return () => clearInterval(interval);
    }, [userId, stationId]);

    // SSE-triggered immediate refresh
    useEffect(() => {
        if (!userId) return;
        const unsub = onDataRefresh(() => {
            loadDispatchCounts();
            fetchAnnouncements();
        });
        return unsub;
    }, [userId, stationId]);

    const loadUserData = async () => {
        try {
            const stored = await AsyncStorage.getItem('userData');
            if (!stored) {
                setUserName('Officer');
                return;
            }
            const user = JSON.parse(stored);
            const first = user?.firstname || user?.firstName || '';
            const last = user?.lastname || user?.lastName || '';
            const full = `${first} ${last}`.trim();
            setUserName(full || user?.email || 'Officer');
            setUserId(user?.id?.toString() || user?.userId?.toString());
            setStationId(user?.assigned_station_id || user?.stationId || 0);
        } catch {
            setUserName('Officer');
        }
    };

    const loadDispatchCounts = async () => {
        if (!userId) return;
        try {
            // Load pending dispatches count (includes dispatches directly assigned to this officer)
            const pendingRes = await fetch(
                `${API_URL}/dispatch/station/${stationId}/pending?userId=${userId}`,
                { headers: { 'X-User-Id': userId } }
            );
            const pendingData = await pendingRes.json();
            if (pendingData.success) {
                setPendingDispatchCount(pendingData.data?.length || 0);
            }

            // Load my active dispatches count
            const myRes = await fetch(
                `${API_URL}/patrol/dispatches?userId=${userId}`,
                { headers: { 'X-User-Id': userId } }
            );
            const myData = await myRes.json();
            if (myData.success) {
                setActiveDispatchCount(myData.data?.length || 0);
            }
        } catch (error) {
            console.error('Error loading dispatch counts:', error);
        }
    };

    // Fetch announcements from API
    const fetchAnnouncements = async () => {
        try {
            const response = await fetch(`${API_URL}/announcements?limit=2`);
            const data = await response.json();
            if (data.success && data.data) {
                setAnnouncements(data.data.map((a: any) => ({
                    id: a.id,
                    title: a.title,
                    content: a.content,
                    message: a.content,
                    date: a.date,
                    author: a.author,
                    attachments: a.attachments || []
                })));
            }
        } catch (error) {
            console.error('Error fetching announcements:', error);
        }
    };

    // Fetch all announcements
    const fetchAllAnnouncements = async () => {
        try {
            const response = await fetch(`${API_URL}/announcements?limit=50`);
            const data = await response.json();
            if (data.success && data.data) {
                setAllAnnouncements(data.data.map((a: any) => ({
                    id: a.id,
                    title: a.title,
                    content: a.content,
                    message: a.content,
                    date: a.date,
                    author: a.author,
                    attachments: a.attachments || []
                })));
            }
        } catch (error) {
            console.error('Error fetching all announcements:', error);
        }
    };



    // Fetch notifications
    const fetchNotifications = async () => {
        if (!userId) return;
        try {
            const notifs = await notificationService.getUserNotifications(userId);
            setNotifications(notifs);
            const unread = notifs.filter((n: Notification) => !n.read).length;
            setUnreadNotifications(unread);
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    };

    // Mark notification as read
    const markNotificationAsRead = async (notificationId: number) => {
        if (!userId) return;
        try {
            await notificationService.markAsRead(notificationId, userId);
            // Update local state
            setNotifications(prev =>
                prev.map(n => n.id === notificationId ? { ...n, read: true } : n)
            );
            setUnreadNotifications(prev => Math.max(0, prev - 1));
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    };

    // Mark all notifications as read
    const markAllNotificationsAsRead = async () => {
        if (!userId) return;
        try {
            const unreadNotifs = notifications.filter(n => !n.read);
            await Promise.all(
                unreadNotifs.map(n => notificationService.markAsRead(n.id, userId!))
            );
            setNotifications(prev => prev.map(n => ({ ...n, read: true })));
            setUnreadNotifications(0);
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    };

    // Format time ago
    const formatTimeAgo = (dateStr: string) => {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 60) return `${diffMins} min ago`;
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        const diffDays = Math.floor(diffHours / 24);
        return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    };

    // Load data on mount
    useEffect(() => {
        fetchAnnouncements();

        // Auto-refresh every 30 seconds
        const interval = setInterval(() => {
            fetchAnnouncements();
        }, 30000);

        return () => clearInterval(interval);
    }, [stationId]);

    // Fetch notifications when userId is available
    useEffect(() => {
        if (userId) {
            fetchNotifications();
            // Auto-refresh notifications every 15 seconds
            const notifInterval = setInterval(fetchNotifications, 15000);
            return () => clearInterval(notifInterval);
        }
    }, [userId]);

    const onRefresh = () => {
        setLoading(true);
        loadDispatchCounts();
        fetchAnnouncements();
        setTimeout(() => setLoading(false), 1000);
    };

    const getInitials = (name: string) => {
        const parts = name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    };

    const handleLogout = async () => {
        setShowLogoutDialog(false);
        try {
            // Stop server warmup pings
            stopServerWarmup();

            // Stop inactivity manager
            const { inactivityManager } = await import('../../services/inactivityManager');
            inactivityManager.stop();

            // Get user data before clearing
            const userData = await AsyncStorage.getItem('userData');
            const parsedUser = userData ? JSON.parse(userData) : null;

            // Call backend to clear patrol officer session (non-blocking)
            if (parsedUser?.id || parsedUser?.email) {
                fetch(`${BACKEND_URL}/patrol-logout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'ngrok-skip-browser-warning': 'true'
                    },
                    body: JSON.stringify({
                        odId: parsedUser?.id,
                        odEmail: parsedUser?.email
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
                        userId: parsedUser?.id,
                        email: parsedUser?.email
                    })
                }).catch(err => console.warn('Server logout failed:', err));
            }

            // Clear local storage
            await AsyncStorage.multiRemove([
                'userData',
                'userToken',
                'pushToken',
                'lastNotificationCheck',
                'cachedNotifications',
                'patrolDutyStatus',
                'inactivityLogout'
            ]);

            // Dismiss patrol stack and go to login
            while (router.canGoBack()) {
                router.back();
            }
            router.replace('/(tabs)/login');
        } catch (error) {
            console.error('Error logging out:', error);
            // Dismiss patrol stack and go to login
            while (router.canGoBack()) {
                router.back();
            }
            router.replace('/(tabs)/login');
        }
    };

    // Handle announcement press
    const handleAnnouncementPress = (announcement: any) => {
        setSelectedAnnouncement(announcement);
        setShowAnnouncementModal(true);
    };

    // Handle See All announcements
    const handleSeeAllAnnouncements = () => {
        fetchAllAnnouncements();
        setShowAllAnnouncements(true);
    };

    return (
        <View style={styles.container}>
            {/* Header */}
            <View style={styles.header}>
                <View style={styles.headerLeft}>
                    <View style={styles.avatarContainer}>
                        <View style={styles.avatar}>
                            <Text style={styles.avatarText}>{getInitials(userName)}</Text>
                        </View>
                        <View style={styles.onlineIndicator} />
                    </View>
                    <View style={styles.welcomeText}>
                        <Text style={styles.welcomeLabel}>Welcome back,</Text>
                        <Text style={styles.userName} numberOfLines={1}>{userName}</Text>
                    </View>
                </View>
                <View style={styles.headerRight}>
                    <TouchableOpacity
                        style={styles.notificationButton}
                        onPress={() => setShowNotificationsModal(true)}
                    >
                        <Ionicons name="notifications-outline" size={26} color={COLORS.primary} />
                        {unreadNotifications > 0 && (
                            <View style={styles.notificationBadge}>
                                <Text style={styles.notificationBadgeText}>
                                    {unreadNotifications > 9 ? '9+' : unreadNotifications}
                                </Text>
                            </View>
                        )}
                    </TouchableOpacity>
                    <TouchableOpacity
                        style={styles.logoutButton}
                        onPress={() => setShowLogoutDialog(true)}
                    >
                        <Ionicons name="log-out-outline" size={24} color={COLORS.accent} />
                    </TouchableOpacity>
                </View>
            </View>

            <ScrollView
                style={styles.content}
                showsVerticalScrollIndicator={false}
                refreshControl={
                    <RefreshControl
                        refreshing={loading}
                        onRefresh={onRefresh}
                        colors={[COLORS.primary]}
                        tintColor={COLORS.primary}
                    />
                }
            >
                {/* Banner */}
                <View style={styles.bannerContainer}>
                    <View style={styles.banner}>
                        <View style={styles.bannerContent}>
                            <View style={styles.bannerTextContainer}>
                                <Text style={styles.bannerTitle}>Patrol Dashboard</Text>
                                <Text style={styles.bannerSubtitle}>
                                    Stay safe and serve the community
                                </Text>
                            </View>
                            <View style={styles.bannerIconContainer}>
                                <Ionicons name="shield-checkmark" size={48} color="rgba(255,255,255,0.3)" />
                            </View>
                        </View>
                        <View style={styles.bannerStats}>
                            <View style={styles.bannerStatItem}>
                                <Text style={styles.bannerStatValue}>{pendingDispatchCount}</Text>
                                <Text style={styles.bannerStatLabel}>Pending</Text>
                            </View>
                            <View style={styles.bannerStatDivider} />
                            <View style={styles.bannerStatItem}>
                                <Text style={styles.bannerStatValue}>{activeDispatchCount}</Text>
                                <Text style={styles.bannerStatLabel}>Active</Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* Quick Action Button - Dispatches Only */}
                <View style={styles.quickActionsContainer}>
                    <TouchableOpacity
                        style={styles.quickActionButtonFull}
                        onPress={() => router.push('/(patrol)/dispatches')}
                    >
                        <View style={[styles.quickActionIconBgLarge, { backgroundColor: '#FEE2E2' }]}>
                            <Ionicons name="alert-circle-outline" size={28} color="#DC2626" />
                            {pendingDispatchCount > 0 && (
                                <View style={styles.quickActionBadgeLarge}>
                                    <Text style={styles.quickActionBadgeText}>
                                        {pendingDispatchCount > 9 ? '9+' : pendingDispatchCount}
                                    </Text>
                                </View>
                            )}
                        </View>
                        <View style={styles.quickActionTextContainer}>
                            <Text style={styles.quickActionTextMain}>View Dispatches</Text>
                            <Text style={styles.quickActionTextSub}>
                                {pendingDispatchCount > 0
                                    ? `${pendingDispatchCount} pending dispatch${pendingDispatchCount > 1 ? 'es' : ''}`
                                    : 'No pending dispatches'}
                            </Text>
                        </View>
                        <Ionicons name="chevron-forward" size={24} color={COLORS.textMuted} />
                    </TouchableOpacity>
                </View>

                {/* Announcements Section */}
                <View style={styles.section}>
                    <View style={styles.sectionHeader}>
                        <Text style={styles.sectionTitle}>Announcements</Text>
                        <TouchableOpacity onPress={handleSeeAllAnnouncements}>
                            <Text style={styles.seeAllText}>See All</Text>
                        </TouchableOpacity>
                    </View>

                    {announcements.length === 0 ? (
                        <View style={styles.emptyCard}>
                            <Ionicons name="megaphone-outline" size={48} color={COLORS.textMuted} />
                            <Text style={styles.emptyText}>No announcements</Text>
                        </View>
                    ) : (
                        announcements.map((announcement) => (
                            <TouchableOpacity
                                key={announcement.id}
                                style={styles.announcementCard}
                                onPress={() => handleAnnouncementPress(announcement)}
                            >
                                <View style={styles.announcementIconContainer}>
                                    <Ionicons name="megaphone" size={20} color={COLORS.primary} />
                                </View>
                                <View style={styles.announcementContent}>
                                    <View style={styles.announcementHeader}>
                                        <Text style={styles.announcementTitle}>{announcement.title}</Text>
                                        <Text style={styles.announcementDate}>{announcement.date}</Text>
                                    </View>
                                    <Text style={styles.announcementMessage} numberOfLines={2}>
                                        {announcement.message}
                                    </Text>
                                </View>
                            </TouchableOpacity>
                        ))
                    )}
                </View>

                {/* Bottom Spacing */}
                <View style={{ height: 100 }} />
            </ScrollView>

            {/* Bottom Navigation */}
            <View style={styles.bottomNav}>
                <TouchableOpacity
                    style={styles.navItem}
                    onPress={() => setActiveTab('home')}
                >
                    <Ionicons name={activeTab === 'home' ? 'home' : 'home-outline'} size={24} color={activeTab === 'home' ? COLORS.primary : COLORS.textMuted} />
                    <Text style={[styles.navText, activeTab === 'home' && styles.navTextActive]}>Home</Text>
                </TouchableOpacity>
                <TouchableOpacity
                    style={styles.navItem}
                    onPress={() => router.push('/(patrol)/chat')}
                >
                    <Ionicons name="chatbubbles-outline" size={24} color={COLORS.textMuted} />
                    <Text style={styles.navText}>Chat</Text>
                </TouchableOpacity>
                <TouchableOpacity
                    style={styles.navItem}
                    onPress={() => router.push('/(patrol)/profile')}
                >
                    <Ionicons name="person-outline" size={24} color={COLORS.textMuted} />
                    <Text style={styles.navText}>Profile</Text>
                </TouchableOpacity>
            </View>

            {/* Announcement Details Modal */}
            <Modal
                visible={showAnnouncementModal}
                transparent={true}
                animationType="fade"
                onRequestClose={() => setShowAnnouncementModal(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={styles.announcementModalContent}>
                        <View style={styles.announcementModalHeader}>
                            <View style={styles.announcementModalIcon}>
                                <Ionicons name="megaphone" size={24} color={COLORS.primary} />
                            </View>
                            <TouchableOpacity onPress={() => setShowAnnouncementModal(false)}>
                                <Ionicons name="close" size={24} color={COLORS.textMuted} />
                            </TouchableOpacity>
                        </View>
                        {selectedAnnouncement && (
                            <>
                                <Text style={styles.announcementModalTitle}>{selectedAnnouncement.title}</Text>
                                <View style={styles.announcementModalMeta}>
                                    <Text style={styles.announcementModalDate}>{selectedAnnouncement.date}</Text>
                                    {selectedAnnouncement.author && (
                                        <Text style={styles.announcementModalAuthor}>by {selectedAnnouncement.author}</Text>
                                    )}
                                </View>
                                <ScrollView style={styles.announcementModalBody}>
                                    <Text style={styles.announcementModalText}>{selectedAnnouncement.content}</Text>

                                    {/* Attachments */}
                                    {selectedAnnouncement.attachments && selectedAnnouncement.attachments.length > 0 && (
                                        <View style={styles.attachmentsSection}>
                                            <Text style={styles.attachmentsTitle}>
                                                <Ionicons name="attach" size={16} color={COLORS.textSecondary} /> Attachments
                                            </Text>
                                            {selectedAnnouncement.attachments.map((attachment: string, index: number) => {
                                                const isImage = attachment.match(/\.(jpg|jpeg|png|gif|webp)$/i);

                                                if (isImage) {
                                                    return (
                                                        <TouchableOpacity
                                                            key={index}
                                                            onPress={() => Linking.openURL(attachment)}
                                                            style={styles.imageAttachmentContainer}
                                                        >
                                                            <Image
                                                                source={{ uri: attachment }}
                                                                style={styles.attachmentImagePreview}
                                                                resizeMode="cover"
                                                            />
                                                            <View style={styles.imageOverlay}>
                                                                <Ionicons name="expand-outline" size={20} color={COLORS.white} />
                                                            </View>
                                                        </TouchableOpacity>
                                                    );
                                                }

                                                return (
                                                    <TouchableOpacity
                                                        key={index}
                                                        style={styles.attachmentItem}
                                                        onPress={() => Linking.openURL(attachment)}
                                                    >
                                                        <View style={styles.attachmentIcon}>
                                                            <Ionicons
                                                                name="document-outline"
                                                                size={20}
                                                                color={COLORS.primary}
                                                            />
                                                        </View>
                                                        <Text style={styles.attachmentName} numberOfLines={1}>
                                                            {attachment.split('/').pop() || `Attachment ${index + 1}`}
                                                        </Text>
                                                        <Ionicons name="open-outline" size={16} color={COLORS.textMuted} />
                                                    </TouchableOpacity>
                                                );
                                            })}
                                        </View>
                                    )}
                                </ScrollView>
                            </>
                        )}
                        <TouchableOpacity
                            style={styles.announcementModalClose}
                            onPress={() => setShowAnnouncementModal(false)}
                        >
                            <Text style={styles.announcementModalCloseText}>Close</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>

            {/* All Announcements Modal */}
            <Modal
                visible={showAllAnnouncements}
                transparent={true}
                animationType="slide"
                onRequestClose={() => setShowAllAnnouncements(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={styles.allAnnouncementsModal}>
                        <View style={styles.allAnnouncementsHeader}>
                            <Text style={styles.allAnnouncementsTitle}>All Announcements</Text>
                            <TouchableOpacity onPress={() => setShowAllAnnouncements(false)}>
                                <Ionicons name="close" size={24} color={COLORS.textMuted} />
                            </TouchableOpacity>
                        </View>
                        <ScrollView style={styles.allAnnouncementsList}>
                            {allAnnouncements.length === 0 ? (
                                <View style={styles.emptyCard}>
                                    <Ionicons name="megaphone-outline" size={48} color={COLORS.textMuted} />
                                    <Text style={styles.emptyText}>No announcements</Text>
                                </View>
                            ) : (
                                allAnnouncements.map((announcement) => (
                                    <TouchableOpacity
                                        key={announcement.id}
                                        style={styles.allAnnouncementItem}
                                        onPress={() => {
                                            setShowAllAnnouncements(false);
                                            setSelectedAnnouncement(announcement);
                                            setShowAnnouncementModal(true);
                                        }}
                                    >
                                        <View style={styles.announcementIconContainer}>
                                            <Ionicons name="megaphone" size={20} color={COLORS.primary} />
                                        </View>
                                        <View style={styles.announcementContent}>
                                            <View style={styles.announcementHeader}>
                                                <Text style={styles.announcementTitle}>{announcement.title}</Text>
                                                <Text style={styles.announcementDate}>{announcement.date}</Text>
                                            </View>
                                            <Text style={styles.announcementMessage} numberOfLines={2}>
                                                {announcement.message}
                                            </Text>
                                        </View>
                                    </TouchableOpacity>
                                ))
                            )}
                        </ScrollView>
                    </View>
                </View>
            </Modal>

            {/* Notifications Modal */}
            <Modal
                visible={showNotificationsModal}
                transparent={true}
                animationType="fade"
                onRequestClose={() => setShowNotificationsModal(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={styles.notificationsModal}>
                        <View style={styles.notificationsHeader}>
                            <Text style={styles.notificationsTitle}>Notifications</Text>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                                {unreadNotifications > 0 && (
                                    <TouchableOpacity
                                        onPress={markAllNotificationsAsRead}
                                        style={{ paddingVertical: 4, paddingHorizontal: 8 }}
                                    >
                                        <Text style={{ fontSize: 13, color: COLORS.primary, fontWeight: '600' }}>
                                            Mark All Read
                                        </Text>
                                    </TouchableOpacity>
                                )}
                                <TouchableOpacity onPress={() => setShowNotificationsModal(false)}>
                                    <Ionicons name="close" size={24} color={COLORS.textMuted} />
                                </TouchableOpacity>
                            </View>
                        </View>
                        <ScrollView style={styles.notificationsList}>
                            {notifications.length === 0 ? (
                                <View style={styles.emptyNotifications}>
                                    <Ionicons name="notifications-off-outline" size={48} color={COLORS.textMuted} />
                                    <Text style={styles.emptyNotificationsText}>No notifications yet</Text>
                                </View>
                            ) : (
                                notifications.map((notification) => (
                                    <TouchableOpacity
                                        key={notification.id}
                                        style={[
                                            styles.notificationItem,
                                            !notification.read && styles.notificationItemUnread
                                        ]}
                                        onPress={() => {
                                            if (!notification.read) {
                                                markNotificationAsRead(notification.id);
                                            }
                                            // Navigate to dispatch if it's a dispatch notification
                                            if (notification.relatedId && notification.type === 'report') {
                                                setShowNotificationsModal(false);
                                                router.push(`/(patrol)/dispatch-details?id=${notification.relatedId}`);
                                            }
                                        }}
                                    >
                                        <View style={[
                                            styles.notificationIcon,
                                            { backgroundColor: notification.read ? COLORS.border : `${COLORS.primary}20` }
                                        ]}>
                                            <Ionicons
                                                name={
                                                    notification.type === 'report' ? 'alert-circle' :
                                                        notification.type === 'verification' ? 'checkmark-circle' :
                                                            notification.type === 'user_flagged' ? 'warning' :
                                                                'notifications'
                                                }
                                                size={20}
                                                color={notification.read ? COLORS.textMuted : COLORS.primary}
                                            />
                                        </View>
                                        <View style={styles.notificationContent}>
                                            <Text style={[
                                                styles.notificationTitle,
                                                !notification.read && styles.notificationTitleUnread
                                            ]}>
                                                {notification.title}
                                            </Text>
                                            <Text style={styles.notificationMessage} numberOfLines={2}>
                                                {notification.message}
                                            </Text>
                                            <Text style={styles.notificationTime}>
                                                {formatTimeAgo(notification.timestamp)}
                                            </Text>
                                        </View>
                                        {!notification.read && (
                                            <TouchableOpacity
                                                onPress={(e) => {
                                                    e.stopPropagation();
                                                    markNotificationAsRead(notification.id);
                                                }}
                                                style={{ padding: 4 }}
                                            >
                                                <Ionicons name="checkmark-circle-outline" size={20} color={COLORS.primary} />
                                            </TouchableOpacity>
                                        )}
                                    </TouchableOpacity>
                                ))
                            )}
                        </ScrollView>
                    </View>
                </View>
            </Modal>

            {/* Logout Confirmation Dialog */}
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

    // Header Styles
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
    headerLeft: {
        flexDirection: 'row',
        alignItems: 'center',
        flex: 1,
    },
    avatarContainer: {
        position: 'relative',
    },
    avatar: {
        width: isTablet ? 56 : 48,
        height: isTablet ? 56 : 48,
        borderRadius: isTablet ? 28 : 24,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: COLORS.primary,
    },
    avatarText: {
        color: COLORS.white,
        fontSize: fontSize.lg,
        fontWeight: 'bold',
    },
    onlineIndicator: {
        position: 'absolute',
        bottom: 2,
        right: 2,
        width: 14,
        height: 14,
        borderRadius: 7,
        backgroundColor: COLORS.success,
        borderWidth: 2,
        borderColor: COLORS.white,
    },
    welcomeText: {
        marginLeft: spacing.md,
        flex: 1,
    },
    welcomeLabel: {
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
    },
    userName: {
        fontSize: fontSize.lg,
        fontWeight: 'bold',
        color: COLORS.textPrimary,
    },
    headerRight: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.sm,
    },
    notificationButton: {
        width: 48,
        height: 48,
        borderRadius: 24,
        backgroundColor: COLORS.background,
        justifyContent: 'center',
        alignItems: 'center',
        position: 'relative',
    },
    logoutButton: {
        width: 44,
        height: 44,
        borderRadius: 22,
        backgroundColor: '#FEE2E2',
        justifyContent: 'center',
        alignItems: 'center',
    },
    notificationBadge: {
        position: 'absolute',
        top: 6,
        right: 6,
        minWidth: 18,
        height: 18,
        borderRadius: 9,
        backgroundColor: COLORS.accent,
        justifyContent: 'center',
        alignItems: 'center',
        paddingHorizontal: 4,
    },
    notificationBadgeText: {
        color: COLORS.white,
        fontSize: 10,
        fontWeight: 'bold',
    },

    // Content
    content: {
        flex: 1,
    },

    // Banner Styles
    bannerContainer: {
        paddingHorizontal: containerPadding.horizontal,
        paddingTop: spacing.lg,
    },
    banner: {
        borderRadius: borderRadius.lg,
        padding: spacing.lg,
        overflow: 'hidden',
        backgroundColor: COLORS.primary,
    },
    bannerContent: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'flex-start',
    },
    bannerTextContainer: {
        flex: 1,
    },
    bannerTitle: {
        fontSize: fontSize.xl,
        fontWeight: 'bold',
        color: COLORS.white,
        marginBottom: spacing.xs,
    },
    bannerSubtitle: {
        fontSize: fontSize.sm,
        color: 'rgba(255,255,255,0.8)',
    },
    bannerIconContainer: {
        marginLeft: spacing.md,
    },
    bannerStats: {
        flexDirection: 'row',
        marginTop: spacing.lg,
        paddingTop: spacing.md,
        borderTopWidth: 1,
        borderTopColor: 'rgba(255,255,255,0.2)',
    },
    bannerStatItem: {
        flex: 1,
    },
    bannerStatDivider: {
        width: 1,
        backgroundColor: 'rgba(255,255,255,0.2)',
        marginHorizontal: spacing.md,
    },
    bannerStatValue: {
        fontSize: fontSize.xl,
        fontWeight: 'bold',
        color: COLORS.white,
    },
    bannerStatLabel: {
        fontSize: fontSize.xs,
        color: 'rgba(255,255,255,0.7)',
        marginTop: 2,
    },

    // Quick Actions Styles
    quickActionsContainer: {
        paddingHorizontal: containerPadding.horizontal,
        paddingVertical: spacing.lg,
    },
    quickActionButtonFull: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.white,
        borderRadius: borderRadius.lg,
        padding: spacing.md,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.08,
        shadowRadius: 8,
        elevation: 3,
        borderWidth: 1,
        borderColor: '#FEE2E2',
    },
    quickActionButton: {
        alignItems: 'center',
        flex: 1,
    },
    quickActionButtonCenter: {
        alignItems: 'center',
        flex: 1,
        marginTop: -spacing.md,
    },
    quickActionIconBg: {
        width: isTablet ? 64 : 56,
        height: isTablet ? 64 : 56,
        borderRadius: isTablet ? 32 : 28,
        justifyContent: 'center',
        alignItems: 'center',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
        elevation: 3,
    },
    quickActionIconBgLarge: {
        width: isTablet ? 70 : 60,
        height: isTablet ? 70 : 60,
        borderRadius: isTablet ? 35 : 30,
        justifyContent: 'center',
        alignItems: 'center',
        position: 'relative',
    },
    quickActionTextContainer: {
        flex: 1,
        marginLeft: spacing.md,
    },
    quickActionTextMain: {
        fontSize: fontSize.md,
        color: COLORS.textPrimary,
        fontWeight: '700',
    },
    quickActionTextSub: {
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
        marginTop: 2,
    },
    quickActionText: {
        fontSize: fontSize.sm,
        color: COLORS.textPrimary,
        marginTop: spacing.sm,
        fontWeight: '600',
    },
    quickActionBadge: {
        position: 'absolute',
        top: -4,
        right: -4,
        minWidth: 20,
        height: 20,
        borderRadius: 10,
        backgroundColor: COLORS.accent,
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 2,
        borderColor: COLORS.white,
    },
    quickActionBadgeLarge: {
        position: 'absolute',
        top: -2,
        right: -2,
        minWidth: 22,
        height: 22,
        borderRadius: 11,
        backgroundColor: COLORS.accent,
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 2,
        borderColor: COLORS.white,
    },
    quickActionBadgeText: {
        color: COLORS.white,
        fontSize: 10,
        fontWeight: 'bold',
    },

    // Section Styles
    section: {
        paddingHorizontal: containerPadding.horizontal,
        marginBottom: spacing.lg,
    },
    sectionHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: spacing.md,
    },
    sectionTitle: {
        fontSize: fontSize.lg,
        fontWeight: 'bold',
        color: COLORS.textPrimary,
    },
    seeAllText: {
        fontSize: fontSize.sm,
        color: COLORS.primary,
        fontWeight: '600',
    },

    // Empty State
    emptyCard: {
        backgroundColor: COLORS.cardBg,
        borderRadius: borderRadius.lg,
        padding: spacing.xxl,
        alignItems: 'center',
        justifyContent: 'center',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 4,
        elevation: 2,
    },
    emptyText: {
        fontSize: fontSize.md,
        color: COLORS.textMuted,
        marginTop: spacing.md,
    },

    // Report Card Styles
    reportCard: {
        backgroundColor: COLORS.cardBg,
        borderRadius: borderRadius.md,
        padding: spacing.md,
        marginBottom: spacing.sm,
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 4,
        elevation: 2,
    },
    reportCardLeft: {
        flexDirection: 'row',
        alignItems: 'center',
        flex: 1,
    },
    reportStatusIndicator: {
        width: 4,
        height: 40,
        borderRadius: 2,
        marginRight: spacing.md,
    },
    reportInfo: {
        flex: 1,
    },
    reportType: {
        fontSize: fontSize.md,
        fontWeight: '600',
        color: COLORS.textPrimary,
        marginBottom: 4,
    },
    reportLocationRow: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    reportLocation: {
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
        marginLeft: 4,
    },
    reportCardRight: {
        alignItems: 'flex-end',
    },
    reportTime: {
        fontSize: fontSize.xs,
        color: COLORS.textMuted,
        marginBottom: 4,
    },
    statusBadge: {
        paddingHorizontal: spacing.sm,
        paddingVertical: 4,
        borderRadius: borderRadius.sm,
    },
    statusBadgeText: {
        fontSize: fontSize.xs,
        fontWeight: '600',
    },

    // Announcement Card Styles
    announcementCard: {
        backgroundColor: COLORS.cardBg,
        borderRadius: borderRadius.md,
        padding: spacing.md,
        marginBottom: spacing.sm,
        flexDirection: 'row',
        alignItems: 'flex-start',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 4,
        elevation: 2,
    },
    announcementIconContainer: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: '#EEF2FF',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: spacing.md,
    },
    announcementContent: {
        flex: 1,
    },
    announcementHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    announcementTitle: {
        fontSize: fontSize.md,
        fontWeight: '600',
        color: COLORS.textPrimary,
        flex: 1,
    },
    announcementDate: {
        fontSize: fontSize.xs,
        color: COLORS.textMuted,
        marginLeft: spacing.sm,
    },
    announcementMessage: {
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
        lineHeight: fontSize.sm * 1.4,
    },

    // Bottom Navigation Styles
    bottomNav: {
        flexDirection: 'row',
        backgroundColor: COLORS.white,
        paddingVertical: spacing.sm,
        paddingBottom: spacing.lg,
        borderTopWidth: 1,
        borderTopColor: COLORS.border,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: -4 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
        elevation: 10,
    },
    navItem: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.xs,
    },
    navBadge: {
        position: 'absolute',
        top: -4,
        right: -8,
        minWidth: 16,
        height: 16,
        borderRadius: 8,
        backgroundColor: COLORS.accent,
        justifyContent: 'center',
        alignItems: 'center',
        paddingHorizontal: 4,
    },
    navBadgeText: {
        color: COLORS.white,
        fontSize: 9,
        fontWeight: 'bold',
    },
    navText: {
        fontSize: fontSize.xs,
        color: COLORS.textMuted,
        marginTop: 4,
    },
    navTextActive: {
        color: COLORS.primary,
        fontWeight: '600',
    },

    // Modal Styles
    modalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'center',
        alignItems: 'center',
        padding: spacing.lg,
    },
    announcementModalContent: {
        backgroundColor: COLORS.white,
        borderRadius: borderRadius.lg,
        padding: spacing.lg,
        width: '100%',
        maxWidth: 400,
        maxHeight: '80%',
    },
    announcementModalHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: spacing.md,
    },
    announcementModalIcon: {
        width: 40,
        height: 40,
        borderRadius: 20,
        backgroundColor: '#EEF2FF',
        justifyContent: 'center',
        alignItems: 'center',
    },
    announcementModalTitle: {
        fontSize: fontSize.lg,
        fontWeight: 'bold',
        color: COLORS.textPrimary,
        marginBottom: spacing.sm,
    },
    announcementModalMeta: {
        flexDirection: 'row',
        gap: spacing.md,
        marginBottom: spacing.md,
    },
    announcementModalDate: {
        fontSize: fontSize.sm,
        color: COLORS.textMuted,
    },
    announcementModalAuthor: {
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
    },
    announcementModalBody: {
        maxHeight: 300,
        marginBottom: spacing.md,
    },
    announcementModalText: {
        fontSize: fontSize.md,
        color: COLORS.textPrimary,
        lineHeight: 24,
    },
    attachmentsSection: {
        marginTop: spacing.lg,
        paddingTop: spacing.md,
        borderTopWidth: 1,
        borderTopColor: COLORS.border,
    },
    attachmentsTitle: {
        fontSize: fontSize.sm,
        fontWeight: '600',
        color: COLORS.textSecondary,
        marginBottom: spacing.sm,
    },
    attachmentItem: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.background,
        padding: spacing.sm,
        borderRadius: borderRadius.sm,
        marginBottom: spacing.xs,
    },
    attachmentIcon: {
        width: 36,
        height: 36,
        borderRadius: 8,
        backgroundColor: '#EEF2FF',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: spacing.sm,
    },
    attachmentName: {
        flex: 1,
        fontSize: fontSize.sm,
        color: COLORS.textPrimary,
    },
    imageAttachmentContainer: {
        position: 'relative',
        marginBottom: spacing.sm,
        borderRadius: borderRadius.md,
        overflow: 'hidden',
    },
    attachmentImagePreview: {
        width: '100%',
        height: 180,
        borderRadius: borderRadius.md,
    },
    imageOverlay: {
        position: 'absolute',
        bottom: spacing.sm,
        right: spacing.sm,
        backgroundColor: 'rgba(0,0,0,0.5)',
        borderRadius: borderRadius.sm,
        padding: spacing.xs,
    },
    announcementModalClose: {
        backgroundColor: COLORS.primary,
        paddingVertical: spacing.md,
        borderRadius: borderRadius.md,
        alignItems: 'center',
    },
    announcementModalCloseText: {
        color: COLORS.white,
        fontSize: fontSize.md,
        fontWeight: '600',
    },
    allAnnouncementsModal: {
        backgroundColor: COLORS.white,
        borderRadius: borderRadius.lg,
        width: '100%',
        maxWidth: 450,
        maxHeight: '85%',
    },
    allAnnouncementsHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: spacing.lg,
        borderBottomWidth: 1,
        borderBottomColor: COLORS.border,
    },
    allAnnouncementsTitle: {
        fontSize: fontSize.lg,
        fontWeight: 'bold',
        color: COLORS.textPrimary,
    },
    allAnnouncementsList: {
        padding: spacing.md,
    },
    allAnnouncementItem: {
        flexDirection: 'row',
        backgroundColor: COLORS.cardBg,
        padding: spacing.md,
        borderRadius: borderRadius.md,
        marginBottom: spacing.sm,
        borderWidth: 1,
        borderColor: COLORS.border,
    },
    // Notifications Modal Styles
    notificationsModal: {
        backgroundColor: COLORS.white,
        borderRadius: borderRadius.lg,
        width: '100%',
        maxWidth: 400,
        maxHeight: '80%',
    },
    notificationsHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: spacing.lg,
        borderBottomWidth: 1,
        borderBottomColor: COLORS.border,
    },
    notificationsTitle: {
        fontSize: fontSize.lg,
        fontWeight: 'bold',
        color: COLORS.textPrimary,
    },
    notificationsList: {
        padding: spacing.md,
        maxHeight: 500,
    },
    emptyNotifications: {
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.xxl,
    },
    emptyNotificationsText: {
        fontSize: fontSize.md,
        color: COLORS.textMuted,
        marginTop: spacing.md,
    },
    notificationItem: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        padding: spacing.md,
        borderRadius: borderRadius.md,
        marginBottom: spacing.sm,
        backgroundColor: COLORS.white,
        borderWidth: 1,
        borderColor: COLORS.border,
    },
    notificationItemUnread: {
        backgroundColor: `${COLORS.primary}05`,
        borderColor: `${COLORS.primary}20`,
    },
    notificationIcon: {
        width: 40,
        height: 40,
        borderRadius: 20,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: spacing.md,
    },
    notificationContent: {
        flex: 1,
    },
    notificationTitle: {
        fontSize: fontSize.md,
        color: COLORS.textPrimary,
        marginBottom: 2,
    },
    notificationTitleUnread: {
        fontWeight: '600',
    },
    notificationMessage: {
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
        marginBottom: spacing.xs,
    },
    notificationTime: {
        fontSize: fontSize.xs,
        color: COLORS.textMuted,
    },
    unreadDot: {
        width: 8,
        height: 8,
        borderRadius: 4,
        backgroundColor: COLORS.primary,
        marginLeft: spacing.sm,
        marginTop: 6,
    },
});
