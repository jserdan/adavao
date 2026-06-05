const express = require("express");
const cors = require("cors");
const path = require('path');
const multer = require('multer');
const rateLimit = require('express-rate-limit');
const compression = require('compression');
require("dotenv").config();
const db = require('./db');

const app = express();
const PORT = process.env.PORT || 8081;
let io = null;
let liveUpdateSeq = 0;

// Needed for correct req.ip behind proxies (e.g., Render)
app.set('trust proxy', 1);

// Performance: Enable gzip compression for responses
app.use(compression({
  filter: (req, res) => {
    // Don't compress small responses or already compressed
    if (req.headers['x-no-compression']) return false;
    return compression.filter(req, res);
  },
  level: 6, // Balanced compression level
}));

app.use(cors());

// Performance: Limit JSON body size to prevent abuse
app.use(express.json({ limit: '10mb' }));

// Performance: Import server-side cache middleware
const { cacheMiddleware, getCacheStats, clearCache } = require('./cacheMiddleware');
app.use(cacheMiddleware);

// API rate limiting for general endpoints (prevents abuse)
const generalLimiter = rateLimit({
  windowMs: 60 * 1000, // 1 minute
  limit: 300, // 300 requests per minute per IP
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => {
    if (typeof rateLimit.ipKeyGenerator === 'function') return rateLimit.ipKeyGenerator(req);
    return req.ip;
  },
  skip: (req) => {
    // Skip rate limiting for health checks
    return req.path === '/health' || req.path === '/api/health' || req.path === '/api/stream';
  },
  message: {
    success: false,
    message: 'Too many requests. Please slow down.'
  }
});
app.use('/api', generalLimiter);

// Trigger lightweight live-refresh signal for every mobile API request.
// This keeps AdminSide UI updated in near real-time without manual refresh.
app.use('/api', (req, res, next) => {
  const shouldSkip = req.path === '/health' || req.path === '/stream';
  if (shouldSkip) return next();

  res.on('finish', () => {
    if (!io) return;
    if (res.statusCode >= 500) return;

    io.emit('update', {
      version: new Date().toISOString(),
      source: 'request',
      method: req.method,
      path: req.path,
      seq: ++liveUpdateSeq,
    });
  });

  next();
});

// Global rate limit for report submissions (extra safety; DB checks apply inside handler too)
const reportLimiter = rateLimit({
  windowMs: Number(process.env.REPORT_RATE_LIMIT_WINDOW_MS) || 5 * 60 * 1000, // 5 minutes
  limit: Number(process.env.REPORT_RATE_LIMIT_MAX) || 30,
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => {
    if (typeof rateLimit.ipKeyGenerator === 'function') return rateLimit.ipKeyGenerator(req);
    return req.ip;
  },
  message: {
    success: false,
    message: 'Too many report submissions. Please wait a few minutes and try again.'
  }
});

// Serve static files from 'evidence' directory
// DO NOT serve evidence as public static files.
// Evidence is encrypted at rest and must be decrypted only for authorized roles.
app.use('/verifications', express.static(path.join(__dirname, '../verifications'), {
  maxAge: '1d', // Cache static files for 1 day
  etag: true,
}));

const requestLogger = (req, res, next) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.url} from ${req.ip}`);
  next();
};


// Configure multer for evidence files (reports)
const evidenceStorage = multer.diskStorage({
  destination: function (req, file, cb) {
    cb(null, path.join(__dirname, '../evidence'));
  },
  filename: function (req, file, cb) {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    cb(null, 'evidence-' + uniqueSuffix + path.extname(file.originalname));
  }
});

// Configure multer for announcement attachments (uploaded to Cloudinary)
const announcementStorage = multer.diskStorage({
  destination: function (req, file, cb) {
    const dir = path.join(__dirname, '../temp_announcements');
    const fs = require('fs');
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    cb(null, dir);
  },
  filename: function (req, file, cb) {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    cb(null, 'announcement-' + uniqueSuffix + path.extname(file.originalname));
  }
});

const announcementUpload = multer({
  storage: announcementStorage,
  fileFilter: function (req, file, cb) {
    if (file.mimetype.startsWith('image/') || file.mimetype.startsWith('video/') || file.mimetype === 'application/pdf') {
      cb(null, true);
    } else {
      cb(new Error('Only image, video, and PDF files are allowed!'), false);
    }
  },
  limits: {
    fileSize: 10 * 1024 * 1024 // 10MB limit
  }
});

// Configure multer for verification files
const verificationStorage = multer.diskStorage({
  destination: function (req, file, cb) {
    cb(null, path.join(__dirname, '../verifications'));
  },
  filename: function (req, file, cb) {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    cb(null, 'verification-' + uniqueSuffix + path.extname(file.originalname));
  }
});

const evidenceUpload = multer({
  storage: evidenceStorage,
  fileFilter: function (req, file, cb) {
    // Accept only image files
    if (file.mimetype.startsWith('image/')) {
      cb(null, true);
    } else {
      cb(new Error('Only image files are allowed!'), false);
    }
  },
  limits: {
    fileSize: 5 * 1024 * 1024 // 5MB limit
  }
});

const verificationUpload = multer({
  storage: verificationStorage,
  fileFilter: function (req, file, cb) {
    // Accept only image files
    if (file.mimetype.startsWith('image/')) {
      cb(null, true);
    } else {
      cb(new Error('Only image files are allowed!'), false);
    }
  },
  limits: {
    fileSize: 5 * 1024 * 1024 // 5MB limit
  }
});

const handleRegister = require("./handleRegister");
const { handleGoogleLogin, handleGoogleLoginWithToken, handleGoogleOtpVerify, handleGoogleCompleteRegistration } = require("./handleGoogleAuth");
const handleLogin = require("./handleLogin");
const { handleLogout, handlePatrolLogout } = require("./handleLogout");
const { handleVerifyEmail, handleResendVerification } = require("./handleEmailVerification");
const { handleForgotPassword, handleVerifyResetToken, handleResetPassword } = require("./handlePasswordReset");
const { handleChangePassword } = require("./handleChangePassword");
const {
  testConnection,
  getUserById,
  upsertUser,
  updateUserAddress,
  updateUserStation,
  getUserStation
} = require("./handleUserProfile");
const {
  upload: reportUpload,
  submitReport,
  getUserReports,
  getAllReports
} = require("./handleReport");
const {
  // Police Stations
  getAllPoliceStations,
  getPoliceStationById,
  getNearestStations,
  // User Roles
  getUserRoles,
  assignUserRole,
  // Verification
  submitVerification,
  uploadVerificationDocument,
  getVerificationStatus,
  updateVerification,
  approveVerification,
  rejectVerification,
  // Messages
  getUserConversations,
  getMessagesBetweenUsers,
  getUserMessages,
  sendMessage,
  markMessageAsRead,
  markConversationAsRead,
  getUnreadCount,
  updateUserTypingStatus,
  checkUserTypingStatus,
  // Crime Analytics
  getCrimeAnalytics,
  getAllCrimeAnalytics,
  // Crime Forecasts
  getCrimeForecasts,
  // Admin/Police contacts for patrol chat
  getAdminPoliceContacts
} = require("./handleNewFeatures");

// Add this new function for handling notifications
const { getUserNotifications, markNotificationAsRead } = require("./handleNotifications");

// Add flag status checking for debugging
const { checkUserFlagStatus } = require("./handleCheckFlagStatus");

// Add location service handler
const { searchLocation, reverseGeocode, getDistance } = require("./handleLocation");

// Add barangay handler
const { getAllBarangays, getBarangayByCoordinates } = require("./handleBarangays");

// Add police reports handler
const {
  getReportsByStation,
  getReportsByStationAndStatus,
  getStationDashboardStats
} = require("./getPoliceReports");

// Add auto-assign reports handler
const { autoAssignReports } = require("./autoAssignReports");

// Add user restrictions handler
const {
  handleCheckRestrictions,
  handleFlagUser,
  handleGetFlagHistory
} = require("./handleUserRestrictions");

// Add diagnostics handler
const {
  checkPoliceOfficerSetup,
  listAllReportsWithStations,
  debugUserStation
} = require("./handleDiagnostics");

// One-time scripts removed - no longer needed
// 🔐 Secure file serving with decryption (Admin/Police only)
// Files are encrypted at rest and decrypted on-demand for authorized users
const { decryptFile } = require('./encryptionService');
const { getVerifiedUserRole } = require('./authMiddleware');
const { verifyUserRole, requireAuthorizedRole } = require('./authMiddleware');

// Announcements handlers
const { getAnnouncements, getAnnouncementById, uploadAnnouncementFiles } = require('./handleAnnouncements');

// Patrol dispatch handlers
const {
  getMyDispatches,
  getDispatchDetails,
  acceptDispatch,
  declineDispatch,
  getMyHistory,
} = require('./handlePatrolDispatches');

// Dispatch management handlers (location tracking, send to dispatch, verification)
const {
  updatePatrolLocation,
  getAllPatrolLocations,
  getPatrolOfficersByStation,
  sendToDispatch,
  getPendingDispatchesForStation,
  respondToDispatch,
  markEnRoute,
  markArrived,
  verifyReport,
} = require('./handleDispatch');

// User routes (push notifications, duty status)
const userRoutes = require('./routes/user');
app.use('/api/user', userRoutes);

// Announcements API (public, no auth required)
app.get('/api/announcements', getAnnouncements);
app.get('/api/announcements/:id', getAnnouncementById);
app.post('/api/announcements/upload', announcementUpload.array('attachments', 10), uploadAnnouncementFiles);

// Patrol location tracking API
app.post('/api/patrol/location', updatePatrolLocation);
app.get('/api/patrol/locations', verifyUserRole, requireAuthorizedRole, getAllPatrolLocations);
app.get('/api/patrol/officers/:stationId', verifyUserRole, requireAuthorizedRole, getPatrolOfficersByStation);

// Dispatch management API (for police station to send reports to patrol)
app.post('/api/dispatch/send', verifyUserRole, requireAuthorizedRole, sendToDispatch);
// AdminSide sync endpoint (protected by shared secret) - ensures dispatch is created in UserSide backend
app.post('/api/dispatch/admin-sync', async (req, res) => {
  try {
    const expected = process.env.DISPATCH_SYNC_KEY;
    if (expected) {
      const provided = req.headers['x-dispatch-key'];
      if (!provided || String(provided) !== String(expected)) {
        return res.status(401).json({ success: false, message: 'Unauthorized' });
      }
    }

    const { reportId, dispatcherId, notes, patrolOfficerId } = req.body;
    if (!reportId) {
      return res.status(400).json({ success: false, message: 'reportId is required' });
    }

    // Check if dispatch already exists for this report in UserSide DB
    const [existing] = await db.query(
      `SELECT dispatch_id, status, patrol_officer_id FROM patrol_dispatches
       WHERE report_id = $1 AND status NOT IN ('completed','cancelled','declined')`,
      [reportId]
    );

    if (existing && existing.length > 0) {
      // Dispatch already exists - update it if needed
      const d = existing[0];
      if (patrolOfficerId && String(patrolOfficerId) !== String(d.patrol_officer_id)) {
        // Update officer assignment and set status to 'assigned'
        await db.query(
          `UPDATE patrol_dispatches SET patrol_officer_id = $1, status = 'assigned', updated_at = NOW() WHERE dispatch_id = $2`,
          [patrolOfficerId, d.dispatch_id]
        );
      } else if (patrolOfficerId && d.status === 'pending') {
        // Officer already matches but status is still pending - fix it
        await db.query(
          `UPDATE patrol_dispatches SET status = 'assigned', updated_at = NOW() WHERE dispatch_id = $1`,
          [d.dispatch_id]
        );
      }
      // Ensure report status is dispatched
      await db.query(`UPDATE reports SET status = 'dispatched', updated_at = NOW() WHERE report_id = $1 AND status NOT IN ('dispatched','investigating','verified','resolved')`, [reportId]);

      // Still send push notifications to patrol officers
      const [report] = await db.query(
        `SELECT r.assigned_station_id, r.report_type, l.barangay FROM reports r LEFT JOIN locations l ON r.location_id = l.location_id WHERE r.report_id = $1`, [reportId]
      );
      if (report && report.length > 0) {
        const stationId = report[0].assigned_station_id;
        const effectiveOfficerId = patrolOfficerId || d.patrol_officer_id;

        // Send targeted notification to the assigned officer first
        let targetTokens = [];
        if (effectiveOfficerId) {
          const [assignedOfficer] = await db.query(
            `SELECT push_token FROM users_public WHERE id = $1 AND push_token IS NOT NULL`,
            [effectiveOfficerId]
          );
          targetTokens = (assignedOfficer || []).map(o => o.push_token).filter(Boolean);
        }

        // Also notify other on-duty officers at the station (if station is set)
        let stationTokens = [];
        if (stationId) {
          const [officers] = await db.query(
            `SELECT push_token FROM users_public WHERE LOWER(COALESCE(user_role::text, role::text, '')) LIKE '%patrol%' AND assigned_station_id = $1 AND is_on_duty = true AND push_token IS NOT NULL`,
            [stationId]
          );
          stationTokens = (officers || []).map(o => o.push_token).filter(Boolean);
        }

        // Combine unique tokens (assigned officer + station officers)
        const allTokens = [...new Set([...targetTokens, ...stationTokens])];

        if (allTokens.length > 0) {
          const messages = allTokens.map(token => ({
            to: token, sound: 'default', title: '\u{1F6A8} New Dispatch Alert',
            body: `${report[0].report_type || 'Report'} at ${report[0].barangay || 'Unknown location'}`,
            data: { type: 'dispatch', dispatch_id: d.dispatch_id, report_id: reportId },
            priority: 'high', channelId: 'dispatch',
          }));
          fetch('https://exp.host/--/api/v2/push/send', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(messages),
          }).catch(err => console.warn('Push notification error:', err));
          console.log(`📱 Push notifications sent to ${allTokens.length} officers for dispatch #${d.dispatch_id}`);
        }
      }

      console.log(`\u2705 Admin-sync: dispatch already exists #${d.dispatch_id} for report #${reportId}`);
      return res.json({ success: true, message: 'Dispatch already exists, updated', data: { dispatch_id: d.dispatch_id } });
    }

    // No existing dispatch - create one via sendToDispatch
    return await sendToDispatch(req, res);
  } catch (error) {
    console.error('Admin-sync error:', error);
    return res.status(500).json({ success: false, message: 'Failed to sync dispatch', error: error.message });
  }
});
app.get('/api/dispatch/station/:stationId/pending', verifyUserRole, requireAuthorizedRole, getPendingDispatchesForStation);
app.post('/api/dispatch/:dispatchId/respond', verifyUserRole, requireAuthorizedRole, respondToDispatch);
app.post('/api/dispatch/:dispatchId/en-route', verifyUserRole, requireAuthorizedRole, markEnRoute);
app.post('/api/dispatch/:dispatchId/arrived', verifyUserRole, requireAuthorizedRole, markArrived);
app.post('/api/dispatch/:dispatchId/verify', verifyUserRole, requireAuthorizedRole, verificationUpload.single('evidence'), verifyReport);

// Patrol dispatch API (mobile patrol UI)
app.get('/api/patrol/dispatches', verifyUserRole, requireAuthorizedRole, getMyDispatches);
app.get('/api/patrol/dispatches/:dispatchId', verifyUserRole, requireAuthorizedRole, getDispatchDetails);
app.post('/api/patrol/dispatches/:dispatchId/accept', verifyUserRole, requireAuthorizedRole, acceptDispatch);
app.post('/api/patrol/dispatches/:dispatchId/decline', verifyUserRole, requireAuthorizedRole, declineDispatch);
app.get('/api/patrol/history', verifyUserRole, requireAuthorizedRole, getMyHistory);

const fs = require('fs');

// Decrypt and serve evidence files (Admin/Police only)
app.get('/evidence/:filename', async (req, res) => {
  // Set CORS headers explicitly for cross-origin requests
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-User-Id');

  // 🔒 SECURITY: Verify role from database instead of trusting client
  const requestingUserId = req.query.userId || req.headers['x-user-id'];
  const userRole = await getVerifiedUserRole(requestingUserId);

  if (!requestingUserId || (userRole !== 'admin' && userRole !== 'police')) {
    console.log(`🚫 Unauthorized access attempt to evidence by user ${requestingUserId} with role: ${userRole}`);
    return res.status(403).json({
      success: false,
      message: 'Unauthorized: Only admin and police can access report attachments'
    });
  }

  try {
    const filePath = path.join(__dirname, '../evidence', req.params.filename);

    if (!fs.existsSync(filePath)) {
      return res.status(404).json({ success: false, message: 'File not found' });
    }

    console.log(`🔓 Decrypting evidence file for ${userRole}:`, req.params.filename);

    // Read encrypted file
    const encryptedBuffer = fs.readFileSync(filePath);

    // Decrypt the file
    const decryptedBuffer = decryptFile(encryptedBuffer);

    // Determine content type
    const ext = path.extname(req.params.filename).toLowerCase();
    const contentTypes = {
      '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png',
      '.gif': 'image/gif', '.mp4': 'video/mp4', '.mov': 'video/quicktime',
      '.avi': 'video/x-msvideo'
    };

    res.setHeader('Content-Type', contentTypes[ext] || 'application/octet-stream');
    res.setHeader('Cache-Control', 'private, max-age=3600'); // Cache for 1 hour
    res.send(decryptedBuffer);

    console.log('✅ File decrypted and served');
  } catch (error) {
    console.error('❌ Error serving evidence file:', error);
    res.status(500).json({ success: false, message: 'Failed to retrieve file' });
  }
});

// Decrypt and serve verification files (Admin/Police only)
app.get('/verifications/:filename', async (req, res) => {
  // 🔒 SECURITY: Verify role from database instead of trusting client
  const requestingUserId = req.query.userId || req.headers['x-user-id'];
  const userRole = await getVerifiedUserRole(requestingUserId);

  if (userRole !== 'admin' && userRole !== 'police') {
    console.log(`🚫 Unauthorized access attempt to verification by user ${requestingUserId} with role: ${userRole}`);
    return res.status(403).json({
      success: false,
      message: 'Unauthorized: Only admin and police can access verification documents'
    });
  }

  try {
    const filePath = path.join(__dirname, '../verifications', req.params.filename);

    if (!fs.existsSync(filePath)) {
      return res.status(404).json({ success: false, message: 'File not found' });
    }

    console.log(`🔓 Decrypting verification file for ${userRole}:`, req.params.filename);

    // Read encrypted file
    const encryptedBuffer = fs.readFileSync(filePath);

    // Decrypt the file
    const decryptedBuffer = decryptFile(encryptedBuffer);

    // Determine content type
    const ext = path.extname(req.params.filename).toLowerCase();
    const contentTypes = {
      '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png',
      '.gif': 'image/gif', '.pdf': 'application/pdf'
    };

    res.setHeader('Content-Type', contentTypes[ext] || 'application/octet-stream');
    res.send(decryptedBuffer);

    console.log('✅ File decrypted and served');
  } catch (error) {
    console.error('❌ Error serving verification file:', error);
    res.status(500).json({ success: false, message: 'Failed to retrieve file' });
  }
});

// Do not expose legacy evidence uploads publicly.
// If you still have legacy paths referenced, migrate them to `/evidence/:filename`.

// Debug logger - BEFORE multer processes the request
app.use((req, res, next) => {
  console.log("\n" + "=".repeat(50));
  console.log("📨 INCOMING REQUEST:");
  console.log("   Method:", req.method);
  console.log("   URL:", req.url);
  console.log("   Content-Type:", req.headers['content-type']);
  console.log("   Body keys:", Object.keys(req.body));
  console.log("=".repeat(50) + "\n");
  next();
});

// Authentication Routes
// Authentication Routes
const handleGoogleRegister = require("./handleGoogleRegister"); // Import

// ...

// Authentication Routes
app.post("/register", handleRegister); // Registration with email verification
app.post("/google-register", handleGoogleRegister); // Google Registration with Phone
app.post("/login", handleLogin);
app.post("/logout", handleLogout); // Clear server-side session on logout
app.post("/patrol-logout", handlePatrolLogout); // Clear patrol officer session and location

// Email Verification Routes
app.get("/verify-email/:token", handleVerifyEmail); // Verify email with token
app.post("/resend-verification", handleResendVerification); // Resend verification email

// Password Reset Routes
app.post("/forgot-password", handleForgotPassword); // Request password reset
app.get("/verify-reset-token/:token", handleVerifyResetToken); // Verify reset token validity
app.post("/reset-password", handleResetPassword); // Reset password with token
app.post("/api/users/change-password", handleChangePassword); // Authenticated password change with OTP

// OTP endpoints (phone verification / login OTP)
const { sendOtp, verifyOtp } = require('./handleOtp');
app.post('/api/send-otp', sendOtp);
app.post('/api/verify-otp', verifyOtp);

app.post("/google-login", handleGoogleLogin); // Google Sign-In (legacy)
app.post("/google-login-token", handleGoogleLoginWithToken); // Google Sign-In with ID token verification (more secure)
app.post("/api/auth/google", handleGoogleLoginWithToken); // ID token verification endpoint
app.post("/google-verify-otp", handleGoogleOtpVerify); // [NEW] Verify OTP for Google Login
app.post("/google-complete-registration", handleGoogleCompleteRegistration); // Complete Google registration with name

// User Profile API Routes
app.get("/api/test-connection", testConnection);
app.get("/api/users/:id", getUserById);
app.get("/api/users/:userId/station", getUserStation);
app.post("/api/users/upsert", upsertUser);
app.patch("/api/users/:id/address", updateUserAddress);
app.patch("/api/users/:id/station", updateUserStation);

// Report API Routes - with detailed logging
app.post("/api/reports", (req, res, next) => {
  console.log("\n🎯 REPORT ENDPOINT HIT");
  console.log("   Content-Type:", req.headers['content-type']);
  console.log("   Is multipart?", req.headers['content-type']?.includes('multipart'));
  next();
}, reportLimiter, reportUpload.array('media', 6), (req, res, next) => {
  console.log("\n📦 AFTER MULTER:");
  console.log("   req.files exists?", Array.isArray(req.files) && req.files.length > 0);
  console.log("   req.files count:", Array.isArray(req.files) ? req.files.length : 0);
  console.log("   req.files:", req.files);
  console.log("   req.body:", req.body);
  next();
}, submitReport);
app.get("/api/reports", getAllReports);
app.get("/api/reports/user/:userId", getUserReports);

// Report Auto-Assignment Route
app.post("/api/reports/auto-assign", autoAssignReports);

// Police Reports Routes (Station-specific)
// IMPORTANT: More specific routes must come BEFORE less specific routes
app.get("/api/police/station/:stationId/dashboard", verifyUserRole, requireAuthorizedRole, getStationDashboardStats);
app.get("/api/police/station/:stationId/reports/:status", verifyUserRole, requireAuthorizedRole, getReportsByStationAndStatus);
app.get("/api/police/station/:stationId/reports", verifyUserRole, requireAuthorizedRole, getReportsByStation);

// Police Stations API Routes
// IMPORTANT: More specific routes must come BEFORE less specific routes
app.get("/api/police-stations/nearest", getNearestStations);
app.get("/api/police-stations", getAllPoliceStations);
app.get("/api/police-stations/:id", getPoliceStationById);

// User Roles API Routes
app.get("/api/users/:userId/roles", getUserRoles);
app.post("/api/users/roles/assign", assignUserRole);

// Verification API Routes
app.post("/api/verification/submit", submitVerification);
app.post("/api/verification/upload", verificationUpload.single('document'), uploadVerificationDocument);
app.get("/api/verification/status/:userId", getVerificationStatus);
app.put("/api/verification/:verificationId/update", updateVerification);
app.post("/api/verification/approve", approveVerification);
app.post("/api/verification/reject", rejectVerification);

// Messages API Routes
// IMPORTANT: Order matters! More specific routes must come before less specific routes
app.get("/api/messages/conversations/:userId", getUserConversations);
app.get("/api/messages/contacts/admin-police", getAdminPoliceContacts);
app.get("/api/messages/unread/:userId", getUnreadCount);
app.post("/api/messages/typing", updateUserTypingStatus);
app.get("/api/messages/typing-status/:senderId/:receiverId", checkUserTypingStatus);
app.post("/api/messages", sendMessage);
app.patch("/api/messages/conversation/read", markConversationAsRead);
app.patch("/api/messages/:messageId/read", markMessageAsRead);
app.get("/api/messages/:userId/:otherUserId", getMessagesBetweenUsers);
app.get("/api/messages/:userId", getUserMessages);

// Notifications API Routes
app.get("/api/notifications/:userId", getUserNotifications);
app.patch("/api/notifications/:notificationId/read", markNotificationAsRead);

// Crime Analytics API Routes
app.get("/api/analytics", getAllCrimeAnalytics);
app.get("/api/analytics/:locationId", getCrimeAnalytics);

// Crime Forecasts API Routes
app.get("/api/forecasts/:locationId", getCrimeForecasts);

// Location Service API Routes
app.get("/api/location/search", searchLocation);
app.get("/api/location/reverse", reverseGeocode);
app.get("/api/location/distance", getDistance);

// Barangay API Routes
app.get("/api/barangays", getAllBarangays);
app.get("/api/barangay/by-coordinates", getBarangayByCoordinates);

// Diagnostics Routes (for debugging)
app.get("/api/diagnostics/user/:userId", checkPoliceOfficerSetup);
app.get("/api/diagnostics/user/:userId/station", debugUserStation);

// Central error handler (multer + rate-limit + generic)
app.use((err, req, res, next) => {
  if (!err) return next();

  if (err instanceof multer.MulterError) {
    const status = err.code === 'LIMIT_FILE_SIZE' ? 413 : 400;
    return res.status(status).json({
      success: false,
      message: err.code === 'LIMIT_FILE_SIZE'
        ? 'Uploaded file is too large.'
        : 'File upload failed.',
      error: err.code
    });
  }

  // express-rate-limit sets statusCode on the error in some cases
  const statusCode = typeof err.statusCode === 'number' ? err.statusCode : 500;
  return res.status(statusCode).json({
    success: false,
    message: err.message || 'Server error'
  });
});
app.get("/api/diagnostics/reports-all", listAllReportsWithStations);

// User Restrictions/Flagging API Routes
app.get("/api/users/:userId/restrictions", handleCheckRestrictions);
app.get("/api/users/:userId/flags", handleGetFlagHistory);
app.post("/api/users/flag", handleFlagUser);
app.get("/api/debug/user/:userId/flag-status", checkUserFlagStatus);

// Geocoding API Route (legacy, kept for backward compatibility)
app.post("/api/geocode", async (req, res) => {
  try {
    const { address } = req.body;

    if (!address || address.trim().length === 0) {
      return res.status(400).json({ error: "Address is required" });
    }

    const response = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`,
      {
        headers: {
          'User-Agent': 'AlertDavao/2.0 (Crime Reporting App)',
        }
      }
    );

    if (!response.ok) {
      throw new Error(`Nominatim API error: ${response.status}`);
    }

    const data = await response.json();
    res.json(data);
  } catch (error) {
    console.error("Geocoding error:", error);
    res.status(500).json({ error: "Failed to geocode address" });
  }
});

// Google OAuth redirect handler for Expo Go
app.get('/auth/google/callback', (req, res) => {
  const { code, state, error } = req.query;

  console.log('🔔 OAuth callback received:', { code: code ? 'present' : 'missing', state, error });

  if (error) {
    console.error('❌ OAuth error:', error);
    return res.send(`
      <html>
        <body>
          <h2>Authentication failed</h2>
          <p>Error: ${error}</p>
          <script>
            window.close();
          </script>
        </body>
      </html>
    `);
  }

  // Instead of redirecting to exp://, we need to close the browser and let
  // expo-auth-session handle the callback through its internal mechanism
  res.send(`
    <html>
      <head>
        <title>Success</title>
      </head>
      <body>
        <h2>✅ Authentication successful!</h2>
        <p>You can close this window and return to the app.</p>
        <script>
          // Try to close the window
          window.close();
          
          // If that doesn't work, try to go back
          setTimeout(function() {
            if (!window.closed) {
              window.history.back();
            }
          }, 500);
        </script>
      </body>
    </html>
  `);
});

// Health check endpoint for Docker
app.get("/api/health", (req, res) => {
  res.status(200).json({ status: "healthy", timestamp: new Date().toISOString() });
});

// Version endpoint (helps confirm which commit is deployed on Render)
app.get('/api/version', (req, res) => {
  (async () => {
    let dbInfo = null;
    try {
      const db = require('./db');
      const [rows] = await db.query(
        `SELECT
           current_database() AS database,
           current_schema() AS schema,
           inet_server_addr()::text AS server_addr,
           inet_server_port() AS server_port,
           to_regclass('public.users_public') IS NOT NULL AS has_users_public,
           to_regclass('public.user_admin') IS NOT NULL AS has_user_admin,
           to_regclass('public.pending_user_admin_registrations') IS NOT NULL AS has_pending_admin
         `,
        []
      );
      dbInfo = rows?.[0] || null;
    } catch (err) {
      dbInfo = { error: err?.message || String(err) };
    }

    res.status(200).json({
      service: 'userside-backend',
      gitCommit: process.env.RENDER_GIT_COMMIT || process.env.GIT_COMMIT || null,
      timestamp: new Date().toISOString(),
      dbInfo,
    });
  })();
});

// SSE streaming has been replaced with Socket.io

// Debug endpoints (disabled by default). Enable by setting ENABLE_DEBUG_ENDPOINTS=true on Render.
// Guarded by x-debug-key header matching DEBUG_KEY env var.
if (String(process.env.ENABLE_DEBUG_ENDPOINTS || '').toLowerCase() === 'true') {
  const db = require('./db');

  const requireDebugKey = (req, res, next) => {
    const expected = process.env.DEBUG_KEY;
    const provided = req.headers['x-debug-key'];
    if (!expected || !provided || String(provided) !== String(expected)) {
      return res.status(401).json({ success: false, message: 'Unauthorized' });
    }
    next();
  };

  const sanitizeEmail = (email) => {
    if (!email) return '';
    return email.toString().trim().toLowerCase().replace(/[<>'"]/g, '');
  };

  const wildcardToRegex = (pattern) => {
    const escaped = pattern
      .replace(/[.+^${}()|[\]\\]/g, '\\$&')
      .replace(/%/g, '.*')
      .replace(/\*/g, '.*');
    return new RegExp(`^${escaped}$`, 'i');
  };

  const getPatrolPatterns = () => {
    const raw = process.env.TEST_PATROL_EMAIL_PATTERNS || 'dansoypatrol%@mailsac.com';
    return raw.split(',').map(s => s.trim()).filter(Boolean);
  };

  app.post('/api/debug/patrol-lookup', requireDebugKey, async (req, res) => {
    try {
      const email = sanitizeEmail(req.body?.email);
      if (!email) {
        return res.status(400).json({ success: false, message: 'email is required' });
      }

      const patterns = getPatrolPatterns();
      const matchesPattern = patterns.some(p => wildcardToRegex(p).test(email));

      const [publicRows] = await db.query(
        'SELECT email, user_role, email_verified_at FROM users_public WHERE email = $1 LIMIT 1',
        [email]
      );
      const [adminRows] = await db.query(
        'SELECT email, user_role, email_verified_at FROM user_admin WHERE email = $1 LIMIT 1',
        [email]
      );

      return res.status(200).json({
        success: true,
        email,
        testPatrolPatterns: patterns,
        matchesPattern,
        users_public: publicRows[0] || null,
        user_admin: adminRows[0] || null,
      });
    } catch (err) {
      return res.status(500).json({ success: false, message: err.message || 'error' });
    }
  });
}

// === HEALTH CHECK & MONITORING ENDPOINTS ===

// Basic health check (for load balancers, uptime monitors)
app.get('/health', (req, res) => {
  res.status(200).json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
  });
});

// Detailed health check with database connectivity
app.get('/api/health', async (req, res) => {
  try {
    const start = Date.now();
    await db.query('SELECT 1');
    const dbLatency = Date.now() - start;

    res.status(200).json({
      status: 'healthy',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      database: {
        status: 'connected',
        latency: `${dbLatency}ms`,
        pool: db.getPoolStats ? db.getPoolStats() : 'N/A',
      },
      cache: getCacheStats ? getCacheStats() : 'N/A',
      memory: {
        heapUsed: Math.round(process.memoryUsage().heapUsed / 1024 / 1024) + 'MB',
        heapTotal: Math.round(process.memoryUsage().heapTotal / 1024 / 1024) + 'MB',
        rss: Math.round(process.memoryUsage().rss / 1024 / 1024) + 'MB',
      },
    });
  } catch (error) {
    res.status(503).json({
      status: 'unhealthy',
      timestamp: new Date().toISOString(),
      error: error.message,
      database: { status: 'disconnected' },
    });
  }
});

// Cache management endpoint (for admin/debugging)
app.post('/api/admin/cache/clear', (req, res) => {
  const authKey = req.headers['x-admin-key'];
  if (authKey !== process.env.ADMIN_API_KEY && authKey !== process.env.DEBUG_API_KEY) {
    return res.status(401).json({ success: false, message: 'Unauthorized' });
  }

  if (clearCache) clearCache();
  res.json({ success: true, message: 'Cache cleared' });
});

// Temporary endpoint to purge database reports leaving only report ID 18572
app.post('/api/purge-reports-except-18572', async (req, res) => {
  const providedSecret = req.headers['x-custom-secret'];
  if (providedSecret !== 'purge-please-123') {
    return res.status(401).json({ success: false, message: 'Unauthorized' });
  }

  const { Pool } = require('pg');
  const pool = new Pool({
    connectionString: process.env.DATABASE_URL,
    ssl: { rejectUnauthorized: false }
  });

  let client;
  try {
    client = await pool.connect();
    await client.query('BEGIN');
    
    // Delete in order of foreign key dependencies
    const r1 = await client.query('DELETE FROM report_media WHERE report_id != 18572');
    const r2 = await client.query('DELETE FROM patrol_dispatches WHERE report_id != 18572');
    const r3 = await client.query('DELETE FROM report_timelines WHERE report_id != 18572');
    const r4 = await client.query('DELETE FROM messages WHERE report_id IS NOT NULL AND report_id != 18572');
    const r5 = await client.query('DELETE FROM reports WHERE report_id != 18572');
    
    // Clean up locations
    const r6 = await client.query(`
      DELETE FROM locations 
      WHERE location_id NOT IN (
        SELECT DISTINCT location_id 
        FROM reports 
        WHERE location_id IS NOT NULL
      )
    `);
    
    await client.query('COMMIT');
    
    return res.json({
      success: true,
      message: 'Purged successfully, kept report 18572',
      details: {
        mediaDeleted: r1.rowCount,
        dispatchesDeleted: r2.rowCount,
        timelinesDeleted: r3.rowCount,
        messagesDeleted: r4.rowCount,
        reportsDeleted: r5.rowCount,
        locationsDeleted: r6.rowCount
      }
    });
  } catch (error) {
    if (client) await client.query('ROLLBACK');
    console.error('Purge error:', error);
    return res.status(500).json({ success: false, error: error.message });
  } finally {
    if (client) client.release();
    await pool.end();
  }
});

// Catch-all for undefined routes
app.use('*', (req, res) => {
  res.status(404).json({ error: 'Not found', path: req.originalUrl, method: req.method });
});


// Start server
const { runMigrations } = require('./runMigrations');

(async () => {
  console.log("🔄 Initializing DB migrations on startup...");
  try {
    await runMigrations();
  } catch (err) {
    console.warn("⚠️ Migrations failed, but starting server anyway:", err?.message || err);
  }

  const server = app.listen(PORT, "0.0.0.0", async () => {
    console.log(`🚀 Server running at http://localhost:${PORT}`);
    // console.log(`   Local Network: http://${require('ip').address()}:${PORT}`);

    // Purge database reports leaving only report ID 18572 on boot
    console.log("🧹 Running startup database reports purge (keeping only ID 18572)...");
    try {
      const { Pool } = require('pg');
      const pool = new Pool({
        connectionString: process.env.DATABASE_URL,
        ssl: { rejectUnauthorized: false }
      });
      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        const r1 = await client.query('DELETE FROM report_media WHERE report_id != 18572');
        const r2 = await client.query('DELETE FROM patrol_dispatches WHERE report_id != 18572');
        const r3 = await client.query('DELETE FROM report_timelines WHERE report_id != 18572');
        const r4 = await client.query('DELETE FROM messages WHERE report_id IS NOT NULL AND report_id != 18572');
        const r5 = await client.query('DELETE FROM reports WHERE report_id != 18572');
        const r6 = await client.query(`
          DELETE FROM locations 
          WHERE location_id NOT IN (
            SELECT DISTINCT location_id 
            FROM reports 
            WHERE location_id IS NOT NULL
          )
        `);
        await client.query('COMMIT');
        console.log(`✅ Startup database purge completed:`);
        console.log(`   - Media: ${r1.rowCount}, Dispatches: ${r2.rowCount}, Timelines: ${r3.rowCount}, Messages: ${r4.rowCount}, Reports: ${r5.rowCount}, Locations: ${r6.rowCount}`);
      } catch (dbErr) {
        await client.query('ROLLBACK');
        console.warn("⚠️ Database purge transaction failed:", dbErr.message);
      } finally {
        client.release();
        await pool.end();
      }
    } catch (err) {
      console.warn("⚠️ Failed to run startup database purge:", err.message);
    }

    // Auto-reset verification status on startup (as requested)
    // This ensures all users are set to 'unverified' when the server restarts/redeploys
    console.log("🔄 Running centralized verification reset script...");
    try {
      // Import the function dynamically to avoid circular dependencies
      // Check if module exports a function or runs standalone
      const resetAllUsersVerification = require('./reset_verification_status.js');
      if (typeof resetAllUsersVerification === 'function') {
        await resetAllUsersVerification();
        console.log("✅ Verification reset script finished");
      }
    } catch (err) {
      console.warn("⚠️ Verification reset failed:", err.message);
    }

    // Duplicated app.listen logic removed

    // 🔄 KEEP-ALIVE (SELF-PING)
    // Enabled by default. Set KEEP_ALIVE_ENABLED=false to disable.
    const KEEP_ALIVE_ENABLED = String(process.env.KEEP_ALIVE_ENABLED || 'true').toLowerCase() === 'true';

    if (KEEP_ALIVE_ENABLED) {
      // Pings UserSide, AdminSide, and SARIMA API endpoints every interval
      const KEEP_ALIVE_URL = process.env.KEEP_ALIVE_URL || process.env.RENDER_EXTERNAL_URL;
      const ADMIN_KEEP_ALIVE_URL = process.env.ADMIN_KEEP_ALIVE_URL; // AdminSide URL
      const SARIMA_KEEP_ALIVE_URL = process.env.SARIMA_KEEP_ALIVE_URL; // SARIMA API URL
      const KEEP_ALIVE_INTERVAL = parseInt(process.env.KEEP_ALIVE_INTERVAL_MS || '30000', 10); // 30 seconds default

      const pingUrl = async (targetUrl, label) => {
        try {
          const https = require('https');
          const http = require('http');
          const url = new URL(targetUrl);
          const client = url.protocol === 'https:' ? https : http;

          const req = client.get(url.href, { timeout: 30000 }, (res) => {
            console.log(`🏓 ${label}: ${res.statusCode}`);
          });

          req.on('error', () => { }); // Silently ignore - ping attempt still keeps server alive
          req.on('timeout', () => req.destroy());
        } catch (err) {
          // Silently ignore - ping attempt still keeps server alive
        }
      };

      const urls = [];
      if (KEEP_ALIVE_URL) urls.push({ url: KEEP_ALIVE_URL + '/health', label: 'UserSide' });
      if (ADMIN_KEEP_ALIVE_URL) urls.push({ url: ADMIN_KEEP_ALIVE_URL, label: 'AdminSide' });
      if (SARIMA_KEEP_ALIVE_URL) urls.push({ url: SARIMA_KEEP_ALIVE_URL, label: 'SARIMA API' });

      if (urls.length > 0) {
        console.log(`🏓 Auto-ping enabled for ${urls.length} services`);
        urls.forEach(u => console.log(`   Target: ${u.label} (${u.url})`));

        const keepAlive = () => {
          console.log(`⏰ Executing pings at ${new Date().toISOString()}`);
          urls.forEach(u => pingUrl(u.url, u.label));
        };

        // Start pinging after 5 seconds
        console.log("⏳ Starting keep-alive timer (5s delay)...");
        setTimeout(() => {
          keepAlive(); // First ping
          setInterval(keepAlive, KEEP_ALIVE_INTERVAL);
        }, 5000); // Reduced to 5s for faster feedback
      } else {
        console.log('ℹ️ Keep-alive enabled but no target URLs are configured.');
        console.log('   Env vars:', {
          KEEP_ALIVE_URL: process.env.KEEP_ALIVE_URL ? 'set' : 'missing',
          RENDER_EXTERNAL_URL: process.env.RENDER_EXTERNAL_URL ? 'set' : 'missing',
          ADMIN_KEEP_ALIVE_URL: process.env.ADMIN_KEEP_ALIVE_URL ? 'set' : 'missing',
          SARIMA_KEEP_ALIVE_URL: process.env.SARIMA_KEEP_ALIVE_URL ? 'set' : 'missing'
        });
      }
    } else {
      console.log('ℹ️ Keep-alive self-ping disabled (KEEP_ALIVE_ENABLED is not true).');
    }
  });

  // Init Socket.io
  const { Server } = require("socket.io");
  io = new Server(server, {
    cors: {
      origin: "*",
      methods: ["GET", "POST"]
    }
  });

  io.on('connection', (socket) => {
    socket.emit('tick', { ts: Date.now() });

    socket.on('disconnect', () => {
      // client disconnected
    });
  });

  let cachedVersion = null;
  async function getLiveDataVersion() {
    try {
      const db = require('./db');
      const [rows] = await db.query(
        `SELECT GREATEST(
           COALESCE((SELECT MAX(updated_at) FROM reports), '1970-01-01'::timestamp),
           COALESCE((SELECT MAX(updated_at) FROM patrol_dispatches), '1970-01-01'::timestamp),
           COALESCE((SELECT MAX(updated_at) FROM announcements), '1970-01-01'::timestamp),
           COALESCE((SELECT MAX(updated_at) FROM messages), '1970-01-01'::timestamp),
           COALESCE((SELECT MAX(updated_at) FROM users_public), '1970-01-01'::timestamp),
           COALESCE((SELECT MAX(updated_at) FROM locations), '1970-01-01'::timestamp),
           COALESCE((SELECT MAX(updated_at) FROM report_media), '1970-01-01'::timestamp)
         ) AS latest`
      );
      const latest = rows?.[0]?.latest;
      return latest ? new Date(latest).toISOString() : new Date(0).toISOString();
    } catch (err) {
      console.error('⚠️ Error getting live data version:', err.message);
      return cachedVersion || new Date(0).toISOString();
    }
  }

  // Polling for live data updates to emit to all clients
  setInterval(async () => {
    const currentVersion = await getLiveDataVersion();
    if (cachedVersion == null) cachedVersion = currentVersion;

    if (currentVersion !== cachedVersion) {
      cachedVersion = currentVersion;
      io.emit('update', { version: currentVersion });
    }
  }, 1000);
})();