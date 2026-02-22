import React, { useState, useEffect, useRef } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    TextInput,
    FlatList,
    KeyboardAvoidingView,
    Platform,
    ActivityIndicator,
    Keyboard,
} from 'react-native';
import { useRouter, useLocalSearchParams } from 'expo-router';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { API_URL } from '../../config/backend';
import { spacing, fontSize, containerPadding, borderRadius } from '../../utils/responsive';

const COLORS = {
    primary: '#1D3557',
    primaryLight: '#2a4a7a',
    accent: '#E63946',
    white: '#ffffff',
    background: '#f0f2f5',
    surface: '#ffffff',
    textPrimary: '#1D3557',
    textSecondary: '#6b7280',
    textMuted: '#9ca3af',
    border: '#e5e7eb',
    borderLight: '#f0f0f0',
    success: '#10b981',
    messageSent: '#1D3557',
    messageReceived: '#ffffff',
    inputBg: '#f8f9fa',
    onlineDot: '#22c55e',
};

interface Contact {
    id: number;
    name: string;
    role: string;
    lastMessage?: string;
    lastMessageTime?: string;
    unreadCount?: number;
}

interface Message {
    message_id: number;
    sender_id: number;
    receiver_id: number;
    message: string;
    sent_at: string;
    is_read: boolean;
}

export default function PatrolChatScreen() {
    const router = useRouter();
    const params = useLocalSearchParams();
    const contactId = params?.contactId ? String(params.contactId) : null;
    const contactName = params?.contactName ? String(params.contactName) : null;
    const contactRole = params?.contactRole ? String(params.contactRole) : null;

    const [userId, setUserId] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [contacts, setContacts] = useState<Contact[]>([]);
    const [conversations, setConversations] = useState<Contact[]>([]);
    const [messages, setMessages] = useState<Message[]>([]);
    const [newMessage, setNewMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [showContacts, setShowContacts] = useState(false);

    const flatListRef = useRef<FlatList>(null);
    const inputRef = useRef<TextInput>(null);

    // Scroll to bottom whenever keyboard opens
    useEffect(() => {
        if (!contactId) return;
        const showEvent = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
        const sub = Keyboard.addListener(showEvent, () => {
            setTimeout(() => flatListRef.current?.scrollToEnd({ animated: true }), 100);
        });
        return () => sub.remove();
    }, [contactId]);

    useEffect(() => { loadUserData(); }, []);

    useEffect(() => {
        if (userId) {
            if (contactId) { loadMessages(); }
            else { loadConversations(); }
        }
    }, [userId, contactId]);

    // Auto-refresh messages every 3s
    useEffect(() => {
        if (!userId || !contactId) return;
        const interval = setInterval(() => loadMessages(false), 3000);
        return () => clearInterval(interval);
    }, [userId, contactId]);

    // Auto-refresh conversations every 5s
    useEffect(() => {
        if (!userId || contactId) return;
        const interval = setInterval(() => loadConversations(false), 5000);
        return () => clearInterval(interval);
    }, [userId, contactId]);

    const loadUserData = async () => {
        try {
            const stored = await AsyncStorage.getItem('userData');
            if (!stored) return;
            const user = JSON.parse(stored);
            setUserId(user?.id?.toString() || user?.userId?.toString());
        } catch (error) {
            console.error('Error loading user data:', error);
        }
    };

    const loadConversations = async (showLoading = true) => {
        if (!userId) return;
        if (showLoading) setLoading(true);
        try {
            const response = await fetch(`${API_URL}/messages/conversations/${userId}`);
            const data = await response.json();
            if (data.success) {
                const allConversations = (data.data || []).map((c: any) => ({
                    id: c.other_user_id || c.user_id || c.id,
                    name: c.name || c.user_name || `${c.firstname || c.user_firstname || ''} ${c.lastname || c.user_lastname || ''}`.trim() || 'Unknown',
                    role: c.role || 'admin',
                    lastMessage: c.last_message || c.lastMessage,
                    lastMessageTime: c.last_message_time || c.lastMessageTime,
                    unreadCount: c.unread_count || 0,
                }));
                setConversations(allConversations);
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
        } finally {
            if (showLoading) setLoading(false);
        }
    };

    const loadContacts = async () => {
        try {
            const response = await fetch(`${API_URL}/messages/contacts/admin-police`);
            const data = await response.json();
            if (data.success) { setContacts(data.data || []); }
        } catch (error) {
            console.error('Error loading contacts:', error);
        }
    };

    const loadMessages = async (showLoading = true) => {
        if (!userId || !contactId) return;
        if (showLoading) setLoading(true);
        try {
            const response = await fetch(`${API_URL}/messages/${userId}/${contactId}`);
            const data = await response.json();
            if (data.success) {
                setMessages(prev => {
                    // Sort descending from API to ascending (oldest first)
                    const newData = (data.data || []).sort((a: any, b: any) =>
                        new Date(a.sent_at).getTime() - new Date(b.sent_at).getTime()
                    );
                    if (prev.length === newData.length && prev.length > 0) {
                        const lastOld = prev[prev.length - 1]?.message_id;
                        const lastNew = newData[newData.length - 1]?.message_id;
                        if (lastOld === lastNew) return prev;
                    }
                    return newData;
                });
                await fetch(`${API_URL}/messages/conversation/read`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userId, otherUserId: contactId }),
                }).catch(() => { });
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        } finally {
            if (showLoading) setLoading(false);
        }
    };

    const sendChatMessage = async () => {
        if (!newMessage.trim() || !userId || !contactId || sending) return;
        setSending(true);
        const messageText = newMessage.trim();
        setNewMessage('');
        try {
            const response = await fetch(`${API_URL}/messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    senderId: parseInt(userId),
                    receiverId: parseInt(contactId),
                    message: messageText,
                }),
            });
            const data = await response.json();
            if (data.success) {
                loadMessages(false);
                setTimeout(() => flatListRef.current?.scrollToEnd({ animated: true }), 100);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            setNewMessage(messageText);
        } finally {
            setSending(false);
        }
    };

    const formatTime = (dateStr: string) => {
        const date = new Date(dateStr);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    };

    const formatDate = (dateStr: string) => {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        if (days === 0) return 'Today';
        if (days === 1) return 'Yesterday';
        if (days < 7) return date.toLocaleDateString('en-US', { weekday: 'long' });
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };

    const formatTimeAgo = (dateStr: string) => {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 1) return 'now';
        if (diffMins < 60) return `${diffMins}m`;
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours}h`;
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays < 7) return `${diffDays}d`;
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };

    const getRoleIcon = (role: string): any => role === 'admin' ? 'shield-checkmark' : 'people';
    const getRoleColor = (role: string) => role === 'admin' ? '#7C3AED' : '#2563EB';
    const getRoleLabel = (role: string) => role === 'admin' ? 'Admin' : 'Police';

    // Build flat data for FlatList with date separators
    const getChatData = (): ({ type: 'date'; date: string } | { type: 'message'; data: Message })[] => {
        const items: ({ type: 'date'; date: string } | { type: 'message'; data: Message })[] = [];
        let currentDate = '';
        messages.forEach((msg) => {
            const msgDate = new Date(msg.sent_at).toDateString();
            if (msgDate !== currentDate) {
                currentDate = msgDate;
                items.push({ type: 'date', date: msg.sent_at });
            }
            items.push({ type: 'message', data: msg });
        });
        return items;
    };

    const renderChatItem = ({ item }: { item: { type: 'date'; date: string } | { type: 'message'; data: Message } }) => {
        if (item.type === 'date') {
            return (
                <View style={styles.dateSeparator}>
                    <View style={styles.dateLine} />
                    <Text style={styles.dateText}>{formatDate(item.date)}</Text>
                    <View style={styles.dateLine} />
                </View>
            );
        }

        const msg = item.data;
        const isMine = String(msg.sender_id) === userId;

        return (
            <View style={[styles.messageRow, isMine ? styles.messageRowSent : styles.messageRowReceived]}>
                {!isMine && (
                    <View style={[styles.messageAvatar, { backgroundColor: getRoleColor(contactRole || 'admin') }]}>
                        <Ionicons name={getRoleIcon(contactRole || 'admin')} size={12} color={COLORS.white} />
                    </View>
                )}
                <View style={[styles.messageBubble, isMine ? styles.messageSent : styles.messageReceived]}>
                    <Text style={[styles.messageText, isMine ? styles.messageTextSent : styles.messageTextReceived]}>
                        {msg.message}
                    </Text>
                    <View style={styles.messageFooter}>
                        <Text style={[styles.messageTime, isMine ? styles.messageTimeSent : styles.messageTimeReceived]}>
                            {formatTime(msg.sent_at)}
                        </Text>
                        {isMine && (
                            <Ionicons
                                name={msg.is_read ? 'checkmark-done' : 'checkmark'}
                                size={14}
                                color={msg.is_read ? '#60a5fa' : 'rgba(255,255,255,0.5)'}
                                style={{ marginLeft: 4 }}
                            />
                        )}
                    </View>
                </View>
            </View>
        );
    };

    // ─── CONVERSATION THREAD VIEW ────────────────────────────────────
    if (contactId) {
        const chatData = getChatData();

        return (
            <KeyboardAvoidingView
                style={{ flex: 1, backgroundColor: COLORS.background }}
                behavior="padding"
                keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 80}
            >
                {/* Chat Header */}
                <View style={styles.chatHeader}>
                    <TouchableOpacity
                        onPress={() => router.push('/(patrol)/chat')}
                        style={styles.backBtn}
                        hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                    >
                        <Ionicons name="chevron-back" size={26} color={COLORS.white} />
                    </TouchableOpacity>
                    <View style={styles.chatHeaderInfo}>
                        <View style={[styles.chatHeaderAvatar, { backgroundColor: getRoleColor(contactRole || 'admin') }]}>
                            <Ionicons name={getRoleIcon(contactRole || 'admin')} size={18} color={COLORS.white} />
                        </View>
                        <View style={styles.chatHeaderTextContainer}>
                            <Text style={styles.chatHeaderName} numberOfLines={1}>{contactName || 'Unknown'}</Text>
                            <View style={styles.chatHeaderStatus}>
                                <View style={styles.onlineDot} />
                                <Text style={styles.chatHeaderRole}>
                                    {contactRole === 'admin' ? 'Central Admin' : 'Police Station'}
                                </Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* Messages Area */}
                {loading ? (
                    <View style={styles.loadingContainer}>
                        <ActivityIndicator size="large" color={COLORS.primary} />
                        <Text style={styles.loadingText}>Loading messages...</Text>
                    </View>
                ) : (
                    <FlatList
                        ref={flatListRef}
                        data={chatData}
                        renderItem={renderChatItem}
                        keyExtractor={(item, index) =>
                            item.type === 'date' ? `date-${index}` : `msg-${item.data.message_id}`
                        }
                        style={styles.messagesContainer}
                        contentContainerStyle={[
                            styles.messagesContent,
                            chatData.length === 0 && styles.messagesContentEmpty,
                        ]}
                        onContentSizeChange={() =>
                            setTimeout(() => flatListRef.current?.scrollToEnd({ animated: false }), 50)
                        }
                        onLayout={() =>
                            flatListRef.current?.scrollToEnd({ animated: false })
                        }
                        showsVerticalScrollIndicator={false}
                        keyboardShouldPersistTaps="handled"
                        keyboardDismissMode="interactive"
                        automaticallyAdjustKeyboardInsets={Platform.OS === 'ios'}
                        ListEmptyComponent={
                            <View style={styles.emptyMessages}>
                                <View style={styles.emptyIconCircle}>
                                    <Ionicons name="chatbubbles-outline" size={40} color={COLORS.primary} />
                                </View>
                                <Text style={styles.emptyText}>No messages yet</Text>
                                <Text style={styles.emptySubtext}>
                                    Say hello to start the conversation!
                                </Text>
                            </View>
                        }
                    />
                )}

                {/* Message Input */}
                <View style={styles.inputWrapper}>
                    <View style={styles.inputContainer}>
                        <TextInput
                            ref={inputRef}
                            style={styles.textInput}
                            placeholder="Type a message..."
                            placeholderTextColor={COLORS.textMuted}
                            value={newMessage}
                            onChangeText={setNewMessage}
                            multiline
                            maxLength={1000}
                            onFocus={() => {
                                setTimeout(() => flatListRef.current?.scrollToEnd({ animated: true }), 200);
                            }}
                        />
                        <TouchableOpacity
                            style={[
                                styles.sendButton,
                                (!newMessage.trim() || sending) && styles.sendButtonDisabled,
                            ]}
                            onPress={sendChatMessage}
                            disabled={!newMessage.trim() || sending}
                            activeOpacity={0.7}
                        >
                            {sending ? (
                                <ActivityIndicator size="small" color={COLORS.white} />
                            ) : (
                                <Ionicons name="send" size={18} color={COLORS.white} />
                            )}
                        </TouchableOpacity>
                    </View>
                </View>
            </KeyboardAvoidingView>
        );
    }

    // ─── CONVERSATION LIST VIEW ──────────────────────────────────────
    return (
        <View style={styles.container}>
            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity
                    onPress={() => router.replace('/(patrol)/dashboard')}
                    style={styles.backBtn}
                    hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                >
                    <Ionicons name="chevron-back" size={26} color={COLORS.white} />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>Messages</Text>
                <TouchableOpacity
                    onPress={() => { loadContacts(); setShowContacts(true); }}
                    style={styles.newChatBtn}
                    hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                >
                    <Ionicons name="create-outline" size={22} color={COLORS.white} />
                </TouchableOpacity>
            </View>

            {loading ? (
                <View style={styles.loadingContainer}>
                    <ActivityIndicator size="large" color={COLORS.primary} />
                    <Text style={styles.loadingText}>Loading conversations...</Text>
                </View>
            ) : conversations.length === 0 && !showContacts ? (
                <View style={styles.emptyContainer}>
                    <View style={styles.emptyIconCircleLarge}>
                        <Ionicons name="chatbubbles-outline" size={56} color={COLORS.primary} />
                    </View>
                    <Text style={styles.emptyTitle}>No Conversations</Text>
                    <Text style={[styles.emptySubtext, { maxWidth: 280, marginTop: spacing.sm }]}>
                        Start a conversation with admin or police station officers
                    </Text>
                    <TouchableOpacity
                        style={styles.startChatButton}
                        onPress={() => { loadContacts(); setShowContacts(true); }}
                        activeOpacity={0.8}
                    >
                        <Ionicons name="add-circle" size={20} color={COLORS.white} />
                        <Text style={styles.startChatButtonText}>New Conversation</Text>
                    </TouchableOpacity>
                </View>
            ) : (
                <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
                    {/* New Chat Contacts */}
                    {showContacts && (
                        <View style={styles.contactsSection}>
                            <View style={styles.contactsHeader}>
                                <Text style={styles.contactsSectionTitle}>Start New Conversation</Text>
                                <TouchableOpacity onPress={() => setShowContacts(false)} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
                                    <Ionicons name="close-circle" size={24} color={COLORS.textMuted} />
                                </TouchableOpacity>
                            </View>
                            {contacts.length === 0 ? (
                                <View style={{ padding: spacing.xl, alignItems: 'center' }}>
                                    <ActivityIndicator size="small" color={COLORS.primary} />
                                    <Text style={[styles.loadingText, { marginTop: spacing.sm }]}>Loading contacts...</Text>
                                </View>
                            ) : (
                                contacts.map((contact, index) => (
                                    <TouchableOpacity
                                        key={contact.id}
                                        style={[
                                            styles.contactItem,
                                            index === contacts.length - 1 && { borderBottomWidth: 0 },
                                        ]}
                                        activeOpacity={0.6}
                                        onPress={() => {
                                            setShowContacts(false);
                                            router.push(`/(patrol)/chat?contactId=${contact.id}&contactName=${encodeURIComponent(contact.name)}&contactRole=${contact.role}`);
                                        }}
                                    >
                                        <View style={[styles.contactAvatar, { backgroundColor: getRoleColor(contact.role) }]}>
                                            <Ionicons name={getRoleIcon(contact.role)} size={20} color={COLORS.white} />
                                        </View>
                                        <View style={styles.contactInfo}>
                                            <Text style={styles.contactName}>{contact.name}</Text>
                                            <Text style={[styles.roleTagText, { color: getRoleColor(contact.role) }]}>
                                                {getRoleLabel(contact.role)}
                                            </Text>
                                        </View>
                                        <View style={styles.contactChatIcon}>
                                            <Ionicons name="chatbubble" size={16} color={COLORS.primary} />
                                        </View>
                                    </TouchableOpacity>
                                ))
                            )}
                        </View>
                    )}

                    {/* Existing Conversations */}
                    {conversations.length > 0 && (
                        <View style={styles.conversationsSection}>
                            <Text style={styles.sectionTitle}>Recent</Text>
                            {conversations.map((conv) => (
                                <TouchableOpacity
                                    key={conv.id}
                                    style={styles.conversationItem}
                                    activeOpacity={0.6}
                                    onPress={() => router.push(`/(patrol)/chat?contactId=${conv.id}&contactName=${encodeURIComponent(conv.name)}&contactRole=${conv.role}`)}
                                >
                                    <View style={styles.conversationAvatarContainer}>
                                        <View style={[styles.contactAvatar, { backgroundColor: getRoleColor(conv.role) }]}>
                                            <Ionicons name={getRoleIcon(conv.role)} size={20} color={COLORS.white} />
                                        </View>
                                        {(conv.unreadCount ?? 0) > 0 && (
                                            <View style={styles.unreadBadge}>
                                                <Text style={styles.unreadBadgeText}>
                                                    {(conv.unreadCount ?? 0) > 99 ? '99+' : conv.unreadCount}
                                                </Text>
                                            </View>
                                        )}
                                    </View>
                                    <View style={styles.conversationInfo}>
                                        <View style={styles.conversationTopRow}>
                                            <Text
                                                style={[
                                                    styles.conversationName,
                                                    (conv.unreadCount ?? 0) > 0 && styles.conversationNameUnread,
                                                ]}
                                                numberOfLines={1}
                                            >
                                                {conv.name}
                                            </Text>
                                            {conv.lastMessageTime && (
                                                <Text
                                                    style={[
                                                        styles.conversationTime,
                                                        (conv.unreadCount ?? 0) > 0 && styles.conversationTimeUnread,
                                                    ]}
                                                >
                                                    {formatTimeAgo(conv.lastMessageTime)}
                                                </Text>
                                            )}
                                        </View>
                                        {conv.lastMessage ? (
                                            <Text
                                                style={[
                                                    styles.conversationLastMsg,
                                                    (conv.unreadCount ?? 0) > 0 && styles.conversationLastMsgUnread,
                                                ]}
                                                numberOfLines={1}
                                            >
                                                {conv.lastMessage}
                                            </Text>
                                        ) : (
                                            <Text style={styles.conversationLastMsg}>No messages yet</Text>
                                        )}
                                    </View>
                                    <Ionicons name="chevron-forward" size={16} color={COLORS.textMuted} />
                                </TouchableOpacity>
                            ))}
                        </View>
                    )}

                    <View style={{ height: 100 }} />
                </ScrollView>
            )}
        </View>
    );
}

const styles = StyleSheet.create({
    // ─── Shared ──────────────────────────────────
    container: {
        flex: 1,
        backgroundColor: COLORS.background,
    },

    // ─── List Header ─────────────────────────────
    header: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingHorizontal: spacing.md,
        paddingTop: containerPadding.vertical + 10,
        paddingBottom: spacing.md + 4,
        backgroundColor: COLORS.primary,
    },
    headerTitle: {
        fontSize: fontSize.xl,
        fontWeight: '700',
        color: COLORS.white,
        letterSpacing: 0.3,
    },
    backBtn: {
        padding: spacing.xs,
        borderRadius: 20,
    },
    newChatBtn: {
        padding: spacing.xs,
        borderRadius: 20,
    },

    // ─── Chat Header ─────────────────────────────
    chatHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: spacing.md,
        paddingTop: containerPadding.vertical + 10,
        paddingBottom: spacing.md,
        backgroundColor: COLORS.primary,
    },
    chatHeaderInfo: {
        flexDirection: 'row',
        alignItems: 'center',
        flex: 1,
        marginLeft: spacing.xs,
    },
    chatHeaderAvatar: {
        width: 38,
        height: 38,
        borderRadius: 19,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: spacing.sm + 2,
    },
    chatHeaderTextContainer: {
        flex: 1,
    },
    chatHeaderName: {
        fontSize: fontSize.md,
        fontWeight: '700',
        color: COLORS.white,
    },
    chatHeaderStatus: {
        flexDirection: 'row',
        alignItems: 'center',
        marginTop: 2,
    },
    onlineDot: {
        width: 7,
        height: 7,
        borderRadius: 3.5,
        backgroundColor: COLORS.onlineDot,
        marginRight: 5,
    },
    chatHeaderRole: {
        fontSize: fontSize.xs,
        color: 'rgba(255,255,255,0.75)',
    },

    // ─── Loading / Empty ─────────────────────────
    loadingContainer: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
    },
    loadingText: {
        marginTop: spacing.sm,
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
    },
    emptyContainer: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        paddingHorizontal: spacing.xl,
    },
    emptyIconCircle: {
        width: 80,
        height: 80,
        borderRadius: 40,
        backgroundColor: 'rgba(29,53,87,0.08)',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: spacing.md,
    },
    emptyIconCircleLarge: {
        width: 100,
        height: 100,
        borderRadius: 50,
        backgroundColor: 'rgba(29,53,87,0.08)',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: spacing.lg,
    },
    emptyTitle: {
        fontSize: fontSize.lg + 2,
        fontWeight: '700',
        color: COLORS.textPrimary,
    },
    emptyText: {
        fontSize: fontSize.md,
        fontWeight: '600',
        color: COLORS.textPrimary,
    },
    emptySubtext: {
        fontSize: fontSize.sm,
        color: COLORS.textMuted,
        textAlign: 'center',
        lineHeight: fontSize.sm * 1.5,
    },
    startChatButton: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.primary,
        paddingHorizontal: spacing.lg + 4,
        paddingVertical: spacing.md,
        borderRadius: 24,
        marginTop: spacing.xl,
        gap: spacing.sm,
        shadowColor: COLORS.primary,
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 8,
        elevation: 6,
    },
    startChatButtonText: {
        fontSize: fontSize.md,
        fontWeight: '600',
        color: COLORS.white,
    },
    content: {
        flex: 1,
    },

    // ─── Contacts ────────────────────────────────
    contactsSection: {
        backgroundColor: COLORS.surface,
        marginHorizontal: spacing.md,
        marginTop: spacing.md,
        borderRadius: 16,
        overflow: 'hidden',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.06,
        shadowRadius: 8,
        elevation: 3,
    },
    contactsHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingHorizontal: spacing.md + 2,
        paddingVertical: spacing.md,
        borderBottomWidth: 1,
        borderBottomColor: COLORS.borderLight,
    },
    contactsSectionTitle: {
        fontSize: fontSize.md,
        fontWeight: '700',
        color: COLORS.textPrimary,
    },
    contactItem: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: spacing.md + 2,
        paddingVertical: spacing.md,
        borderBottomWidth: 1,
        borderBottomColor: COLORS.borderLight,
    },
    contactAvatar: {
        width: 46,
        height: 46,
        borderRadius: 23,
        justifyContent: 'center',
        alignItems: 'center',
    },
    contactInfo: {
        flex: 1,
        marginLeft: spacing.md,
    },
    contactName: {
        fontSize: fontSize.md,
        fontWeight: '600',
        color: COLORS.textPrimary,
    },
    roleTagText: {
        fontSize: fontSize.xs,
        fontWeight: '500',
        marginTop: 3,
    },
    contactChatIcon: {
        width: 32,
        height: 32,
        borderRadius: 16,
        backgroundColor: 'rgba(29,53,87,0.08)',
        justifyContent: 'center',
        alignItems: 'center',
    },

    // ─── Conversations ───────────────────────────
    conversationsSection: {
        paddingHorizontal: spacing.md,
        paddingTop: spacing.md,
    },
    sectionTitle: {
        fontSize: fontSize.xs,
        fontWeight: '700',
        color: COLORS.textMuted,
        textTransform: 'uppercase',
        letterSpacing: 1,
        marginBottom: spacing.sm,
        paddingLeft: 4,
    },
    conversationItem: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.surface,
        paddingHorizontal: spacing.md,
        paddingVertical: spacing.md + 2,
        borderRadius: 14,
        marginBottom: spacing.sm,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.04,
        shadowRadius: 4,
        elevation: 1,
    },
    conversationAvatarContainer: {
        position: 'relative',
    },
    conversationInfo: {
        flex: 1,
        marginLeft: spacing.md,
        marginRight: spacing.xs,
    },
    conversationTopRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    conversationName: {
        fontSize: fontSize.md,
        fontWeight: '500',
        color: COLORS.textPrimary,
        flex: 1,
    },
    conversationNameUnread: {
        fontWeight: '700',
    },
    conversationTime: {
        fontSize: 11,
        color: COLORS.textMuted,
        marginLeft: spacing.sm,
    },
    conversationTimeUnread: {
        color: COLORS.primary,
        fontWeight: '600',
    },
    conversationLastMsg: {
        fontSize: fontSize.sm,
        color: COLORS.textSecondary,
        marginTop: 3,
    },
    conversationLastMsgUnread: {
        color: COLORS.textPrimary,
        fontWeight: '500',
    },
    unreadBadge: {
        position: 'absolute',
        top: -3,
        right: -3,
        minWidth: 20,
        height: 20,
        borderRadius: 10,
        backgroundColor: COLORS.accent,
        justifyContent: 'center',
        alignItems: 'center',
        paddingHorizontal: 5,
        borderWidth: 2,
        borderColor: COLORS.surface,
    },
    unreadBadgeText: {
        color: COLORS.white,
        fontSize: 10,
        fontWeight: 'bold',
    },

    // ─── Messages ────────────────────────────────
    messagesContainer: {
        flex: 1,
        backgroundColor: COLORS.background,
    },
    messagesContent: {
        paddingHorizontal: spacing.md,
        paddingTop: spacing.sm,
        paddingBottom: spacing.sm,
    },
    messagesContentEmpty: {
        flexGrow: 1,
        justifyContent: 'center',
    },
    emptyMessages: {
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.xxl,
    },
    dateSeparator: {
        flexDirection: 'row',
        alignItems: 'center',
        marginVertical: spacing.md,
        paddingHorizontal: spacing.sm,
    },
    dateLine: {
        flex: 1,
        height: 1,
        backgroundColor: COLORS.border,
    },
    dateText: {
        fontSize: 11,
        fontWeight: '600',
        color: COLORS.textMuted,
        paddingHorizontal: spacing.md,
        textTransform: 'uppercase',
        letterSpacing: 0.5,
    },
    messageRow: {
        flexDirection: 'row',
        marginBottom: spacing.xs + 2,
        alignItems: 'flex-end',
    },
    messageRowSent: {
        justifyContent: 'flex-end',
    },
    messageRowReceived: {
        justifyContent: 'flex-start',
    },
    messageAvatar: {
        width: 24,
        height: 24,
        borderRadius: 12,
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 6,
        marginBottom: 2,
    },
    messageBubble: {
        maxWidth: '78%',
        paddingHorizontal: spacing.md,
        paddingVertical: spacing.sm + 2,
        borderRadius: 18,
    },
    messageSent: {
        backgroundColor: COLORS.messageSent,
        borderBottomRightRadius: 6,
    },
    messageReceived: {
        backgroundColor: COLORS.messageReceived,
        borderBottomLeftRadius: 6,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
        elevation: 1,
    },
    messageText: {
        fontSize: fontSize.md,
        lineHeight: fontSize.md * 1.45,
    },
    messageTextSent: {
        color: COLORS.white,
    },
    messageTextReceived: {
        color: COLORS.textPrimary,
    },
    messageFooter: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'flex-end',
        marginTop: 3,
    },
    messageTime: {
        fontSize: 10,
    },
    messageTimeSent: {
        color: 'rgba(255,255,255,0.55)',
    },
    messageTimeReceived: {
        color: COLORS.textMuted,
    },

    // ─── Input ───────────────────────────────────
    inputWrapper: {
        backgroundColor: COLORS.surface,
        borderTopWidth: 1,
        borderTopColor: COLORS.borderLight,
        paddingTop: spacing.sm,
        paddingBottom: Platform.OS === 'ios' ? spacing.lg + 10 : spacing.lg + 14,
        paddingHorizontal: spacing.md,
    },
    inputContainer: {
        flexDirection: 'row',
        alignItems: 'flex-end',
    },
    textInput: {
        flex: 1,
        minHeight: 42,
        maxHeight: 110,
        backgroundColor: COLORS.inputBg,
        borderRadius: 22,
        paddingHorizontal: spacing.md + 2,
        paddingTop: Platform.OS === 'ios' ? 12 : 10,
        paddingBottom: Platform.OS === 'ios' ? 12 : 10,
        fontSize: fontSize.md,
        color: COLORS.textPrimary,
        marginRight: spacing.sm,
        borderWidth: 1,
        borderColor: COLORS.border,
    },
    sendButton: {
        width: 42,
        height: 42,
        borderRadius: 21,
        backgroundColor: COLORS.primary,
        justifyContent: 'center',
        alignItems: 'center',
        shadowColor: COLORS.primary,
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 4,
        elevation: 4,
    },
    sendButtonDisabled: {
        backgroundColor: COLORS.textMuted,
        shadowOpacity: 0,
        elevation: 0,
    },
});
