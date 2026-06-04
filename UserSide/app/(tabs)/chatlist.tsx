import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Image, FlatList, Pressable, ActivityIndicator, RefreshControl } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useFocusEffect } from 'expo-router';
import styles from "./styles";
import { Link } from 'expo-router';
import { useUser } from '../../contexts/UserContext';
import { messageService, ChatConversation } from '../../services/messageService';

// Color palette matching dashboard
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

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now.getTime() - date.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        // Today - show time
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return date.toLocaleDateString('en-US', { weekday: 'short' });
    } else {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
};

const FAQProfileItem = React.memo(() => (
    <Link href="/FAQScreen" asChild>
        <Pressable style={localStyles.faqItem}>
            {/* Avatar */}
            <View style={localStyles.faqAvatarContainer}>
                <View style={localStyles.faqAvatar}>
                    <Ionicons name="help-circle" size={26} color="#fff" />
                </View>
            </View>

            {/* Chat Info */}
            <View style={{ flex: 1 }}>
                <View style={localStyles.faqHeader}>
                    <Text style={localStyles.faqTitle}>FAQ</Text>
                    <View style={localStyles.pinnedBadge}>
                        <Ionicons name="pin" size={10} color="#fff" style={{ marginRight: 3 }} />
                        <Text style={localStyles.pinnedText}>PINNED</Text>
                    </View>
                </View>
                <Text style={localStyles.faqSubtitle} numberOfLines={1}>
                    Get instant answers to common questions
                </Text>
            </View>
            <Ionicons name="chevron-forward" size={20} color={COLORS.textMuted} />
        </Pressable>
    </Link>
));

const ChatListItem = React.memo(({ item }: { item: ChatConversation }) => (
    <Link
        href={{
            pathname: "/ChatScreen",
            params: {
                otherUserId: item.user_id,
                otherUserName: item.user_name
            }
        }}
        asChild
    >
        <Pressable style={localStyles.chatItem}>
            {/* Avatar */}
            <View style={localStyles.avatarWrapper}>
                <View style={localStyles.chatAvatar}>
                    <Text style={localStyles.avatarText}>
                        {item.user_firstname.charAt(0)}{item.user_lastname.charAt(0)}
                    </Text>
                </View>
                {/* Online indicator could go here */}
            </View>

            {/* Chat Info */}
            <View style={localStyles.chatContent}>
                <View style={localStyles.chatHeader}>
                    <Text style={[localStyles.chatName, item.unread_count > 0 && { fontWeight: '700' }]}>
                        {item.user_name}
                    </Text>
                    <Text style={[localStyles.chatDate, item.unread_count > 0 && { color: COLORS.primary }]}>
                        {formatDate(item.last_message_time)}
                    </Text>
                </View>
                <View style={localStyles.chatPreview}>
                    <Text 
                        style={[localStyles.chatMessage, item.unread_count > 0 && { color: COLORS.textPrimary, fontWeight: '500' }]} 
                        numberOfLines={1}
                    >
                        {item.last_message}
                    </Text>
                    {item.unread_count > 0 && (
                        <View style={localStyles.unreadBadge}>
                            <Text style={localStyles.unreadText}>
                                {item.unread_count > 9 ? '9+' : item.unread_count}
                            </Text>
                        </View>
                    )}
                </View>
            </View>
        </Pressable>
    </Link>
));

export default function ChatList({ navigation }: any) {
    const { user } = useUser();
    const [conversations, setConversations] = useState<ChatConversation[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchConversations = async (isInitialLoad = false) => {
        if (!user || !user.id) {
            setError('Please log in to view messages');
            if (isInitialLoad) setLoading(false);
            return;
        }

        try {
            if (isInitialLoad) setError(null);
            const response = await messageService.getUserConversations(parseInt(user.id));

            if (response.success) {
                // Only update if data actually changed to prevent flickering
                setConversations(prev => {
                    const newIds = response.data.map((c: any) => `${c.other_user_id}-${c.last_message_time}-${c.unread_count}`);
                    const oldIds = prev.map(c => `${c.other_user_id}-${c.last_message_time}-${c.unread_count}`);
                    if (JSON.stringify(newIds) === JSON.stringify(oldIds)) return prev;
                    return response.data;
                });
            } else if (isInitialLoad) {
                setError('Failed to load conversations');
            }
        } catch (err) {
            if (isInitialLoad) setError('Failed to load conversations. Please try again.');
        } finally {
            if (isInitialLoad) setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        // Fetch immediately when component mounts (initial load)
        fetchConversations(true);

        // Set up auto-refresh every 2 seconds (silent background refresh)
        const interval = setInterval(() => {
            fetchConversations(false);
        }, 30000);

        return () => clearInterval(interval);
    }, [user]);

    // Refresh when screen comes into focus
    useFocusEffect(
        React.useCallback(() => {
            fetchConversations(false);
        }, [user])
    );

    const onRefresh = () => {
        setRefreshing(true);
        fetchConversations();
    };

    if (loading) {
        return (
            <View style={localStyles.loadingContainer}>
                <View style={localStyles.loadingSpinner}>
                    <ActivityIndicator size="large" color={COLORS.primary} />
                </View>
                <Text style={localStyles.loadingText}>Loading conversations...</Text>
            </View>
        );
    }

    return (
        <View style={localStyles.container}>
            {/* Header */}
            <View style={localStyles.header}>
                <TouchableOpacity onPress={() => router.push('/')} style={localStyles.backButton}>
                    <Ionicons name="chevron-back" size={24} color={COLORS.primary} />
                </TouchableOpacity>
                <View style={localStyles.headerCenter}>
                    <Text style={localStyles.headerTitle}>
                        <Text style={{ color: COLORS.primary }}>Alert</Text>
                        <Text style={{ color: '#000' }}>Davao</Text>
                    </Text>
                    <Text style={localStyles.headerSubtitle}>Messages</Text>
                </View>
                <View style={{ width: 40 }} />
            </View>

            {/* Divider */}
            <View style={localStyles.divider} />

            {/* Error Message */}
            {error && (
                <View style={localStyles.errorContainer}>
                    <Ionicons name="alert-circle" size={18} color="#c62828" style={{ marginRight: 8 }} />
                    <Text style={localStyles.errorText}>{error}</Text>
                </View>
            )}

            {/* Chat List */}
            <FlatList
                data={conversations}
                keyExtractor={(item) => item.user_id.toString()}
                renderItem={({ item }) => <ChatListItem item={item} />}
                contentContainerStyle={localStyles.listContent}
                showsVerticalScrollIndicator={false}
                ListHeaderComponent={<FAQProfileItem />}
                ListEmptyComponent={
                    <View style={localStyles.emptyContainer}>
                        <View style={localStyles.emptyIconContainer}>
                            <Ionicons name="chatbubbles-outline" size={56} color={COLORS.primary} />
                        </View>
                        <Text style={localStyles.emptyTitle}>No Conversations Yet</Text>
                        <Text style={localStyles.emptySubtitle}>
                            Police officers will contact you here regarding your reports or concerns.
                        </Text>
                        <View style={localStyles.emptyTip}>
                            <Ionicons name="shield-checkmark" size={18} color={COLORS.primary} />
                            <Text style={localStyles.emptyTipText}>
                                Only verified officers can contact you. Keep your reports updated for faster response.
                            </Text>
                        </View>
                    </View>
                }
                initialNumToRender={10}
                maxToRenderPerBatch={10}
                windowSize={5}
                removeClippedSubviews={true}
                refreshControl={
                    <RefreshControl 
                        refreshing={refreshing} 
                        onRefresh={onRefresh} 
                        colors={[COLORS.primary]}
                        tintColor={COLORS.primary}
                    />
                }
            />
        </View>
    );
}

const localStyles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: COLORS.background,
    },
    loadingContainer: {
        flex: 1,
        backgroundColor: COLORS.background,
        justifyContent: 'center',
        alignItems: 'center',
    },
    loadingSpinner: {
        width: 80,
        height: 80,
        borderRadius: 40,
        backgroundColor: COLORS.white,
        justifyContent: 'center',
        alignItems: 'center',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
        elevation: 3,
    },
    loadingText: {
        marginTop: 16,
        fontSize: 14,
        color: COLORS.textSecondary,
        fontWeight: '500',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingTop: 16,
        paddingBottom: 12,
        backgroundColor: COLORS.white,
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
        fontSize: 22,
        fontWeight: 'bold',
    },
    headerSubtitle: {
        fontSize: 15,
        color: COLORS.textSecondary,
        fontWeight: '600',
        marginTop: 2,
    },
    divider: {
        height: 1,
        backgroundColor: COLORS.border,
    },
    errorContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 14,
        backgroundColor: '#ffebee',
        marginHorizontal: 16,
        marginTop: 12,
        borderRadius: 10,
        borderLeftWidth: 4,
        borderLeftColor: '#c62828',
    },
    errorText: {
        flex: 1,
        color: '#c62828',
        fontSize: 13,
        fontWeight: '500',
    },
    listContent: {
        paddingHorizontal: 16,
        paddingTop: 12,
        paddingBottom: 100,
    },
    // FAQ Item Styles
    faqItem: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.white,
        borderRadius: 14,
        padding: 14,
        marginBottom: 10,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.06,
        shadowRadius: 4,
        elevation: 2,
        borderWidth: 1,
        borderColor: '#f0f0f0',
    },
    faqAvatarContainer: {
        marginRight: 14,
    },
    faqAvatar: {
        width: 52,
        height: 52,
        borderRadius: 26,
        backgroundColor: COLORS.accent,
        justifyContent: 'center',
        alignItems: 'center',
        shadowColor: COLORS.accent,
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 4,
        elevation: 3,
    },
    faqHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    faqTitle: {
        fontSize: 16,
        fontWeight: '700',
        color: COLORS.textPrimary,
    },
    pinnedBadge: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.accent,
        paddingHorizontal: 8,
        paddingVertical: 3,
        borderRadius: 10,
    },
    pinnedText: {
        color: '#fff',
        fontSize: 9,
        fontWeight: '700',
        letterSpacing: 0.5,
    },
    faqSubtitle: {
        fontSize: 13,
        color: COLORS.textSecondary,
    },
    // Chat Item Styles
    chatItem: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.white,
        borderRadius: 14,
        padding: 14,
        marginBottom: 10,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.06,
        shadowRadius: 4,
        elevation: 2,
        borderWidth: 1,
        borderColor: '#f0f0f0',
    },
    avatarWrapper: {
        marginRight: 14,
    },
    chatAvatar: {
        width: 52,
        height: 52,
        borderRadius: 26,
        backgroundColor: COLORS.primary,
        justifyContent: 'center',
        alignItems: 'center',
    },
    avatarText: {
        color: '#fff',
        fontSize: 18,
        fontWeight: '700',
        letterSpacing: 0.5,
    },
    chatContent: {
        flex: 1,
    },
    chatHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 4,
    },
    chatName: {
        fontSize: 15,
        fontWeight: '600',
        color: COLORS.textPrimary,
        flex: 1,
    },
    chatDate: {
        fontSize: 12,
        color: COLORS.textMuted,
        marginLeft: 8,
    },
    chatPreview: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
    },
    chatMessage: {
        fontSize: 13,
        color: COLORS.textSecondary,
        flex: 1,
        marginRight: 8,
    },
    unreadBadge: {
        backgroundColor: COLORS.primary,
        borderRadius: 12,
        minWidth: 22,
        height: 22,
        justifyContent: 'center',
        alignItems: 'center',
        paddingHorizontal: 6,
    },
    unreadText: {
        color: '#fff',
        fontSize: 11,
        fontWeight: '700',
    },
    // Empty State
    emptyContainer: {
        alignItems: 'center',
        paddingHorizontal: 30,
        paddingTop: 40,
    },
    emptyIconContainer: {
        width: 110,
        height: 110,
        borderRadius: 55,
        backgroundColor: '#E3F2FD',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 24,
    },
    emptyTitle: {
        fontSize: 20,
        fontWeight: '700',
        color: COLORS.textPrimary,
        marginBottom: 8,
        textAlign: 'center',
    },
    emptySubtitle: {
        fontSize: 14,
        color: COLORS.textSecondary,
        textAlign: 'center',
        lineHeight: 20,
        marginBottom: 24,
    },
    emptyTip: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        backgroundColor: COLORS.white,
        padding: 16,
        borderRadius: 12,
        borderLeftWidth: 4,
        borderLeftColor: COLORS.primary,
        width: '100%',
    },
    emptyTipText: {
        flex: 1,
        marginLeft: 10,
        fontSize: 13,
        color: COLORS.textSecondary,
        lineHeight: 18,
    },
});