import React, { useEffect, useMemo, useState } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    Alert,
    ActivityIndicator,
    Linking,
    TextInput,
    Image,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_URL } from '../../config/backend';
import { onDataRefresh } from '../../services/sseService';
import { spacing, fontSize, containerPadding, borderRadius } from '../../utils/responsive';

const COLORS = {
    primary: '#1D3557',
    accent: '#E63946',
    white: '#ffffff',
    background: '#f5f7fa',
    textPrimary: '#1D3557',
    textSecondary: '#6b7280',
    textMuted: '#9ca3af',
    border: '#e5e7eb',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#dc2626',
};

export default function DispatchDetails() {
    const router = useRouter();
    const params = useLocalSearchParams();
    const dispatchId = useMemo(() => String((params as any)?.id || ''), [params]);

    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);
    const [dispatch, setDispatch] = useState<any>(null);
    const [userId, setUserId] = useState<string | null>(null);
    const [validationNotes, setValidationNotes] = useState('');
    const [showVerificationModal, setShowVerificationModal] = useState(false);
    const [verifyingAs, setVerifyingAs] = useState<'valid' | 'invalid' | null>(null);
    const [selectedPhotoEvidence, setSelectedPhotoEvidence] = useState<ImagePicker.ImagePickerAsset | null>(null);
    const [selectedVideoEvidence, setSelectedVideoEvidence] = useState<ImagePicker.ImagePickerAsset | null>(null);

    useEffect(() => {
        loadUserData();
    }, []);

    useEffect(() => {
        if (userId && dispatchId) {
            loadDetails();
        }
    }, [userId, dispatchId]);

    // Auto-refresh dispatch details every 2 seconds (silent)
    useEffect(() => {
        if (!userId || !dispatchId) return;

        const interval = setInterval(() => {
            loadDetails(false);
        }, 30000);

        return () => clearInterval(interval);
    }, [userId, dispatchId]);

    // SSE-triggered immediate refresh
    useEffect(() => {
        if (!userId || !dispatchId) return;
        const unsub = onDataRefresh(() => loadDetails(false));
        return unsub;
    }, [userId, dispatchId]);

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

    const loadDetails = async (isInitialLoad = true) => {
        if (!dispatchId || !userId) return;
        if (isInitialLoad) setLoading(true);
        try {
            const response = await fetch(
                `${API_URL}/patrol/dispatches/${encodeURIComponent(dispatchId)}?userId=${encodeURIComponent(userId)}`,
                { headers: { 'X-User-Id': userId } }
            );
            const data = await response.json();
            if (response.ok && data?.success) {
                // Only update if data changed to prevent flickering
                setDispatch((prev: any) => {
                    if (prev && JSON.stringify(prev) === JSON.stringify(data.data)) return prev;
                    return data.data;
                });
            } else if (isInitialLoad) {
                Alert.alert('Error', data?.message || 'Failed to load dispatch details');
            }
        } catch {
            if (isInitialLoad) Alert.alert('Error', 'Failed to load dispatch details');
        } finally {
            if (isInitialLoad) setLoading(false);
        }
    };

    const acceptDispatch = async () => {
        if (!dispatchId || !userId) return;
        setActionLoading(true);
        try {
            const response = await fetch(`${API_URL}/dispatch/${dispatchId}/respond`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-User-Id': userId },
                body: JSON.stringify({ userId }),
            });
            const data = await response.json();
            if (response.ok && data?.success) {
                Alert.alert('Success', 'You have accepted this dispatch!');
                loadDetails();
            } else {
                Alert.alert('Error', data?.message || 'Failed to accept dispatch');
            }
        } catch {
            Alert.alert('Error', 'Failed to accept dispatch');
        } finally {
            setActionLoading(false);
        }
    };

    const updateStatus = async (action: 'en-route' | 'arrived') => {
        if (!dispatchId || !userId) return;
        setActionLoading(true);
        try {
            const response = await fetch(`${API_URL}/dispatch/${dispatchId}/${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-User-Id': userId },
                body: JSON.stringify({ userId }),
            });
            const data = await response.json();
            if (response.ok && data?.success) {
                Alert.alert('Success', data.message || 'Status updated');
                loadDetails();
            } else {
                Alert.alert('Error', data?.message || 'Failed to update status');
            }
        } catch {
            Alert.alert('Error', 'Failed to update status');
        } finally {
            setActionLoading(false);
        }
    };

    const pickEvidence = async (kind: 'photo' | 'video') => {
        const permissionResult = await ImagePicker.requestMediaLibraryPermissionsAsync();

        if (!permissionResult.granted) {
            Alert.alert('Permission Required', 'Please grant access to your media library to upload evidence.');
            return;
        }

        const result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: kind === 'photo' ? ['images'] : ['videos'],
            allowsEditing: kind === 'photo',
            quality: 1,
        });

        if (!result.canceled && result.assets && result.assets[0]) {
            const asset = result.assets[0];

            const maxSizeInBytes = 25 * 1024 * 1024;
            if (asset.fileSize && asset.fileSize > maxSizeInBytes) {
                Alert.alert('File Too Large', 'Your file is too big. Please select a file smaller than 25MB.');
                return;
            }

            if (kind === 'photo') {
                setSelectedPhotoEvidence(asset);
                setSelectedVideoEvidence(null);
            } else {
                setSelectedVideoEvidence(asset);
                setSelectedPhotoEvidence(null);
            }
        }
    };

    const removePhotoEvidence = () => setSelectedPhotoEvidence(null);
    const removeVideoEvidence = () => setSelectedVideoEvidence(null);

    const formatBytes = (bytes?: number) => {
        if (!bytes || bytes <= 0) return 'Unknown size';
        const units = ['B', 'KB', 'MB', 'GB'];
        const exp = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / Math.pow(1024, exp);
        return `${value.toFixed(value >= 10 || exp === 0 ? 0 : 1)} ${units[exp]}`;
    };

    const verifyReport = async (isValid: boolean) => {
        if (!dispatchId || !userId) return;

        const evidenceAsset = selectedPhotoEvidence || selectedVideoEvidence;

        // Require evidence photo for verification
        if (!evidenceAsset) {
            Alert.alert('Evidence Required', 'Please attach a photo or video as evidence before verifying.');
            return;
        }

        setActionLoading(true);
        try {
            const formData = new FormData();
            formData.append('userId', userId);
            formData.append('isValid', String(isValid));
            formData.append('validationNotes', validationNotes.trim() || '');

            if (evidenceAsset) {
                const uri = evidenceAsset.uri;
                let filename = evidenceAsset.fileName || uri.split('/').pop() || 'evidence.jpg';
                if (!evidenceAsset.fileName && selectedVideoEvidence) {
                    filename = uri.split('/').pop() || 'evidence.mp4';
                }
                const match = /\.([\w]+)$/.exec(filename);
                let type = match ? `image/${match[1]}` : 'image/jpeg';
                if (selectedVideoEvidence) {
                    type = match ? `video/${match[1]}` : 'video/mp4';
                }
                formData.append('evidence', { uri, name: filename, type } as any);
            }

            const response = await fetch(`${API_URL}/dispatch/${dispatchId}/verify`, {
                method: 'POST',
                headers: { 'X-User-Id': userId },
                body: formData,
            });
            const data = await response.json();
            if (response.ok && data?.success) {
                Alert.alert(
                    'Report Verified',
                    `Report has been marked as ${isValid ? 'VALID' : 'INVALID'}. Admin side has been notified.`,
                    [{ text: 'OK', onPress: () => router.back() }]
                );
            } else {
                Alert.alert('Error', data?.message || 'Failed to verify report');
            }
        } catch {
            Alert.alert('Error', 'Failed to verify report');
        } finally {
            setActionLoading(false);
            setShowVerificationModal(false);
            setSelectedPhotoEvidence(null);
            setSelectedVideoEvidence(null);
        }
    };

    const openMaps = () => {
        if (!dispatch?.report?.location?.latitude || !dispatch?.report?.location?.longitude) {
            Alert.alert('Error', 'Location coordinates not available');
            return;
        }
        const { latitude, longitude } = dispatch.report.location;
        Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`);
    };

    const formatTime = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    };

    const formatCrimeType = (reportType: any) => {
        if (!reportType) return 'Unknown';
        if (Array.isArray(reportType)) return reportType.join(', ');
        try {
            const parsed = JSON.parse(reportType);
            if (Array.isArray(parsed)) return parsed.join(', ');
        } catch { }
        return reportType;
    };

    const getStatusColor = (status: string) => {
        const colors: Record<string, string> = {
            pending: COLORS.textMuted, accepted: COLORS.primary, en_route: COLORS.warning,
            arrived: COLORS.success, completed: '#059669', declined: COLORS.danger, cancelled: COLORS.danger,
        };
        return colors[status] || COLORS.textMuted;
    };

    const renderTimeline = () => {
        const officerName = dispatch?.officer_name || 'Officer';
        const steps = [
            { key: 'dispatched', label: 'Dispatched to all patrol', time: dispatch?.dispatched_at, icon: 'send' },
            { key: 'accepted', label: `${officerName} accepted`, time: dispatch?.accepted_at, icon: 'checkmark-circle' },
            { key: 'en_route', label: `${officerName} en route`, time: dispatch?.en_route_at, icon: 'car' },
            { key: 'arrived', label: `${officerName} arrived`, time: dispatch?.arrived_at, icon: 'location' },
            { key: 'completed', label: 'Completed', time: dispatch?.completed_at, icon: 'checkmark-done-circle' },
        ];
        const currentIndex = steps.findIndex(s => !s.time);

        return (
            <View style={styles.timeline}>
                {steps.map((step, index) => {
                    const isCompleted = !!step.time;
                    const isCurrent = index === currentIndex;
                    return (
                        <View key={step.key} style={styles.timelineStep}>
                            <View style={styles.timelineIconContainer}>
                                <View style={[styles.timelineIcon, isCompleted && styles.timelineIconCompleted, isCurrent && styles.timelineIconCurrent]}>
                                    <Ionicons name={step.icon as any} size={16} color={isCompleted || isCurrent ? COLORS.white : COLORS.textMuted} />
                                </View>
                                {index < steps.length - 1 && <View style={[styles.timelineLine, isCompleted && styles.timelineLineCompleted]} />}
                            </View>
                            <View style={styles.timelineContent}>
                                <Text style={[styles.timelineLabel, isCompleted && styles.timelineLabelCompleted]}>{step.label}</Text>
                                {step.time && <Text style={styles.timelineTime}>{formatTime(step.time)}</Text>}
                            </View>
                        </View>
                    );
                })}
            </View>
        );
    };

    const renderVerificationModal = () => {
        if (!showVerificationModal) return null;
        return (
            <View style={styles.modalOverlay}>
                <ScrollView contentContainerStyle={{ flexGrow: 1, justifyContent: 'center', alignItems: 'center', padding: spacing.lg }}>
                    <View style={styles.modalContent}>
                        <Text style={styles.modalTitle}>Verify Report as {verifyingAs === 'valid' ? 'VALID' : 'INVALID'}</Text>
                        <Text style={styles.modalSubtitle}>
                            {verifyingAs === 'valid' ? 'Confirm that this report is a real incident.' : 'Mark this report as fake or false.'}
                        </Text>

                        {/* Evidence Photo/Video Picker */}
                        <View style={{ width: '100%', marginBottom: spacing.lg }}>
                            <Text style={{ fontSize: fontSize.md, fontWeight: '600', color: COLORS.textPrimary, marginBottom: spacing.xs }}>
                                Evidence (Photo or Video) <Text style={{ color: COLORS.danger }}>*</Text>
                            </Text>

                            <View style={{ flexDirection: 'row', gap: 12, marginBottom: spacing.sm }}>
                                <TouchableOpacity style={styles.mediaButton} onPress={() => pickEvidence('photo')}>
                                    <Ionicons name="image-outline" size={24} color={COLORS.primary} />
                                    <Text style={styles.mediaButtonText}>Select Photo</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={styles.mediaButton} onPress={() => pickEvidence('video')}>
                                    <Ionicons name="videocam-outline" size={24} color={COLORS.primary} />
                                    <Text style={styles.mediaButtonText}>Select Video</Text>
                                </TouchableOpacity>
                            </View>

                            {(selectedPhotoEvidence || selectedVideoEvidence) && (
                                <View style={{ marginTop: spacing.sm }}>
                                    {selectedPhotoEvidence && (
                                        <View style={styles.mediaPreviewContainer}>
                                            <View style={{ flex: 1, flexDirection: 'row', alignItems: 'center' }}>
                                                <View style={styles.mediaThumbnail}>
                                                    <Image source={{ uri: selectedPhotoEvidence.uri }} style={{ width: '100%', height: '100%' }} resizeMode="cover" />
                                                </View>
                                                <View style={{ flex: 1 }}>
                                                    <Text style={styles.mediaName} numberOfLines={1}>{selectedPhotoEvidence.fileName || 'Selected Photo'}</Text>
                                                    <Text style={styles.mediaSize}>{formatBytes(selectedPhotoEvidence.fileSize)}</Text>
                                                </View>
                                            </View>
                                            <TouchableOpacity onPress={removePhotoEvidence} style={{ padding: spacing.sm }}>
                                                <Ionicons name="close-circle" size={24} color={COLORS.danger} />
                                            </TouchableOpacity>
                                        </View>
                                    )}

                                    {selectedVideoEvidence && (
                                        <View style={styles.mediaPreviewContainer}>
                                            <View style={{ flex: 1, flexDirection: 'row', alignItems: 'center' }}>
                                                <View style={[styles.mediaThumbnail, { justifyContent: 'center', alignItems: 'center' }]}>
                                                    <Ionicons name="videocam" size={32} color={COLORS.primary} />
                                                </View>
                                                <View style={{ flex: 1 }}>
                                                    <Text style={styles.mediaName} numberOfLines={1}>{selectedVideoEvidence.fileName || 'Selected Video'}</Text>
                                                    <Text style={styles.mediaSize}>{formatBytes(selectedVideoEvidence.fileSize)}</Text>
                                                </View>
                                            </View>
                                            <TouchableOpacity onPress={removeVideoEvidence} style={{ padding: spacing.sm }}>
                                                <Ionicons name="close-circle" size={24} color={COLORS.danger} />
                                            </TouchableOpacity>
                                        </View>
                                    )}
                                </View>
                            )}
                        </View>

                        <Text style={{ fontSize: fontSize.md, fontWeight: '600', color: COLORS.textPrimary, marginBottom: spacing.xs, alignSelf: 'flex-start' }}>
                            Description
                        </Text>
                        <TextInput
                            style={styles.notesInput}
                            placeholder="Add verification notes (optional)"
                            value={validationNotes}
                            onChangeText={setValidationNotes}
                            multiline
                            numberOfLines={3}
                        />
                        <View style={styles.modalButtons}>
                            <TouchableOpacity style={styles.modalCancelButton} onPress={() => { setShowVerificationModal(false); setVerifyingAs(null); setSelectedPhotoEvidence(null); setSelectedVideoEvidence(null); }}>
                                <Text style={styles.modalCancelText}>Cancel</Text>
                            </TouchableOpacity>
                            <TouchableOpacity
                                style={[styles.modalConfirmButton, { backgroundColor: verifyingAs === 'valid' ? COLORS.success : COLORS.danger, opacity: (selectedPhotoEvidence || selectedVideoEvidence) ? 1 : 0.5 }]}
                                onPress={() => verifyReport(verifyingAs === 'valid')}
                                disabled={actionLoading || (!selectedPhotoEvidence && !selectedVideoEvidence)}
                            >
                                {actionLoading ? <ActivityIndicator color={COLORS.white} size="small" /> : <Text style={styles.modalConfirmText}>Confirm</Text>}
                            </TouchableOpacity>
                        </View>
                    </View>
                </ScrollView>
            </View>
        );
    };

    const renderActions = () => {
        if (!dispatch) return null;
        const { status } = dispatch;
        const isMyDispatch = dispatch.patrol_officer_id && String(dispatch.patrol_officer_id) === userId;

        if (status === 'completed') {
            return (
                <View style={styles.completedBanner}>
                    <Ionicons name="checkmark-circle" size={24} color={COLORS.success} />
                    <Text style={styles.completedText}>Report verified as {dispatch.is_valid ? 'VALID' : 'INVALID'}</Text>
                </View>
            );
        }

        // If someone else accepted, show info banner
        if (status !== 'pending' && status !== 'assigned' && !isMyDispatch) {
            return (
                <View style={[styles.completedBanner, { backgroundColor: '#EFF6FF' }]}>
                    <Ionicons name="information-circle" size={24} color={COLORS.primary} />
                    <Text style={[styles.completedText, { color: COLORS.primary }]}>
                        {dispatch.officer_name || 'An officer'} is handling this dispatch
                    </Text>
                </View>
            );
        }

        return (
            <View style={styles.actionsContainer}>
                {(status === 'pending' || status === 'assigned') && (
                    <TouchableOpacity style={[styles.actionButton, { backgroundColor: COLORS.primary }]} onPress={acceptDispatch} disabled={actionLoading}>
                        {actionLoading ? <ActivityIndicator color={COLORS.white} /> : (
                            <><Ionicons name="checkmark-circle" size={20} color={COLORS.white} /><Text style={styles.actionButtonText}>Accept Dispatch</Text></>
                        )}
                    </TouchableOpacity>
                )}
                {status === 'accepted' && (
                    <TouchableOpacity style={[styles.actionButton, { backgroundColor: COLORS.warning }]} onPress={() => updateStatus('en-route')} disabled={actionLoading}>
                        {actionLoading ? <ActivityIndicator color={COLORS.white} /> : (
                            <><Ionicons name="car" size={20} color={COLORS.white} /><Text style={styles.actionButtonText}>Mark En Route</Text></>
                        )}
                    </TouchableOpacity>
                )}
                {status === 'en_route' && (
                    <TouchableOpacity style={[styles.actionButton, { backgroundColor: COLORS.success }]} onPress={() => updateStatus('arrived')} disabled={actionLoading}>
                        {actionLoading ? <ActivityIndicator color={COLORS.white} /> : (
                            <><Ionicons name="location" size={20} color={COLORS.white} /><Text style={styles.actionButtonText}>Mark Arrived</Text></>
                        )}
                    </TouchableOpacity>
                )}
                {status === 'arrived' && (
                    <>
                        <Text style={styles.verifyPrompt}>Verify this report based on your on-site investigation:</Text>
                        <View style={styles.verifyButtons}>
                            <TouchableOpacity style={[styles.verifyButton, styles.verifyValidButton]} onPress={() => { setVerifyingAs('valid'); setShowVerificationModal(true); }}>
                                <Ionicons name="checkmark-circle" size={24} color={COLORS.white} />
                                <Text style={styles.verifyButtonText}>VALID</Text>
                                <Text style={styles.verifyButtonSubtext}>Real incident</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={[styles.verifyButton, styles.verifyInvalidButton]} onPress={() => { setVerifyingAs('invalid'); setShowVerificationModal(true); }}>
                                <Ionicons name="close-circle" size={24} color={COLORS.white} />
                                <Text style={styles.verifyButtonText}>INVALID</Text>
                                <Text style={styles.verifyButtonSubtext}>Fake report</Text>
                            </TouchableOpacity>
                        </View>
                    </>
                )}
            </View>
        );
    };

    if (loading) {
        return (
            <View style={styles.loadingContainer}>
                <ActivityIndicator size="large" color={COLORS.primary} />
                <Text style={styles.loadingText}>Loading dispatch details...</Text>
            </View>
        );
    }

    return (
        <View style={styles.container}>
            <View style={styles.header}>
                <TouchableOpacity onPress={() => router.back()} style={styles.backBtn}>
                    <Ionicons name="arrow-back" size={24} color={COLORS.textPrimary} />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>Dispatch #{dispatchId}</Text>
                <TouchableOpacity onPress={() => loadDetails(true)} style={styles.refreshBtn}>
                    <Ionicons name="refresh" size={24} color={COLORS.primary} />
                </TouchableOpacity>
            </View>

            <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
                {dispatch && (
                    <View style={styles.statusContainer}>
                        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(dispatch.status) }]}>
                            <Text style={styles.statusText}>{dispatch.status.replace('_', ' ').toUpperCase()}</Text>
                        </View>
                        {dispatch.three_minute_rule_met !== null && (
                            <View style={[styles.ruleIndicator, { backgroundColor: dispatch.three_minute_rule_met ? COLORS.success : COLORS.danger }]}>
                                <Ionicons name={dispatch.three_minute_rule_met ? 'checkmark' : 'close'} size={16} color={COLORS.white} />
                                <Text style={styles.ruleText}>Response time {dispatch.three_minute_rule_met ? 'met' : 'exceeded'}</Text>
                            </View>
                        )}
                    </View>
                )}

                <View style={styles.card}>
                    <View style={styles.cardHeader}>
                        <Ionicons name="alert-circle" size={24} color={COLORS.accent} />
                        <Text style={styles.cardTitle}>Crime Type</Text>
                    </View>
                    <Text style={styles.crimeType}>{formatCrimeType(dispatch?.report?.report_type)}</Text>
                </View>

                {/* Officer Status - visible to all patrol */}
                {dispatch?.officer_name && dispatch.status !== 'pending' && (
                    <View style={[styles.card, { backgroundColor: '#EFF6FF', borderLeftWidth: 4, borderLeftColor: COLORS.primary }]}>
                        <View style={styles.cardHeader}>
                            <Ionicons name="person" size={24} color={COLORS.primary} />
                            <Text style={styles.cardTitle}>Officer Status</Text>
                        </View>
                        <Text style={{ fontSize: fontSize.md, color: COLORS.primary, fontWeight: '600' }}>
                            {dispatch.status === 'arrived'
                                ? `${dispatch.officer_name} has arrived at the location`
                                : dispatch.status === 'en_route'
                                    ? `${dispatch.officer_name} is en route to the location`
                                    : `${dispatch.officer_name} has accepted this dispatch`
                            }
                        </Text>
                    </View>
                )}

                {/* Admin Notes */}
                {dispatch?.notes && (
                    <View style={[styles.card, { backgroundColor: '#F5F3FF', borderLeftWidth: 4, borderLeftColor: '#7C3AED' }]}>
                        <View style={styles.cardHeader}>
                            <Ionicons name="chatbubble-ellipses" size={24} color="#7C3AED" />
                            <Text style={[styles.cardTitle, { color: '#5B21B6' }]}>Dispatch Notes</Text>
                        </View>
                        <Text style={{ fontSize: fontSize.md, color: '#5B21B6', lineHeight: fontSize.md * 1.5, fontStyle: 'italic' }}>
                            {dispatch.notes}
                        </Text>
                    </View>
                )}

                <View style={styles.card}>
                    <View style={styles.cardHeader}>
                        <Ionicons name="location" size={24} color={COLORS.primary} />
                        <Text style={styles.cardTitle}>Location</Text>
                    </View>
                    <Text style={styles.locationText}>{dispatch?.report?.location?.barangay || 'Barangay not specified'}</Text>
                    {dispatch?.report?.location?.reporters_address && (
                        <Text style={styles.addressText}>{dispatch.report.location.reporters_address}</Text>
                    )}
                    <TouchableOpacity style={styles.directionsButton} onPress={openMaps}>
                        <Ionicons name="navigate" size={20} color={COLORS.white} />
                        <Text style={styles.directionsText}>Get Directions</Text>
                    </TouchableOpacity>
                </View>

                {dispatch?.report?.description && (
                    <View style={styles.card}>
                        <View style={styles.cardHeader}>
                            <Ionicons name="document-text" size={24} color={COLORS.primary} />
                            <Text style={styles.cardTitle}>Description</Text>
                        </View>
                        <Text style={styles.descriptionText}>{dispatch.report.description}</Text>
                    </View>
                )}

                <View style={styles.card}>
                    <View style={styles.cardHeader}>
                        <Ionicons name="time" size={24} color={COLORS.primary} />
                        <Text style={styles.cardTitle}>Timeline</Text>
                    </View>
                    {renderTimeline()}
                </View>

                {renderActions()}
                <View style={{ height: 100 }} />
            </ScrollView>

            {renderVerificationModal()}
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: COLORS.background },
    loadingContainer: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.background },
    loadingText: { marginTop: spacing.md, fontSize: fontSize.md, color: COLORS.textSecondary },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: containerPadding.horizontal, paddingTop: containerPadding.vertical + 10, backgroundColor: COLORS.white, borderBottomWidth: 1, borderBottomColor: COLORS.border },
    backBtn: { padding: spacing.sm },
    headerTitle: { fontSize: fontSize.lg, fontWeight: 'bold', color: COLORS.textPrimary },
    refreshBtn: { padding: spacing.sm },
    content: { flex: 1, padding: containerPadding.horizontal },
    statusContainer: { flexDirection: 'row', alignItems: 'center', marginTop: spacing.md, marginBottom: spacing.sm, gap: spacing.sm },
    statusBadge: { paddingHorizontal: spacing.md, paddingVertical: spacing.sm, borderRadius: borderRadius.md },
    statusText: { fontSize: fontSize.sm, fontWeight: 'bold', color: COLORS.white },
    ruleIndicator: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: spacing.sm, paddingVertical: spacing.xs, borderRadius: borderRadius.sm, gap: 4 },
    ruleText: { fontSize: fontSize.xs, fontWeight: '600', color: COLORS.white },
    card: { backgroundColor: COLORS.white, borderRadius: borderRadius.lg, padding: spacing.lg, marginTop: spacing.md, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.1, shadowRadius: 4, elevation: 3 },
    cardHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: spacing.sm },
    cardTitle: { fontSize: fontSize.md, fontWeight: 'bold', color: COLORS.textPrimary, marginLeft: spacing.sm },
    crimeType: { fontSize: fontSize.lg, fontWeight: '600', color: COLORS.accent },
    locationText: { fontSize: fontSize.md, color: COLORS.textPrimary, marginBottom: spacing.xs },
    addressText: { fontSize: fontSize.sm, color: COLORS.textSecondary, marginBottom: spacing.md },
    directionsButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.primary, padding: spacing.md, borderRadius: borderRadius.md, gap: spacing.sm },
    directionsText: { fontSize: fontSize.md, fontWeight: '600', color: COLORS.white },
    descriptionText: { fontSize: fontSize.md, color: COLORS.textPrimary, lineHeight: fontSize.md * 1.5 },
    timeline: { paddingLeft: spacing.sm },
    timelineStep: { flexDirection: 'row', minHeight: 50 },
    timelineIconContainer: { alignItems: 'center', width: 40 },
    timelineIcon: { width: 32, height: 32, borderRadius: 16, backgroundColor: COLORS.border, alignItems: 'center', justifyContent: 'center' },
    timelineIconCompleted: { backgroundColor: COLORS.success },
    timelineIconCurrent: { backgroundColor: COLORS.primary },
    timelineLine: { width: 2, flex: 1, backgroundColor: COLORS.border, marginVertical: 4 },
    timelineLineCompleted: { backgroundColor: COLORS.success },
    timelineContent: { flex: 1, paddingLeft: spacing.sm, paddingBottom: spacing.md },
    timelineLabel: { fontSize: fontSize.sm, color: COLORS.textMuted },
    timelineLabelCompleted: { color: COLORS.textPrimary, fontWeight: '500' },
    timelineTime: { fontSize: fontSize.xs, color: COLORS.textSecondary },
    actionsContainer: { marginTop: spacing.lg, paddingBottom: spacing.lg },
    actionButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', padding: spacing.lg, borderRadius: borderRadius.lg, gap: spacing.sm },
    actionButtonText: { fontSize: fontSize.lg, fontWeight: 'bold', color: COLORS.white },
    verifyPrompt: { fontSize: fontSize.md, color: COLORS.textPrimary, textAlign: 'center', marginBottom: spacing.md },
    verifyButtons: { flexDirection: 'row', gap: spacing.md },
    verifyButton: { flex: 1, alignItems: 'center', padding: spacing.md, borderRadius: borderRadius.lg },
    verifyValidButton: { backgroundColor: COLORS.success },
    verifyInvalidButton: { backgroundColor: COLORS.danger },
    verifyButtonText: { fontSize: fontSize.md, fontWeight: 'bold', color: COLORS.white, marginTop: spacing.xs },
    verifyButtonSubtext: { fontSize: fontSize.sm, color: 'rgba(255,255,255,0.8)' },
    mediaButton: { flex: 1, padding: spacing.md, backgroundColor: '#f8f9fa', borderRadius: borderRadius.md, borderWidth: 1, borderColor: COLORS.border, alignItems: 'center', justifyContent: 'center', flexDirection: 'row', gap: spacing.sm },
    mediaButtonText: { fontSize: fontSize.md, fontWeight: '600', color: COLORS.primary },
    mediaPreviewContainer: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#f8f9fa', borderRadius: borderRadius.md, padding: spacing.sm, borderWidth: 1, borderColor: COLORS.border },
    mediaThumbnail: { width: 60, height: 60, borderRadius: borderRadius.sm, overflow: 'hidden', marginRight: spacing.md, backgroundColor: '#e9ecef', borderWidth: 1, borderColor: COLORS.border },
    mediaName: { fontSize: fontSize.sm, fontWeight: '600', color: COLORS.textPrimary, marginBottom: 2 },
    mediaSize: { fontSize: fontSize.xs, color: COLORS.textSecondary },
    completedBanner: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', backgroundColor: '#D1FAE5', padding: spacing.lg, borderRadius: borderRadius.lg, marginTop: spacing.lg, gap: spacing.sm },
    completedText: { fontSize: fontSize.md, fontWeight: '600', color: COLORS.success },
    modalOverlay: { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', alignItems: 'center', padding: spacing.lg },
    modalContent: { backgroundColor: COLORS.white, borderRadius: borderRadius.lg, padding: spacing.xl, width: '100%', maxWidth: 400 },
    modalTitle: { fontSize: fontSize.xl, fontWeight: 'bold', color: COLORS.textPrimary, textAlign: 'center', marginBottom: spacing.sm },
    modalSubtitle: { fontSize: fontSize.md, color: COLORS.textSecondary, textAlign: 'center', marginBottom: spacing.lg },
    notesInput: { borderWidth: 1, borderColor: COLORS.border, borderRadius: borderRadius.md, padding: spacing.md, fontSize: fontSize.md, color: COLORS.textPrimary, minHeight: 100, textAlignVertical: 'top', marginBottom: spacing.lg },
    modalButtons: { flexDirection: 'row', gap: spacing.md },
    modalCancelButton: { flex: 1, padding: spacing.md, borderRadius: borderRadius.md, backgroundColor: COLORS.border, alignItems: 'center' },
    modalCancelText: { fontSize: fontSize.md, fontWeight: '600', color: COLORS.textPrimary },
    modalConfirmButton: { flex: 1, padding: spacing.md, borderRadius: borderRadius.md, alignItems: 'center' },
    modalConfirmText: { fontSize: fontSize.md, fontWeight: 'bold', color: COLORS.white },
});
