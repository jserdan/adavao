import React, { useState, useEffect } from 'react';
import { View, Text, TextInput, TouchableOpacity, FlatList, StyleSheet, ActivityIndicator, KeyboardAvoidingView, Platform, Modal, Alert } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import styles from "./styles";
import { useUser } from '../../contexts/UserContext';
import { messageService, Message } from '../../services/messageService';
import { userService } from '../../services/userService';
import { BACKEND_URL } from '../../config/backend';

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

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
};

const ChatMessage = React.memo(({ item, userId }: { item: Message, userId: string | undefined }) => {
    const isUserMessage = item.sender_id.toString() === userId;

    return (
        <View style={[
            localStyles.messageWrapper,
            isUserMessage ? localStyles.userMessageWrapper : localStyles.officerMessageWrapper
        ]}>
            <View style={[
                localStyles.messageBubble,
                isUserMessage ? localStyles.userBubble : localStyles.officerBubble
            ]}>
                <Text style={[
                    localStyles.messageText,
                    isUserMessage ? localStyles.userMessageText : localStyles.officerMessageText
                ]}>
                    {item.message}
                </Text>
                <Text style={[
                    localStyles.timeText,
                    isUserMessage ? localStyles.userTimeText : localStyles.officerTimeText
                ]}>
                    {formatTime(item.sent_at)}
                </Text>
            </View>
        </View>
    );
});

const ChatScreen = () => {
    // 📊 Performance Timing - Start
    const pageStartTime = React.useRef(Date.now());
    React.useEffect(() => {
        const loadTime = Date.now() - pageStartTime.current;
        console.log(`📊 [Chat] Page Load Time: ${loadTime}ms`);
    }, []);
    // 📊 Performance Timing - End

    const { user } = useUser();
    const params = useLocalSearchParams();
    const otherUserId = params.otherUserId as string;
    const otherUserName = params.otherUserName as string;

    const [messages, setMessages] = useState<Message[]>([]);
    const [newMessage, setNewMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);
    const [isOtherUserTyping, setIsOtherUserTyping] = useState(false);
    const [showEnforcerModal, setShowEnforcerModal] = useState(false);
    const [enforcerDetails, setEnforcerDetails] = useState<any>(null);
    const [loadingEnforcerDetails, setLoadingEnforcerDetails] = useState(false);
    const [messageError, setMessageError] = useState('');
    const MAX_MESSAGE_LENGTH = 10000; // Safe limit for TEXT column (65,535 bytes)
    let typingTimeout: ReturnType<typeof setTimeout> | null = null;
    let typingCheckInterval: ReturnType<typeof setInterval> | null = null;

    const fetchMessages = async (isInitialLoad = false) => {
        if (!user || !user.id || !otherUserId) return;

        try {
            const response = await messageService.getMessages(parseInt(user.id), parseInt(otherUserId));

            if (response.success) {
                // Sort messages by timestamp descending (newest first)
                // This ensures newest appears at bottom when FlatList is inverted
                const sortedMessages = response.data.sort((a: any, b: any) =>
                    new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
                );
                // Only update if messages actually changed to prevent unnecessary re-renders
                setMessages(prev => {
                    if (JSON.stringify(prev.map(m => m.message_id)) === JSON.stringify(sortedMessages.map((m: any) => m.message_id))) {
                        return prev;
                    }
                    return sortedMessages;
                });
                // Mark conversation as read
                await messageService.markConversationAsRead(parseInt(user.id), parseInt(otherUserId));
            }
        } catch (error) {
            // Silent fail for background polling
        } finally {
            if (isInitialLoad) setLoading(false);
        }
    };

    useEffect(() => {
        // Fetch immediately (initial load)
        fetchMessages(true);

        // Poll for new messages every 2 seconds for better real-time feel
        const interval = setInterval(() => {
            fetchMessages(false); // Silent background refresh
        }, 15000);

        // Check typing status every 800ms
        typingCheckInterval = setInterval(() => {
            checkTypingStatus();
        }, 800);

        return () => {
            clearInterval(interval);
            if (typingCheckInterval) clearInterval(typingCheckInterval);
        };
    }, [user, otherUserId]);

    const sendMessage = async () => {
        if (newMessage.trim() === '' || !user || !user.id) {
            console.log('❌ Cannot send message - validation failed:', {
                messageEmpty: newMessage.trim() === '',
                userExists: !!user,
                userId: user?.id
            });
            return;
        }

        // Validate message length
        if (newMessage.trim().length > MAX_MESSAGE_LENGTH) {
            Alert.alert(
                'Message Too Long',
                `Your message is too long (${newMessage.trim().length} characters). Maximum allowed is ${MAX_MESSAGE_LENGTH} characters.`,
                [{ text: 'OK' }]
            );
            return;
        }

        setSending(true);
        setMessageError('');

        try {
            console.log('📨 Attempting to send message:', {
                senderId: user.id,
                receiverId: otherUserId,
                messageLength: newMessage.trim().length
            });

            const response = await messageService.sendMessage(
                parseInt(user.id),
                parseInt(otherUserId),
                newMessage.trim()
            );

            console.log('📨 Send message response:', response);

            if (response.success) {
                console.log('✅ Message sent, clearing input and refreshing...');
                setNewMessage('');
                setMessageError('');
                // Refresh messages
                await fetchMessages();
            } else {
                console.error('❌ Message send failed:', response);
                Alert.alert(
                    'Failed to Send',
                    'Could not send your message. Please try again.',
                    [{ text: 'OK' }]
                );
            }
        } catch (error) {
            console.error('❌ Error sending message:', error);
            Alert.alert(
                'Network Error',
                'Could not send your message. Please check your connection and try again.',
                [{ text: 'OK' }]
            );
        } finally {
            setSending(false);
        }
    };



    const sendTypingStatus = async (isTyping: boolean) => {
        if (!user || !user.id || !otherUserId) return;

        try {
            const response = await fetch(`${BACKEND_URL}/api/messages/typing`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    sender_id: user.id,
                    receiver_id: parseInt(otherUserId),
                    is_typing: isTyping
                })
            });
        } catch (error) {
            // Silent fail
        }
    };

    const checkTypingStatus = async () => {
        if (!user || !user.id || !otherUserId) return;

        try {
            const response = await fetch(`${BACKEND_URL}/api/messages/typing-status/${otherUserId}/${user.id}`, {
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (!response.ok) {
                console.error('Failed to check typing status:', response.status);
                return;
            }

            const data = await response.json();
            if (data.success) {
                setIsOtherUserTyping(data.is_typing);
            }
        } catch (error) {
            // Silent fail
        }
    };

    const fetchEnforcerDetails = async () => {
        if (!otherUserId) {
            console.log('❌ No otherUserId available');
            Alert.alert('Error', 'No officer ID available.');
            return;
        }

        console.log('🔍 Fetching enforcer details for userId:', otherUserId);
        setLoadingEnforcerDetails(true);
        try {
            const details = await userService.getUserWithStation(otherUserId);
            console.log('✅ Enforcer details received:', details);
            if (details) {
                setEnforcerDetails(details);
                setShowEnforcerModal(true);
                console.log('✅ Modal should now be visible');
            } else {
                // Show basic info if detailed fetch fails
                setEnforcerDetails({
                    firstname: otherUserName?.split(' ')[0] || 'Officer',
                    lastname: otherUserName?.split(' ').slice(1).join(' ') || '',
                    contact: 'N/A',
                    stationName: 'Information not available',
                    stationAddress: 'Please contact admin for details'
                });
                setShowEnforcerModal(true);
            }
        } catch (error) {
            console.error('❌ Error fetching enforcer details:', error);
            // Show available info instead of error
            setEnforcerDetails({
                firstname: otherUserName?.split(' ')[0] || 'Officer',
                lastname: otherUserName?.split(' ').slice(1).join(' ') || '',
                contact: 'N/A',
                stationName: 'Unable to load station details',
                stationAddress: 'Please try again later'
            });
            setShowEnforcerModal(true);
        } finally {
            setLoadingEnforcerDetails(false);
        }
    };

    const renderMessage = React.useCallback(({ item }: { item: Message }) => (
        <ChatMessage item={item} userId={user?.id} />
    ), [user?.id]);

    if (loading) {
        return (
            <View style={localStyles.loadingContainer}>
                <View style={localStyles.loadingSpinner}>
                    <ActivityIndicator size="large" color={COLORS.primary} />
                </View>
                <Text style={localStyles.loadingText}>Loading messages...</Text>
            </View>
        );
    }

    return (
        <KeyboardAvoidingView
            style={{ flex: 1 }}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
            keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}
        >
            <View style={localStyles.container}>
                {/* Header */}
                <View style={localStyles.header}>
                    <TouchableOpacity
                        onPress={() => router.push('/chatlist')}
                        style={localStyles.backButton}
                    >
                        <Ionicons name="chevron-back" size={24} color={COLORS.primary} />
                    </TouchableOpacity>
                    <View style={localStyles.headerCenter}>
                        <Text style={localStyles.headerTitle}>
                            <Text style={{ color: COLORS.primary }}>Alert</Text>
                            <Text style={{ color: '#000' }}>Davao</Text>
                        </Text>
                        <TouchableOpacity
                            onPress={fetchEnforcerDetails}
                            disabled={loadingEnforcerDetails}
                            style={localStyles.officerNameButton}
                        >
                            <Text style={localStyles.officerName}>
                                {otherUserName || 'Chat'}
                            </Text>
                            <Ionicons name="information-circle" size={18} color={COLORS.primary} />
                        </TouchableOpacity>
                    </View>
                    <View style={{ width: 40 }} />
                </View>

                {/* Divider */}
                <View style={localStyles.divider} />

                {/* Chat Messages */}
                <FlatList
                    data={messages}
                    keyExtractor={(item) => item.message_id.toString()}
                    renderItem={renderMessage}
                    style={localStyles.chatArea}
                    contentContainerStyle={localStyles.chatContent}
                    initialNumToRender={15}
                    maxToRenderPerBatch={10}
                    windowSize={10}
                    removeClippedSubviews={true}
                    inverted={true}
                />

                {/* Typing Indicator */}
                {isOtherUserTyping && (
                    <View style={localStyles.typingContainer}>
                        <View style={localStyles.typingIndicator}>
                            <View style={[localStyles.typingDot, { opacity: 0.4 }]} />
                            <View style={[localStyles.typingDot, { opacity: 0.6 }]} />
                            <View style={[localStyles.typingDot, { opacity: 1 }]} />
                            <Text style={localStyles.typingText}>typing...</Text>
                        </View>
                    </View>
                )}

                {/* Input Area */}
                <View style={localStyles.inputWrapper}>
                    {newMessage.length > MAX_MESSAGE_LENGTH * 0.8 && (
                        <View style={localStyles.charLimitWarning}>
                            <Text style={[
                                localStyles.charLimitText,
                                newMessage.length > MAX_MESSAGE_LENGTH && { color: COLORS.accent }
                            ]}>
                                {newMessage.length > MAX_MESSAGE_LENGTH
                                    ? `⚠️ Message too long! (${newMessage.length - MAX_MESSAGE_LENGTH} over limit)`
                                    : `${MAX_MESSAGE_LENGTH - newMessage.length} characters remaining`}
                            </Text>
                        </View>
                    )}
                    <View style={localStyles.inputContainer}>
                        <TextInput
                            style={localStyles.chatInput}
                            placeholder="Write a message..."
                            placeholderTextColor={COLORS.textMuted}
                            value={newMessage}
                            onChangeText={(text) => {
                                setNewMessage(text);
                                if (text.length <= MAX_MESSAGE_LENGTH) {
                                    setMessageError('');
                                }
                                // Send typing status
                                sendTypingStatus(true);

                                // Clear previous timeout
                                if (typingTimeout) clearTimeout(typingTimeout);

                                // Set timeout to clear typing status after 3 seconds
                                typingTimeout = setTimeout(() => {
                                    sendTypingStatus(false);
                                }, 3000);
                            }}
                            multiline
                            editable={!sending}
                        />
                        <TouchableOpacity
                            style={[
                                localStyles.sendButton,
                                (sending || newMessage.trim() === '') && localStyles.sendButtonDisabled
                            ]}
                            onPress={sendMessage}
                            disabled={sending || newMessage.trim() === ''}
                        >
                            {sending ? (
                                <ActivityIndicator size="small" color="#fff" />
                            ) : (
                                <Ionicons name="send" size={18} color="#fff" />
                            )}
                        </TouchableOpacity>
                    </View>
                </View>

                {/* Enforcer Details Modal */}
                <Modal
                    visible={showEnforcerModal}
                    transparent={true}
                    animationType="fade"
                    onRequestClose={() => setShowEnforcerModal(false)}
                >
                    <View style={styles.modalOverlay}>
                        <View style={styles.enforcerModalContainer}>
                            {loadingEnforcerDetails ? (
                                <View style={styles.enforcerLoadingContainer}>
                                    <ActivityIndicator size="large" color="#1D3557" />
                                    <Text style={styles.enforcerLoadingText}>Loading details...</Text>
                                </View>
                            ) : enforcerDetails ? (
                                <View style={styles.enforcerModalContent}>
                                    {/* Header */}
                                    <View style={styles.enforcerModalHeader}>
                                        <View style={styles.enforcerHeaderTitleContainer}>
                                            <Ionicons name="person-circle" size={28} color="#1D3557" />
                                            <Text style={styles.enforcerModalTitle}>Officer Profile</Text>
                                        </View>
                                        <TouchableOpacity
                                            onPress={() => setShowEnforcerModal(false)}
                                            style={styles.enforcerCloseButton}
                                        >
                                            <Ionicons name="close" size={26} color="#1D3557" />
                                        </TouchableOpacity>
                                    </View>

                                    {/* Divider */}
                                    <View style={styles.enforcerDivider} />

                                    {/* Content */}
                                    <View style={styles.enforcerDetailsContent}>
                                        {/* Name Section */}
                                        <View style={styles.enforcerDetailSection}>
                                            <View style={styles.enforcerIconLabelContainer}>
                                                <Ionicons name="person" size={20} color="#1D3557" />
                                                <Text style={styles.enforcerDetailLabel}>Full Name</Text>
                                            </View>
                                            <Text style={styles.enforcerDetailValue}>
                                                {enforcerDetails.firstname} {enforcerDetails.lastname}
                                            </Text>
                                        </View>

                                        {/* Contact Section */}
                                        <View style={styles.enforcerDetailSection}>
                                            <View style={styles.enforcerIconLabelContainer}>
                                                <Ionicons name="call" size={20} color="#1D3557" />
                                                <Text style={styles.enforcerDetailLabel}>Contact Number</Text>
                                            </View>
                                            <Text style={styles.enforcerDetailValue}>
                                                {enforcerDetails.contact !== 'N/A' ? enforcerDetails.contact : 'Not provided'}
                                            </Text>
                                        </View>

                                        {/* Station Section */}
                                        <View style={styles.enforcerDetailSection}>
                                            <View style={styles.enforcerIconLabelContainer}>
                                                <Ionicons name="location" size={20} color="#1D3557" />
                                                <Text style={styles.enforcerDetailLabel}>Assigned Station</Text>
                                            </View>
                                            <Text style={styles.enforcerDetailValue}>
                                                {enforcerDetails.stationName}
                                            </Text>
                                        </View>

                                        {/* Station Address Section */}
                                        <View style={[styles.enforcerDetailSection, styles.enforcerLastSection]}>
                                            <View style={styles.enforcerIconLabelContainer}>
                                                <Ionicons name="map" size={20} color="#1D3557" />
                                                <Text style={styles.enforcerDetailLabel}>Station Address</Text>
                                            </View>
                                            <Text style={[styles.enforcerDetailValue, { lineHeight: 22 }]}>
                                                {enforcerDetails.stationAddress}
                                            </Text>
                                        </View>
                                    </View>

                                    {/* Close Button */}
                                    <TouchableOpacity
                                        style={styles.enforcerCloseActionButton}
                                        onPress={() => setShowEnforcerModal(false)}
                                    >
                                        <Text style={styles.enforcerCloseActionButtonText}>Close</Text>
                                    </TouchableOpacity>
                                </View>
                            ) : (
                                <View style={styles.enforcerErrorContainer}>
                                    <Ionicons name="alert-circle" size={48} color="#E63946" />
                                    <Text style={styles.enforcerErrorText}>Unable to load officer details</Text>
                                    <TouchableOpacity
                                        style={styles.enforcerCloseActionButton}
                                        onPress={() => setShowEnforcerModal(false)}
                                    >
                                        <Text style={styles.enforcerCloseActionButtonText}>Close</Text>
                                    </TouchableOpacity>
                                </View>
                            )}
                        </View>
                    </View>
                </Modal>
            </View>
        </KeyboardAvoidingView>
    );
};

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
    officerNameButton: {
        flexDirection: 'row',
        alignItems: 'center',
        marginTop: 4,
        paddingVertical: 4,
        paddingHorizontal: 10,
        backgroundColor: '#E3F2FD',
        borderRadius: 16,
    },
    officerName: {
        fontSize: 14,
        color: COLORS.primary,
        fontWeight: '600',
        marginRight: 6,
    },
    divider: {
        height: 1,
        backgroundColor: COLORS.border,
    },
    chatArea: {
        flex: 1,
        backgroundColor: COLORS.background,
    },
    chatContent: {
        paddingHorizontal: 16,
        paddingBottom: 10,
        flexGrow: 1,
        justifyContent: 'flex-end',
    },
    // Message Styles
    messageWrapper: {
        marginVertical: 4,
    },
    userMessageWrapper: {
        alignItems: 'flex-end',
    },
    officerMessageWrapper: {
        alignItems: 'flex-start',
    },
    messageBubble: {
        maxWidth: '80%',
        paddingHorizontal: 16,
        paddingVertical: 12,
        borderRadius: 18,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
        elevation: 1,
    },
    userBubble: {
        backgroundColor: COLORS.primary,
        borderBottomRightRadius: 6,
    },
    officerBubble: {
        backgroundColor: COLORS.white,
        borderBottomLeftRadius: 6,
        borderWidth: 1,
        borderColor: COLORS.border,
    },
    messageText: {
        fontSize: 15,
        lineHeight: 20,
    },
    userMessageText: {
        color: COLORS.white,
    },
    officerMessageText: {
        color: COLORS.textPrimary,
    },
    timeText: {
        fontSize: 11,
        marginTop: 6,
    },
    userTimeText: {
        color: 'rgba(255,255,255,0.7)',
        textAlign: 'right',
    },
    officerTimeText: {
        color: COLORS.textMuted,
    },
    // Typing Indicator
    typingContainer: {
        paddingHorizontal: 16,
        paddingVertical: 8,
        backgroundColor: COLORS.background,
    },
    typingIndicator: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: COLORS.white,
        paddingHorizontal: 14,
        paddingVertical: 10,
        borderRadius: 18,
        alignSelf: 'flex-start',
        borderWidth: 1,
        borderColor: COLORS.border,
    },
    typingDot: {
        width: 7,
        height: 7,
        borderRadius: 4,
        backgroundColor: COLORS.textMuted,
        marginRight: 3,
    },
    typingText: {
        fontSize: 12,
        color: COLORS.textMuted,
        marginLeft: 4,
        fontStyle: 'italic',
    },
    // Input Area
    inputWrapper: {
        backgroundColor: COLORS.white,
        paddingBottom: Platform.OS === 'android' ? 20 : 20,
        borderTopWidth: 1,
        borderTopColor: COLORS.border,
    },
    charLimitWarning: {
        paddingHorizontal: 16,
        paddingVertical: 6,
        backgroundColor: '#fff7ed',
    },
    charLimitText: {
        fontSize: 12,
        color: COLORS.warning,
        fontWeight: '500',
    },
    inputContainer: {
        flexDirection: 'row',
        alignItems: 'flex-end',
        paddingHorizontal: 16,
        paddingVertical: 12,
    },
    chatInput: {
        flex: 1,
        minHeight: 44,
        maxHeight: 120,
        backgroundColor: COLORS.background,
        borderRadius: 22,
        paddingHorizontal: 18,
        paddingVertical: 12,
        paddingRight: 16,
        fontSize: 15,
        color: COLORS.textPrimary,
        borderWidth: 1,
        borderColor: COLORS.border,
    },
    sendButton: {
        width: 44,
        height: 44,
        borderRadius: 22,
        backgroundColor: COLORS.primary,
        justifyContent: 'center',
        alignItems: 'center',
        marginLeft: 10,
        shadowColor: COLORS.primary,
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 4,
        elevation: 3,
    },
    sendButtonDisabled: {
        backgroundColor: COLORS.textMuted,
        shadowOpacity: 0,
        elevation: 0,
    },
});

export default ChatScreen;