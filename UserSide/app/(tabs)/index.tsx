import React, { useState, useEffect, useRef } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    RefreshControl,
    Dimensions,
    Pressable,
    Modal,
    Image,
    Linking,
} from 'react-native';
import { useRouter, Link } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import { spacing, fontSize, containerPadding, borderRadius, isTablet } from '../../utils/responsive';
import ConfirmDialog from '../../components/ConfirmDialog';
import NotificationPopup from '../../components/NotificationPopup';
import FlagNotificationToast from '../../components/FlagNotificationToast';
import { notificationService } from '../../services/notificationService';
import type { Notification } from '../../services/notificationService';
import { useUser } from '../../contexts/UserContext';
import { messageService } from '../../services/messageService';
import { inactivityManager } from '../../services/inactivityManager';
import { debugService } from '../../services/debugService';
import { API_URL, BACKEND_URL } from '../../config/backend';
import { stopServerWarmup } from '../../utils/serverWarmup';
import { onDataRefresh } from '../../services/sseService';

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

export default function UserDashboard() {
    const router = useRouter();
    const { clearUser } = useUser();
    const [userName, setUserName] = useState('User');
    const [loading, setLoading] = useState(false);
    const [isLoggedIn, setIsLoggedIn] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [showLogoutDialog, setShowLogoutDialog] = useState(false);
    const [showNotifications, setShowNotifications] = useState(false);
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [userId, setUserId] = useState<string>('');
    const [lastUnreadCount, setLastUnreadCount] = useState(0);
    const [badgeHidden, setBadgeHidden] = useState(false);
    const [unreadChatCount, setUnreadChatCount] = useState(0);
    const [flagNotification, setFlagNotification] = useState<Notification | null>(null);
    const [flagStatus, setFlagStatus] = useState<{ totalFlags: number; restrictionLevel: string | null } | null>(null);
    const [flagToastShownThisSession, setFlagToastShownThisSession] = useState(false);
    const [showSideMenu, setShowSideMenu] = useState(false);
    const [announcements, setAnnouncements] = useState<{ id: number; title: string; content: string; message?: string; date: string; author?: string; attachments?: string[] }[]>([]);
    const [selectedAnnouncement, setSelectedAnnouncement] = useState<{ id: number; title: string; content: string; date: string; author?: string; attachments?: string[] } | null>(null);
    const [showAnnouncementModal, setShowAnnouncementModal] = useState(false);
    const [showAllAnnouncements, setShowAllAnnouncements] = useState(false);
    const [allAnnouncements, setAllAnnouncements] = useState<{ id: number; title: string; content: string; message?: string; date: string; author?: string; attachments?: string[] }[]>([]);

    // Ref to track flagStatus for polling callback
    const flagStatusRef = useRef(flagStatus);
    const pollingStopRef = useRef<(() => void) | null>(null);

    useEffect(() => {
        flagStatusRef.current = flagStatus;
    }, [flagStatus]);

    // Load user data on mount
    useEffect(() => {
        const checkAuthStatus = async () => {
            try {
                const userData = await AsyncStorage.getItem('userData');
                if (userData) {
                    const user = JSON.parse(userData);
                    
                    // Route patrol officers to their dashboard if they open the app while already logged in
                    const effectiveRole = String(user.user_role || user.role || '').toLowerCase();
                    const userEmail = String(user.email || '').toLowerCase();
                    const isPatrol = effectiveRole.includes('patrol') || userEmail.includes('.patrol@alertdavao.local');

                    if (isPatrol) {
                        console.log('👮 Patrol officer detected on app load, redirecting to patrol dashboard');
                        router.replace('/(patrol)/dashboard' as any);
                        return;
                    }

                    const first = user.firstname || user.first_name || '';
                    const last = user.lastname || user.last_name || '';
                    const full = `${first} ${last}`.trim();
                    setUserName(full || user.email || 'User');
                    setIsLoggedIn(true);
                    setUserId(user.id || user.user_id || '');
                } else {
                    router.replace('/(tabs)/login');
                }
            } catch (error) {
                console.error('Error checking auth status:', error);
                router.replace('/(tabs)/login');
            } finally {
                setIsLoading(false);
            }
        };
        checkAuthStatus();
    }, []);

    // Fetch flag status from backend
    const fetchFlagStatusFromBackend = async () => {
        if (!userId) return;
        try {
            const result = await debugService.checkFlagStatus(userId);
            if (result.success) {
                const status = result.flagStatus;
                if (status.isFlagged && status.activeFlags > 0) {
                    const allFlagsExpired = result.details?.flags?.length > 0 && result.details.flags.every((f: any) =>
                        f.status === 'confirmed' && f.expires_at && new Date(f.expires_at) <= new Date()
                    );
                    if (allFlagsExpired) {
                        setFlagStatus(null);
                        setFlagNotification(null);
                    } else {
                        setFlagStatus({
                            totalFlags: status.activeFlags,
                            restrictionLevel: status.restrictionLevel || 'warning',
                        });
                    }
                } else {
                    setFlagStatus(null);
                    setFlagNotification(null);
                }
            }
        } catch (error) {
            console.error('Error fetching flag status:', error);
        }
    };

    // Handle focus - load notifications and start polling
    useFocusEffect(
        React.useCallback(() => {
            if (userId) {
                fetchFlagStatusFromBackend();
                loadNotifications(userId);
                loadUnreadChatCount(userId);

                pollingStopRef.current = notificationService.startNotificationPolling(
                    userId,
                    (newNotification) => {
                        setNotifications(prev => {
                            const exists = prev.some(n => n.id === newNotification.id);
                            if (!exists) return [newNotification, ...prev];
                            return prev;
                        });
                        if (newNotification.type === 'user_flagged' && !flagToastShownThisSession) {
                            setShowNotifications(true);
                            setFlagNotification(newNotification);
                            setFlagToastShownThisSession(true);
                            if (newNotification.data) {
                                setFlagStatus({
                                    totalFlags: newNotification.data.total_flags || 1,
                                    restrictionLevel: newNotification.data.restriction_applied || null,
                                });
                            }
                        }
                    },
                    (hasFlagNotifications) => {
                        if (!hasFlagNotifications && flagStatusRef.current) {
                            loadNotifications(userId);
                            fetchFlagStatusFromBackend();
                        }
                    },
                    2000 // Poll every 2 seconds for real-time updates
                );
            }
            return () => {
                if (pollingStopRef.current) {
                    pollingStopRef.current();
                    pollingStopRef.current = null;
                }
            };
        }, [userId])
    );

    useEffect(() => {
        if (userId) fetchFlagStatusFromBackend();
    }, [userId]);

    // Load notifications
    const loadNotifications = async (userId: string) => {
        try {
            if (!userId) return;
            const userNotifications = await notificationService.getUserNotifications(userId);
            setNotifications(userNotifications);

            const flagNotif = userNotifications.find(n => n.type === 'user_flagged' && !n.read);
            if (flagNotif && !flagToastShownThisSession) {
                setFlagNotification(flagNotif);
                setFlagToastShownThisSession(true);
                if (flagNotif.data) {
                    setFlagStatus({
                        totalFlags: flagNotif.data.total_flags || 1,
                        restrictionLevel: flagNotif.data.restriction_applied || null,
                    });
                }
            }

            const currentUnreadCount = userNotifications.filter(n => !n.read).length;
            if (currentUnreadCount > lastUnreadCount && !badgeHidden) {
                setBadgeHidden(false);
            } else if (currentUnreadCount <= lastUnreadCount && badgeHidden === false) {
                setBadgeHidden(true);
            }
            setLastUnreadCount(currentUnreadCount);
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    };

    // Load unread chat count
    const loadUnreadChatCount = async (userId: string) => {
        try {
            if (!userId) return;
            const response = await messageService.getUnreadCount(parseInt(userId));
            if (response.success) {
                setUnreadChatCount(response.count);
            }
        } catch (error) {
            console.error('Error loading unread chat count:', error);
        }
    };

    // Mark notification as read
    const markNotificationAsRead = async (notificationId: number) => {
        try {
            await notificationService.markAsRead(notificationId, userId);
            setNotifications(prevNotifications =>
                prevNotifications.map(n =>
                    n.id === notificationId ? { ...n, read: true } : n
                )
            );
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    };

    // Handle notification press
    const handleNotificationPress = async (notification: Notification) => {
        await markNotificationAsRead(notification.id);
        setShowNotifications(false);
        switch (notification.type) {
            case 'report':
                router.push('/history');
                break;
            case 'verification':
                router.push('/profile');
                break;
            case 'message':
                router.push('/chatlist');
                break;
        }
    };

    const onRefresh = () => {
        setLoading(true);
        if (userId) {
            loadNotifications(userId);
            loadUnreadChatCount(userId);
            fetchFlagStatusFromBackend();
        }
        fetchAnnouncements();
        setTimeout(() => setLoading(false), 1000);
    };

    // Fetch announcements from backend (limit 2 for dashboard, latest first)
    const fetchAnnouncements = async () => {
        try {
            const response = await fetch(`${API_URL}/announcements?limit=2`);
            const data = await response.json();
            if (data.success && data.data) {
                setAnnouncements(data.data.map((a: any) => ({
                    id: a.id,
                    title: a.title,
                    content: a.content,
                    message: a.content, // Alias for display
                    date: a.date,
                    author: a.author,
                    attachments: a.attachments || []
                })));
            }
        } catch (error) {
            console.error('Error fetching announcements:', error);
            // Keep any existing announcements or show empty
        }
    };

    // Fetch all announcements for "See All" modal
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

    // Handle announcement press - show details modal
    const handleAnnouncementPress = (announcement: typeof announcements[0]) => {
        setSelectedAnnouncement(announcement);
        setShowAnnouncementModal(true);
    };

    // Handle "See All" press
    const handleSeeAllAnnouncements = () => {
        fetchAllAnnouncements();
        setShowAllAnnouncements(true);
    };

    // Load announcements on mount and auto-refresh every 2 seconds
    useEffect(() => {
        if (isLoggedIn) {
            fetchAnnouncements();

            // Auto-refresh announcements every 2 seconds (silent)
            const interval = setInterval(() => {
                fetchAnnouncements();
            }, 60000);

            return () => clearInterval(interval);
        }
    }, [isLoggedIn]);

    // SSE-triggered immediate refresh
    useEffect(() => {
        if (!isLoggedIn) return;
        const unsub = onDataRefresh(() => {
            fetchAnnouncements();
        });
        return unsub;
    }, [isLoggedIn]);

    const getInitials = (name: string) => {
        const parts = name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    };

    const handleLogout = async () => {
        setShowLogoutDialog(false);
        setShowSideMenu(false);
        try {
            // Stop inactivity manager
            inactivityManager.stop();

            // Stop server warmup pings
            stopServerWarmup();

            // Get user data before clearing
            const userData = await AsyncStorage.getItem('userData');
            const parsedUser = userData ? JSON.parse(userData) : null;

            // Call backend to clear server-side session (non-blocking)
            if (parsedUser?.id || parsedUser?.email) {
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

            // Clear local storage thoroughly to prevent sticky Google sessions
            const savedEmail = await AsyncStorage.getItem('rememberedEmail');
            await AsyncStorage.clear();
            if (savedEmail) {
                await AsyncStorage.setItem('rememberedEmail', savedEmail);
            }

            // Clear context state
            clearUser();
            setIsLoggedIn(false);
            setUserId('');
            setFlagStatus(null);
            setFlagNotification(null);
            setNotifications([]);

            router.replace('/(tabs)/login');
        } catch (error) {
            console.error('Error logging out:', error);
            router.replace('/(tabs)/login');
        }
    };

    // Calculate unread count
    const unreadCount = notifications.filter(n => !n.read).length;

    // Handle notification icon press
    const handleNotificationIconPress = () => {
        setShowNotifications(true);
        setBadgeHidden(true);
    };

    // Guidelines preview
    const guidelinesPreview = [
        'Report emergencies immediately',
        'Provide accurate location information',
        'Stay calm and follow instructions',
    ];

    if (isLoading) {
        return null;
    }

    if (!isLoggedIn) {
        router.replace('/(tabs)/login');
        return null;
    }

    return (
        <View style={styles.container}>
            {/* Flag Toast Notification */}
            <FlagNotificationToast
                notification={flagNotification}
                onDismiss={() => setFlagNotification(null)}
                onMarkAsRead={markNotificationAsRead}
            />

            {/* Side Menu Modal */}
            <Modal
                visible={showSideMenu}
                transparent={true}
                animationType="fade"
                onRequestClose={() => setShowSideMenu(false)}
            >
                <Pressable style={styles.menuOverlay} onPress={() => setShowSideMenu(false)}>
                    <View style={styles.sideMenu}>
                        <View style={styles.sideMenuHeader}>
                            <View style={styles.sideMenuAvatar}>
                                <Text style={styles.sideMenuAvatarText}>{getInitials(userName)}</Text>
                            </View>
                            <Text style={styles.sideMenuUserName}>{userName}</Text>
                        </View>

                        <TouchableOpacity
                            style={styles.sideMenuItem}
                            onPress={() => {
                                setShowSideMenu(false);
                                router.push('/profile');
                            }}
                        >
                            <Ionicons name="person-outline" size={22} color={COLORS.textPrimary} />
                            <Text style={styles.sideMenuItemText}>Profile</Text>
                        </TouchableOpacity>

                        <TouchableOpacity
                            style={styles.sideMenuItem}
                            onPress={() => {
                                setShowSideMenu(false);
                                setShowLogoutDialog(true);
                            }}
                        >
                            <Ionicons name="log-out-outline" size={22} color={COLORS.accent} />
                            <Text style={[styles.sideMenuItemText, { color: COLORS.accent }]}>Logout</Text>
                        </TouchableOpacity>
                    </View>
                </Pressable>
            </Modal>

            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity onPress={() => setShowSideMenu(true)} style={styles.headerLeft}>
                    <View style={styles.avatarContainer}>
                        <View style={styles.avatar}>
                            <Text style={styles.avatarText}>{getInitials(userName)}</Text>
                        </View>
                    </View>
                    <View style={styles.welcomeText}>
                        <Text style={styles.welcomeLabel}>Welcome back,</Text>
                        <Text style={styles.userName} numberOfLines={1}>{userName}</Text>
                    </View>
                </TouchableOpacity>
                <TouchableOpacity style={styles.notificationButton} onPress={handleNotificationIconPress}>
                    <Ionicons name="notifications-outline" size={26} color={COLORS.primary} />
                    {unreadCount > 0 && !badgeHidden && (
                        <View style={styles.notificationBadge}>
                            <Text style={styles.notificationBadgeText}>
                                {unreadCount > 9 ? '9+' : unreadCount}
                            </Text>
                        </View>
                    )}
                </TouchableOpacity>
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
                                <Text style={styles.bannerTitle}>
                                    <Text style={{ color: COLORS.white }}>Alert</Text>
                                    <Text style={{ color: '#93c5fd' }}>Davao</Text>
                                </Text>
                                <Text style={styles.bannerSubtitle}>
                                    Report incidents quickly and safely
                                </Text>
                            </View>
                            <View style={styles.bannerIconContainer}>
                                <Ionicons name="shield-checkmark" size={48} color="rgba(255,255,255,0.3)" />
                            </View>
                        </View>
                        {flagStatus && (
                            <View style={styles.flagBanner}>
                                <Ionicons name="warning" size={18} color="#fbbf24" />
                                <Text style={styles.flagBannerText}>
                                    {flagStatus.totalFlags} Flag{flagStatus.totalFlags !== 1 ? 's' : ''} Active - Reporting Restricted
                                </Text>
                            </View>
                        )}
                    </View>
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
                                        {announcement.content || announcement.message}
                                    </Text>
                                </View>
                            </TouchableOpacity>
                        ))
                    )}
                </View>

                {/* Guidelines Section */}
                <View style={styles.section}>
                    <View style={styles.sectionHeader}>
                        <Text style={styles.sectionTitle}>Guidelines</Text>
                        <TouchableOpacity onPress={() => router.push('/guidelines')}>
                            <Text style={styles.seeAllText}>View All</Text>
                        </TouchableOpacity>
                    </View>

                    <View style={styles.guidelinesCard}>
                        <View style={styles.guidelinesIconContainer}>
                            <Ionicons name="information-circle" size={28} color={COLORS.primary} />
                        </View>
                        <View style={styles.guidelinesContent}>
                            <Text style={styles.guidelinesTitle}>Reporting Guidelines</Text>
                            {guidelinesPreview.map((guideline, index) => (
                                <View key={index} style={styles.guidelineItem}>
                                    <Ionicons name="checkmark-circle" size={16} color={COLORS.success} />
                                    <Text style={styles.guidelineText}>{guideline}</Text>
                                </View>
                            ))}
                            <TouchableOpacity
                                style={styles.viewGuidelinesBtn}
                                onPress={() => router.push('/guidelines')}
                            >
                                <Text style={styles.viewGuidelinesBtnText}>Read Full Guidelines</Text>
                                <Ionicons name="arrow-forward" size={16} color={COLORS.primary} />
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>

                {/* Bottom Spacing for nav */}
                <View style={{ height: 100 }} />
            </ScrollView>

            {/* Bottom Navigation */}
            <View style={styles.bottomNav}>
                <TouchableOpacity
                    style={styles.navItem}
                    onPress={() => router.push('/chatlist')}
                >
                    <View style={{ position: 'relative' }}>
                        <Ionicons name="chatbubbles-outline" size={24} color={COLORS.textMuted} />
                        {unreadChatCount > 0 && (
                            <View style={styles.chatBadge}>
                                <Text style={styles.chatBadgeText}>
                                    {unreadChatCount > 9 ? '9+' : unreadChatCount}
                                </Text>
                            </View>
                        )}
                    </View>
                    <Text style={styles.navText}>Messages</Text>
                </TouchableOpacity>

                <TouchableOpacity style={styles.navItem}>
                    <Ionicons name="home" size={24} color={COLORS.primary} />
                    <Text style={[styles.navText, styles.navTextActive]}>Home</Text>
                </TouchableOpacity>

                {/* Submit Report - Center Button */}
                <TouchableOpacity
                    style={styles.navItemCenter}
                    onPress={() => {
                        if (!flagStatus) {
                            router.push('/report');
                        }
                    }}
                    disabled={!!flagStatus}
                >
                    <View style={[
                        styles.navCenterButton,
                        flagStatus && { backgroundColor: COLORS.textMuted, opacity: 0.6 }
                    ]}>
                        <Ionicons name="add-circle" size={32} color={COLORS.white} />
                    </View>
                    <Text style={styles.navTextCenter}>Submit Report</Text>
                </TouchableOpacity>

                <TouchableOpacity
                    style={styles.navItem}
                    onPress={() => router.push('/history')}
                >
                    <Ionicons name="time-outline" size={24} color={COLORS.textMuted} />
                    <Text style={styles.navText}>History</Text>
                </TouchableOpacity>

                <TouchableOpacity
                    style={styles.navItem}
                    onPress={() => router.push('/(tabs)/location')}
                >
                    <Ionicons name="business-outline" size={24} color={COLORS.textMuted} />
                    <Text style={styles.navText}>Stations</Text>
                </TouchableOpacity>
            </View>

            {/* Logout Confirmation Dialog */}
            <ConfirmDialog
                visible={showLogoutDialog}
                title="Confirm Logout"
                message="Are you sure you want to log out?"
                cancelText="Cancel"
                confirmText="Logout"
                onCancel={() => setShowLogoutDialog(false)}
                onConfirm={handleLogout}
            />

            {/* Notification Popup */}
            <NotificationPopup
                visible={showNotifications}
                notifications={notifications}
                onClose={() => {
                    setShowNotifications(false);
                    if (userId) loadNotifications(userId);
                }}
                onNotificationPress={handleNotificationPress}
                onMarkAsRead={markNotificationAsRead}
            />

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
                                            {selectedAnnouncement.attachments.map((attachment, index) => {
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
                                                {announcement.content || announcement.message}
                                            </Text>
                                        </View>
                                    </TouchableOpacity>
                                ))
                            )}
                        </ScrollView>
                    </View>
                </View>
            </Modal>
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: COLORS.background,
    },

    // Side Menu Styles
    menuOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        flexDirection: 'row',
    },
    sideMenu: {
        width: 280,
        backgroundColor: COLORS.white,
        paddingTop: 60,
        paddingHorizontal: spacing.lg,
    },
    sideMenuHeader: {
        alignItems: 'center',
        paddingBottom: spacing.xl,
        borderBottomWidth: 1,
        borderBottomColor: COLORS.border,
        marginBottom: spacing.lg,
    },
    sideMenuAvatar: {
        width: 80,
        height: 80,
        borderRadius: 40,
        backgroundColor: COLORS.primary,
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: spacing.md,
    },
    sideMenuAvatarText: {
        color: COLORS.white,
        fontSize: fontSize.xl,
        fontWeight: 'bold',
    },
    sideMenuUserName: {
        fontSize: fontSize.lg,
        fontWeight: 'bold',
        color: COLORS.textPrimary,
    },
    sideMenuItem: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: spacing.md,
        gap: spacing.md,
    },
    sideMenuItemText: {
        fontSize: fontSize.md,
        color: COLORS.textPrimary,
        fontWeight: '500',
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
    notificationButton: {
        width: 48,
        height: 48,
        borderRadius: 24,
        backgroundColor: COLORS.background,
        justifyContent: 'center',
        alignItems: 'center',
        position: 'relative',
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
        fontSize: fontSize.xxl || 28,
        fontWeight: 'bold',
        marginBottom: spacing.xs,
    },
    bannerSubtitle: {
        fontSize: fontSize.sm,
        color: 'rgba(255,255,255,0.8)',
    },
    bannerIconContainer: {
        marginLeft: spacing.md,
    },
    flagBanner: {
        flexDirection: 'row',
        alignItems: 'center',
        marginTop: spacing.md,
        paddingTop: spacing.md,
        borderTopWidth: 1,
        borderTopColor: 'rgba(255,255,255,0.2)',
        gap: spacing.sm,
    },
    flagBannerText: {
        color: '#fbbf24',
        fontSize: fontSize.sm,
        fontWeight: '600',
    },

    // Section Styles
    section: {
        paddingHorizontal: containerPadding.horizontal,
        marginTop: spacing.xl,
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

    // Guidelines Card Styles
    guidelinesCard: {
        backgroundColor: COLORS.cardBg,
        borderRadius: borderRadius.md,
        padding: spacing.lg,
        flexDirection: 'row',
        alignItems: 'flex-start',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 4,
        elevation: 2,
    },
    guidelinesIconContainer: {
        width: 50,
        height: 50,
        borderRadius: 25,
        backgroundColor: '#EEF2FF',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: spacing.md,
    },
    guidelinesContent: {
        flex: 1,
    },
    guidelinesTitle: {
        fontSize: fontSize.md,
        fontWeight: '600',
        color: COLORS.textPrimary,
        marginBottom: spacing.sm,
    },
    guidelineItem: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: spacing.sm,
        marginBottom: spacing.xs,
    },
    guidelineText: {
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
    },
    viewGuidelinesBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        marginTop: spacing.md,
        gap: spacing.xs,
    },
    viewGuidelinesBtnText: {
        fontSize: fontSize.sm,
        color: COLORS.primary,
        fontWeight: '600',
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
    navItemCenter: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        marginTop: -spacing.xl,
    },
    navCenterButton: {
        width: 56,
        height: 56,
        borderRadius: 28,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: COLORS.accent,
        shadowColor: COLORS.accent,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.4,
        shadowRadius: 8,
        elevation: 8,
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
    navTextCenter: {
        fontSize: fontSize.xs,
        color: COLORS.textPrimary,
        marginTop: 4,
        fontWeight: '600',
        textAlign: 'center',
    },
    chatBadge: {
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
    chatBadgeText: {
        color: COLORS.white,
        fontSize: 9,
        fontWeight: 'bold',
    },

    // Announcement Modal Styles
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
        marginBottom: spacing.sm,
        borderRadius: borderRadius.md,
        overflow: 'hidden',
        position: 'relative',
    },
    attachmentImagePreview: {
        width: '100%',
        height: 200,
        borderRadius: borderRadius.md,
    },
    imageOverlay: {
        position: 'absolute',
        top: 8,
        right: 8,
        backgroundColor: 'rgba(0,0,0,0.5)',
        borderRadius: 20,
        width: 36,
        height: 36,
        justifyContent: 'center',
        alignItems: 'center',
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

    // All Announcements Modal Styles
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
});
