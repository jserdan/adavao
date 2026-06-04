import React, { useState, useEffect } from "react";
import { View, Text, TouchableOpacity, StyleSheet, FlatList, ActivityIndicator, RefreshControl, Modal, ScrollView, Image } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { router, useFocusEffect } from 'expo-router';
import { useUser } from '../../contexts/UserContext';
import { reportService } from '../../services/reportService';
import { BACKEND_URL } from '../../config/backend';
import { onDataRefresh } from '../../services/sseService';

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

interface Report {
  report_id: number;
  title: string;
  report_type: string | string[];
  description: string;
  status: string;
  is_anonymous: boolean;
  date_reported: string;
  created_at: string;
  assigned_station_id?: number;
  location: {
    latitude: number;
    longitude: number;
    barangay: string;
    reporters_address?: string;
  };
  media: Array<{
    media_id: number;
    media_url: string;
    media_type: string;
  }>;
}

const formatDateTimeManila = (dateString?: string) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return 'N/A';
  try {
    return date.toLocaleString('en-US', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    });
  } catch {
    const utcMs = date.getTime() + date.getTimezoneOffset() * 60000;
    const manila = new Date(utcMs + 8 * 3600000);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[manila.getMonth()]} ${manila.getDate()}, ${manila.getFullYear()} ${manila.getHours() % 12 || 12}:${String(manila.getMinutes()).padStart(2, '0')} ${manila.getHours() >= 12 ? 'PM' : 'AM'}`;
  }
};

const getStatusConfig = (status: string) => {
  const configs: Record<string, { color: string; bg: string; icon: string }> = {
    pending: { color: '#d97706', bg: '#fef3c7', icon: 'time-outline' },
    checking: { color: '#d97706', bg: '#fef3c7', icon: 'eye-outline' },
    verified: { color: '#059669', bg: '#d1fae5', icon: 'checkmark-circle-outline' },
    resolved: { color: '#059669', bg: '#d1fae5', icon: 'checkmark-done-outline' },
    investigating: { color: '#2563eb', bg: '#dbeafe', icon: 'search-outline' },
    rejected: { color: '#dc2626', bg: '#fee2e2', icon: 'close-circle-outline' },
  };
  return configs[status.toLowerCase()] || { color: '#6b7280', bg: '#f3f4f6', icon: 'help-circle-outline' };
};

const HistoryItem = React.memo(({ item, onPress }: { item: Report; onPress: (report: Report) => void }) => {
  const statusConfig = getStatusConfig(item.status);
  const crimeTypes = Array.isArray(item.report_type) ? item.report_type : [item.report_type];

  return (
    <TouchableOpacity style={styles.card} onPress={() => onPress(item)} activeOpacity={0.7}>
      <View style={styles.cardHeader}>
        <View style={styles.cardHeaderLeft}>
          <View style={[styles.statusIconContainer, { backgroundColor: statusConfig.bg }]}>
            <Ionicons name={statusConfig.icon as any} size={20} color={statusConfig.color} />
          </View>
          <View style={styles.cardTitleContainer}>
            <Text style={styles.cardTitle} numberOfLines={1}>{item.title}</Text>
            <Text style={styles.cardSubtitle} numberOfLines={1}>{crimeTypes.join(', ')}</Text>
          </View>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: statusConfig.bg }]}>
          <Text style={[styles.statusText, { color: statusConfig.color }]}>
            {item.status.charAt(0).toUpperCase() + item.status.slice(1)}
          </Text>
        </View>
      </View>

      <View style={styles.cardBody}>
        <View style={styles.cardInfoRow}>
          <Ionicons name="location-outline" size={16} color={COLORS.textMuted} />
          <Text style={styles.cardInfoText} numberOfLines={1}>
            {item.location.barangay || 'Location not specified'}
          </Text>
        </View>
        <View style={styles.cardInfoRow}>
          <Ionicons name="calendar-outline" size={16} color={COLORS.textMuted} />
          <Text style={styles.cardInfoText}>{formatDateTimeManila(item.created_at)}</Text>
        </View>
      </View>

      {item.is_anonymous && (
        <View style={styles.anonymousBadge}>
          <Ionicons name="eye-off" size={14} color={COLORS.textMuted} />
          <Text style={styles.anonymousText}>Anonymous</Text>
        </View>
      )}

      <View style={styles.cardFooter}>
        <Text style={styles.viewDetailsText}>View Details</Text>
        <Ionicons name="chevron-forward" size={18} color={COLORS.primary} />
      </View>
    </TouchableOpacity>
  );
});

const history = () => {
  const pageStartTime = React.useRef(Date.now());
  React.useEffect(() => {
    const loadTime = Date.now() - pageStartTime.current;
    console.log(`📊 [History] Page Load Time: ${loadTime}ms`);
  }, []);

  const { user } = useUser();
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedReport, setSelectedReport] = useState<Report | null>(null);
  const [showReportDetail, setShowReportDetail] = useState(false);
  const [decodedAddress, setDecodedAddress] = useState<string>('Loading address...');

  const fetchReports = async (isInitialLoad = false) => {
    if (!user || !user.id) {
      setError('Please log in to view your reports');
      if (isInitialLoad) setLoading(false);
      return;
    }

    try {
      if (isInitialLoad) setError(null);
      const response = await reportService.getUserReports(user.id);

      if (response.success) {
        setReports(prev => {
          const newIds = response.data.map((r: any) => `${r.id}-${r.status}`);
          const oldIds = prev.map(r => `${r.report_id}-${r.status}`);
          if (JSON.stringify(newIds) === JSON.stringify(oldIds)) return prev;
          return response.data;
        });
      } else if (isInitialLoad) {
        setError('Failed to load reports');
      }
    } catch (err) {
      if (isInitialLoad) setError('Failed to load reports. Please try again.');
    } finally {
      if (isInitialLoad) setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchReports(true);
  }, [user]);

  useFocusEffect(
    React.useCallback(() => {
      fetchReports(false);
      const pollInterval = setInterval(() => {
        fetchReports(false);
      }, 60000);
      return () => clearInterval(pollInterval);
    }, [user])
  );

  // SSE-triggered immediate refresh
  useEffect(() => {
    const unsub = onDataRefresh(() => fetchReports(false));
    return unsub;
  }, [user]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchReports();
  };

  const handleReportClick = React.useCallback((report: Report) => {
    setSelectedReport(report);
    setShowReportDetail(true);
    setDecodedAddress('Loading address...');

    if (report.location.latitude !== 0 || report.location.longitude !== 0) {
      fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${report.location.latitude}&lon=${report.location.longitude}&zoom=18&addressdetails=1`)
        .then(response => response.json())
        .then(data => {
          setDecodedAddress(data.display_name || 'Address unavailable');
        })
        .catch(() => {
          setDecodedAddress('Address unavailable');
        });
    } else {
      setDecodedAddress('No location coordinates available');
    }
  }, []);

  const closeReportDetail = React.useCallback(() => {
    setShowReportDetail(false);
    setSelectedReport(null);
    setDecodedAddress('Loading address...');
  }, []);

  const renderItem = React.useCallback(({ item }: { item: Report }) => (
    <HistoryItem item={item} onPress={handleReportClick} />
  ), [handleReportClick]);

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <View style={styles.loadingSpinner}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
        <Text style={styles.loadingText}>Loading your reports...</Text>
      </View>
    );
  }

  const statusConfig = selectedReport ? getStatusConfig(selectedReport.status) : null;

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.push('/')} style={styles.backButton}>
          <Ionicons name="chevron-back" size={24} color={COLORS.primary} />
        </TouchableOpacity>
        <View style={styles.headerCenter}>
          <Text style={styles.headerTitle}>
            <Text style={{ color: COLORS.primary }}>Alert</Text>
            <Text style={{ color: '#000' }}>Davao</Text>
          </Text>
          <Text style={styles.headerSubtitle}>Report History</Text>
        </View>
        <View style={{ width: 40 }} />
      </View>

      {/* Stats Summary */}
      {reports.length > 0 && (
        <View style={styles.statsContainer}>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{reports.length}</Text>
            <Text style={styles.statLabel}>Total</Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statItem}>
            <Text style={[styles.statValue, { color: COLORS.warning }]}>
              {reports.filter(r => r.status.toLowerCase() === 'pending' || r.status.toLowerCase() === 'checking').length}
            </Text>
            <Text style={styles.statLabel}>Pending</Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statItem}>
            <Text style={[styles.statValue, { color: COLORS.success }]}>
              {reports.filter(r => r.status.toLowerCase() === 'resolved' || r.status.toLowerCase() === 'verified').length}
            </Text>
            <Text style={styles.statLabel}>Resolved</Text>
          </View>
        </View>
      )}

      {/* Error Message */}
      {error && (
        <View style={styles.errorContainer}>
          <Ionicons name="alert-circle" size={20} color={COLORS.accent} />
          <Text style={styles.errorText}>{error}</Text>
        </View>
      )}

      {/* Empty State */}
      {!error && reports.length === 0 && (
        <View style={styles.emptyContainer}>
          <View style={styles.emptyIconContainer}>
            <Ionicons name="document-text-outline" size={64} color={COLORS.textMuted} />
          </View>
          <Text style={styles.emptyTitle}>No Reports Yet</Text>
          <Text style={styles.emptyText}>Your submitted reports will appear here</Text>
          <TouchableOpacity style={styles.emptyButton} onPress={() => router.push('/report')}>
            <Ionicons name="add-circle-outline" size={20} color={COLORS.white} />
            <Text style={styles.emptyButtonText}>Submit a Report</Text>
          </TouchableOpacity>
        </View>
      )}

      {/* History List */}
      {!error && reports.length > 0 && (
        <FlatList
          data={reports}
          keyExtractor={(item) => item.report_id.toString()}
          renderItem={renderItem}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          initialNumToRender={5}
          maxToRenderPerBatch={5}
          windowSize={5}
          removeClippedSubviews={true}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[COLORS.primary]} tintColor={COLORS.primary} />
          }
        />
      )}

      {/* Report Detail Modal */}
      <Modal transparent visible={showReportDetail} animationType="fade" onRequestClose={closeReportDetail}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            {/* Modal Header */}
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Report Details</Text>
              <TouchableOpacity onPress={closeReportDetail} style={styles.modalCloseButton}>
                <Ionicons name="close" size={24} color={COLORS.textPrimary} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody} showsVerticalScrollIndicator={false}>
              {selectedReport && statusConfig && (
                <>
                  {/* Status Banner */}
                  <View style={[styles.statusBanner, { backgroundColor: statusConfig.bg }]}>
                    <Ionicons name={statusConfig.icon as any} size={24} color={statusConfig.color} />
                    <View style={styles.statusBannerText}>
                      <Text style={[styles.statusBannerTitle, { color: statusConfig.color }]}>
                        {selectedReport.status.charAt(0).toUpperCase() + selectedReport.status.slice(1)}
                      </Text>
                      {selectedReport.is_anonymous && (
                        <View style={styles.anonymousIndicator}>
                          <Ionicons name="eye-off" size={12} color={COLORS.textMuted} />
                          <Text style={styles.anonymousIndicatorText}>Anonymous Report</Text>
                        </View>
                      )}
                    </View>
                  </View>

                  {/* Title */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailLabel}>Report Title</Text>
                    <Text style={styles.detailValue}>{selectedReport.title}</Text>
                  </View>

                  {/* Crime Type */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailLabel}>Crime Type</Text>
                    <Text style={styles.detailValue}>
                      {Array.isArray(selectedReport.report_type) ? selectedReport.report_type.join(', ') : selectedReport.report_type}
                    </Text>
                  </View>

                  {/* Location */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailLabel}>Location</Text>
                    {selectedReport.location.reporters_address && (
                      <Text style={styles.detailValue}>{selectedReport.location.reporters_address.split(',')[0].trim()}</Text>
                    )}
                    {selectedReport.location.barangay && !selectedReport.location.barangay.startsWith('Lat:') && (
                      <Text style={styles.detailValueSecondary}>{selectedReport.location.barangay.split(',')[0].trim()}</Text>
                    )}
                    {(selectedReport.location.latitude !== 0 || selectedReport.location.longitude !== 0) && (
                      <Text style={styles.detailValueAddress}>{decodedAddress}</Text>
                    )}
                  </View>

                  {/* Description */}
                  <View style={styles.detailSection}>
                    <Text style={styles.detailLabel}>Description</Text>
                    <Text style={styles.detailValueDescription}>{selectedReport.description}</Text>
                  </View>

                  {/* Dates */}
                  <View style={styles.dateRow}>
                    <View style={styles.dateItem}>
                      <Ionicons name="calendar-outline" size={16} color={COLORS.textMuted} />
                      <View>
                        <Text style={styles.dateLabel}>Date of Incident</Text>
                        <Text style={styles.dateValue}>{formatDateTimeManila(selectedReport.date_reported || selectedReport.created_at)}</Text>
                      </View>
                    </View>
                    <View style={styles.dateItem}>
                      <Ionicons name="time-outline" size={16} color={COLORS.textMuted} />
                      <View>
                        <Text style={styles.dateLabel}>Submitted On</Text>
                        <Text style={styles.dateValue}>{formatDateTimeManila(selectedReport.created_at)}</Text>
                      </View>
                    </View>
                  </View>

                  {/* Evidence Notice */}
                  <View style={styles.evidenceNotice}>
                    <Ionicons name="lock-closed-outline" size={18} color={COLORS.textMuted} />
                    <Text style={styles.evidenceNoticeText}>
                      Evidence attachments are only visible to police/admin for privacy and safety.
                    </Text>
                  </View>
                </>
              )}
            </ScrollView>

            {/* Modal Footer */}
            <View style={styles.modalFooter}>
              <TouchableOpacity style={styles.modalCloseBtn} onPress={closeReportDetail}>
                <Text style={styles.modalCloseBtnText}>Close</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
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
    fontSize: 14,
    color: COLORS.textSecondary,
    marginTop: 2,
  },
  statsContainer: {
    flexDirection: 'row',
    backgroundColor: COLORS.white,
    marginHorizontal: 16,
    marginTop: 12,
    borderRadius: 12,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  statItem: {
    flex: 1,
    alignItems: 'center',
  },
  statValue: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  statLabel: {
    fontSize: 12,
    color: COLORS.textMuted,
    marginTop: 4,
  },
  statDivider: {
    width: 1,
    backgroundColor: COLORS.border,
    marginHorizontal: 16,
  },
  errorContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#fee2e2',
    marginHorizontal: 16,
    marginTop: 12,
    borderRadius: 12,
    gap: 8,
  },
  errorText: {
    color: COLORS.accent,
    fontSize: 14,
    flex: 1,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  emptyIconContainer: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: COLORS.white,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 3,
  },
  emptyTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: COLORS.textPrimary,
    marginBottom: 8,
  },
  emptyText: {
    fontSize: 14,
    color: COLORS.textMuted,
    textAlign: 'center',
    marginBottom: 24,
  },
  emptyButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.primary,
    paddingHorizontal: 24,
    paddingVertical: 14,
    borderRadius: 12,
    gap: 8,
  },
  emptyButtonText: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
  listContent: {
    padding: 16,
    paddingBottom: 32,
  },
  card: {
    backgroundColor: COLORS.white,
    borderRadius: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
    overflow: 'hidden',
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    paddingBottom: 12,
  },
  cardHeaderLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    marginRight: 12,
  },
  statusIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  cardTitleContainer: {
    flex: 1,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.textPrimary,
    marginBottom: 2,
  },
  cardSubtitle: {
    fontSize: 13,
    color: COLORS.textSecondary,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  cardBody: {
    paddingHorizontal: 16,
    paddingBottom: 12,
    gap: 6,
  },
  cardInfoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cardInfoText: {
    fontSize: 13,
    color: COLORS.textMuted,
    flex: 1,
  },
  anonymousBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 16,
    paddingVertical: 6,
    gap: 6,
  },
  anonymousText: {
    fontSize: 12,
    color: COLORS.textMuted,
  },
  cardFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
    gap: 4,
  },
  viewDetailsText: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.primary,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: COLORS.white,
    borderRadius: 20,
    width: '100%',
    maxWidth: 500,
    maxHeight: '85%',
    overflow: 'hidden',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },
  modalCloseButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: COLORS.background,
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalBody: {
    paddingHorizontal: 20,
  },
  statusBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderRadius: 12,
    marginTop: 16,
    gap: 12,
  },
  statusBannerText: {
    flex: 1,
  },
  statusBannerTitle: {
    fontSize: 16,
    fontWeight: '700',
  },
  anonymousIndicator: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginTop: 4,
  },
  anonymousIndicatorText: {
    fontSize: 12,
    color: COLORS.textMuted,
  },
  detailSection: {
    marginTop: 20,
  },
  detailLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textMuted,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 6,
  },
  detailValue: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.textPrimary,
  },
  detailValueSecondary: {
    fontSize: 15,
    color: COLORS.textSecondary,
    marginTop: 4,
  },
  detailValueAddress: {
    fontSize: 14,
    color: COLORS.primary,
    fontStyle: 'italic',
    marginTop: 6,
    lineHeight: 20,
  },
  detailValueDescription: {
    fontSize: 15,
    color: COLORS.textPrimary,
    lineHeight: 22,
  },
  dateRow: {
    flexDirection: 'row',
    marginTop: 20,
    gap: 16,
  },
  dateItem: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    backgroundColor: COLORS.background,
    padding: 12,
    borderRadius: 10,
  },
  dateLabel: {
    fontSize: 11,
    color: COLORS.textMuted,
    fontWeight: '600',
  },
  dateValue: {
    fontSize: 13,
    color: COLORS.textPrimary,
    marginTop: 2,
  },
  evidenceNotice: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.background,
    padding: 14,
    borderRadius: 10,
    marginTop: 20,
    marginBottom: 16,
    gap: 10,
  },
  evidenceNoticeText: {
    fontSize: 13,
    color: COLORS.textMuted,
    flex: 1,
    lineHeight: 18,
  },
  modalFooter: {
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
  },
  modalCloseBtn: {
    backgroundColor: COLORS.primary,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
  },
  modalCloseBtnText: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
});

export default history;
