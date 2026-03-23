// handleReport.js
const db = require("./db");
const multer = require("multer");
const path = require("path");
const fs = require("fs");
const crypto = require('crypto');
const { encrypt, decrypt, canDecrypt } = require("./encryptionService");
const { getClientIp, normalizeIp, getUserAgent } = require("./ipUtils");

let cachedUsersPublicColumns = null;

async function getUsersPublicColumns(connection) {
  if (cachedUsersPublicColumns) return cachedUsersPublicColumns;
  const [rows] = await connection.query(
    `SELECT column_name
     FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name = 'users_public'`
  );
  cachedUsersPublicColumns = new Set((rows || []).map((row) => row.column_name));
  return cachedUsersPublicColumns;
}

function resolveGuestPasswordHash() {
  const fallbackHash = '$2a$10$ChB2BiLVrI.NQwozAvx60OMCPgizVc.AMJXWNcqTbonCcLwTrmktS';
  if (process.env.GUEST_REPORTER_PASSWORD_HASH) return process.env.GUEST_REPORTER_PASSWORD_HASH;
  try {
    const bcrypt = require('bcryptjs');
    return bcrypt.hashSync(`guest_no_login_${Date.now()}`, 10);
  } catch (error) {
    console.warn('⚠️ bcryptjs unavailable, using fallback guest password hash');
    return fallbackHash;
  }
}

function sha256Hex(text) {
  return crypto.createHash('sha256').update(String(text || ''), 'utf8').digest('hex');
}

function normalizeDuplicateText(text) {
  return String(text || '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .replace(/[^a-z0-9\s]/g, '')
    .trim();
}

function containsInappropriateLanguage(text) {
  const sensitiveWords = [
    'fuck', 'shit', 'bitch', 'asshole',
    'gago', 'putangina', 'leche', 'bobo', 'tanga', 'piste', 'yawa', 'inutil'
  ];
  const lowered = (text || '').toLowerCase();
  const found = sensitiveWords.find((w) => lowered.includes(w));
  return found ? { found: true, word: found } : { found: false };
}

function getIntEnv(key, fallback) {
  const raw = process.env[key];
  const parsed = Number.parseInt(String(raw ?? ''), 10);
  return Number.isFinite(parsed) ? parsed : fallback;
}

async function enforceReportAbusePrevention(connection, { clientIp, effectiveUserId, needsGuestUser }) {
  const windowMinutes = getIntEnv('REPORT_ABUSE_WINDOW_MINUTES', 10);
  const ipLimit = getIntEnv('REPORT_ABUSE_IP_LIMIT', needsGuestUser ? 3 : 10);
  const userLimit = getIntEnv('REPORT_ABUSE_USER_LIMIT', 5);
  const cooldownSeconds = getIntEnv('REPORT_ABUSE_COOLDOWN_SECONDS', 20);

  try {
    const [recentIpCountRows] = await connection.query(
      `SELECT COUNT(*)::int AS count
       FROM report_ip_tracking
       WHERE ip_address = $1
         AND submitted_at > NOW() - ($2::text || ' minutes')::interval`,
      [clientIp, String(windowMinutes)]
    );

    const recentIpCount = recentIpCountRows?.[0]?.count ?? 0;
    if (recentIpCount >= ipLimit) {
      const error = new Error('Too many reports from this network. Please wait and try again.');
      error.statusCode = 429;
      throw error;
    }

    const [lastSubmissionRows] = await connection.query(
      `SELECT submitted_at
       FROM report_ip_tracking
       WHERE ip_address = $1
       ORDER BY submitted_at DESC
       LIMIT 1`,
      [clientIp]
    );

    const lastSubmittedAt = lastSubmissionRows?.[0]?.submitted_at ? new Date(lastSubmissionRows[0].submitted_at) : null;
    if (lastSubmittedAt) {
      const secondsSinceLast = (Date.now() - lastSubmittedAt.getTime()) / 1000;
      if (secondsSinceLast < cooldownSeconds) {
        const error = new Error('You are submitting reports too quickly. Please wait a moment and try again.');
        error.statusCode = 429;
        throw error;
      }
    }

    if (!needsGuestUser && effectiveUserId) {
      const [recentUserCountRows] = await connection.query(
        `SELECT COUNT(*)::int AS count
         FROM reports
         WHERE user_id = $1
           AND created_at > NOW() - ($2::text || ' minutes')::interval`,
        [effectiveUserId, String(windowMinutes)]
      );

      const recentUserCount = recentUserCountRows?.[0]?.count ?? 0;
      if (recentUserCount >= userLimit) {
        const error = new Error('Too many reports from this account in a short time. Please wait and try again.');
        error.statusCode = 429;
        throw error;
      }
    }
  } catch (e) {
    // If tracking tables don't exist yet or query fails, do not block submissions here.
    // express-rate-limit in server.js still provides baseline protection.
    if (e && (e.statusCode === 429)) throw e;
    console.warn('⚠️ Abuse-prevention DB checks skipped due to error:', e?.message || e);
  }
}

function evaluateCloudinaryModerationDecision(uploadResult) {
  const moderationEnabled = String(process.env.MEDIA_MODERATION_ENABLED || '').trim() === '1';
  const requireModeration = String(process.env.MEDIA_MODERATION_REQUIRE || '').trim() === '1';
  // New default for production: accept report, but hide flagged evidence behind a reveal/spoiler UI.
  // Set MEDIA_MODERATION_PENDING_ACTION=reject if you want strict blocking.
  const pendingAction = (process.env.MEDIA_MODERATION_PENDING_ACTION || 'flag').toLowerCase();

  if (!moderationEnabled) return { decision: 'allow' };
  if (!uploadResult || !uploadResult.success) {
    return requireModeration
      ? { decision: 'reject', reason: 'Upload failed' }
      : { decision: 'allow' };
  }

  const moderation = uploadResult.moderation;
  if (!moderation || !Array.isArray(moderation) || moderation.length === 0) {
    return requireModeration
      ? { decision: 'reject', reason: 'Moderation not available (provider not configured)' }
      : { decision: 'allow' };
  }

  for (const item of moderation) {
    const status = String(item?.status || '').toLowerCase();
    const provider = String(item?.kind || item?.type || item?.provider || '').trim() || null;
    if (status === 'rejected') {
      return { decision: 'flag', reason: 'Media flagged by moderation provider', moderation_status: status, moderation_provider: provider };
    }
    if (status === 'pending') {
      if (pendingAction === 'reject') {
        return { decision: 'reject', reason: 'Media moderation is pending review', moderation_status: status, moderation_provider: provider };
      }
      return { decision: 'flag', reason: 'Media moderation is pending review', moderation_status: status, moderation_provider: provider };
    }

    let response = item?.response;
    if (typeof response === 'string') {
      try { response = JSON.parse(response); } catch { /* ignore */ }
    }

    const labels = response?.ModerationLabels || response?.moderation_labels || response?.labels;
    if (Array.isArray(labels)) {
      const blockedPatterns = (process.env.MEDIA_MODERATION_BLOCK_PATTERNS || 'explicit nudity,nudity,sexual activity,pornography').split(',')
        .map((s) => s.trim().toLowerCase())
        .filter(Boolean);

      for (const label of labels) {
        const name = String(label?.Name || label?.name || label?.label || '').toLowerCase();
        const confidence = Number(label?.Confidence || label?.confidence || label?.score || 0);
        if (!name) continue;
        const matches = blockedPatterns.some((p) => name.includes(p));
        const threshold = Number(process.env.MEDIA_MODERATION_BLOCK_THRESHOLD || 75);
        if (matches && confidence >= threshold) {
          return {
            decision: 'flag',
            reason: `Sensitive content detected: ${label?.Name} (${confidence.toFixed(1)}%)`,
            moderation_status: String(item?.status || '').toLowerCase() || null,
            moderation_provider: provider,
          };
        }
      }
    }
  }

  return { decision: 'allow' };
}

/**
 * Point-in-polygon algorithm (Ray Casting)
 * Checks if a GPS coordinate falls within a polygon boundary
 * @param {number} lat - Point latitude
 * @param {number} lon - Point longitude
 * @param {Array} polygon - Array of [longitude, latitude] coordinates
 * @returns {boolean} - True if point is inside polygon
 */
function isPointInPolygon(lat, lon, polygon) {
  let inside = false;

  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
    const xi = polygon[i][0]; // longitude
    const yi = polygon[i][1]; // latitude
    const xj = polygon[j][0]; // longitude
    const yj = polygon[j][1]; // latitude

    const intersect = ((yi > lat) !== (yj > lat)) &&
      (lon < (xj - xi) * (lat - yi) / (yj - yi) + xi);

    if (intersect) inside = !inside;
  }

  return inside;
}

// Configure multer for file uploads
const storage = multer.diskStorage({
  destination: function (req, file, cb) {
    const uploadDir = path.join(__dirname, "../evidence");
    // Create directory if it doesn't exist
    if (!fs.existsSync(uploadDir)) {
      fs.mkdirSync(uploadDir, { recursive: true });
    }
    cb(null, uploadDir);
  },
  filename: function (req, file, cb) {
    const uniqueSuffix = Date.now() + "-" + Math.round(Math.random() * 1e9);
    const ext = path.extname(file.originalname);
    cb(null, "evidence-" + uniqueSuffix + ext);
  },
});

const upload = multer({
  storage: storage,
  limits: { fileSize: 25 * 1024 * 1024 }, // 25MB limit
  fileFilter: function (req, file, cb) {
    const allowedTypes = /jpeg|jpg|png|gif|mp4|mov|avi/;
    const extname = allowedTypes.test(
      path.extname(file.originalname).toLowerCase()
    );
    const mimetype = allowedTypes.test(file.mimetype);

    if (mimetype && extname) {
      return cb(null, true);
    } else {
      cb(new Error("Only images and videos are allowed!"));
    }
  },
});

// Submit a new report
async function submitReport(req, res) {
  const connection = await db.getConnection();

  try {
    await connection.beginTransaction();

    const {
      title,
      crime_types,
      description,
      incident_date,
      is_anonymous,
      user_id,
      latitude,
      longitude,
      reporters_address,
      barangay,
      barangay_id,
    } = req.body;

    const isAnon = is_anonymous === "1" || is_anonymous === true || is_anonymous === "true";
    const incomingUserId = typeof user_id === 'string' ? user_id.trim() : user_id;
    const files = Array.isArray(req.files) ? req.files : [];
    const hasFiles = files.length > 0;

    // Server-side text moderation (client-side checks can be bypassed)
    const descriptionCheck = containsInappropriateLanguage(description);
    if (descriptionCheck.found) {
      await connection.rollback();
      return res.status(422).json({
        success: false,
        message: 'Sensitive content detected in description',
        errors: { description: [`Please remove inappropriate language ("${descriptionCheck.word}") and try again.`] }
      });
    }

    console.log("📝 Submitting report:", {
      title,
      crime_types,
      description,
      incident_date,
      is_anonymous,
      user_id,
      latitude,
      longitude,
      reporters_address,
      barangay,
      barangay_id,
      hasFiles,
      filesCount: files.length,
      fileDetails: files.slice(0, 6).map(f => ({
        fieldname: f.fieldname,
        originalname: f.originalname,
        encoding: f.encoding,
        mimetype: f.mimetype,
        size: f.size,
        destination: f.destination,
        filename: f.filename,
        path: f.path
      }))
    });

    // Validation
    // Title is now optional - will be auto-generated if not provided
    if (!crime_types || !description || !incident_date || (!incomingUserId && !isAnon)) {
      await connection.rollback();
      return res.status(422).json({
        success: false,
        message: "Missing required fields",
        errors: {
          crime_types: !crime_types ? ["Crime type is required"] : [],
          description: !description ? ["Description is required"] : [],
          incident_date: !incident_date ? ["Incident date is required"] : [],
          user_id: (!incomingUserId && !isAnon) ? ["User ID is required"] : [],
        },
      });
    }

    // If anonymous and no real user_id provided, use a dedicated guest user record.
    // This enables true unregistered complaints while keeping DB foreign keys intact.
    // Always use guest user for anonymous submissions to avoid FK issues
    // and prevent tying anonymous reports to a real user id.
    let effectiveUserId = isAnon ? null : incomingUserId;
    const needsGuestUser = isAnon || (!effectiveUserId || effectiveUserId === '0' || effectiveUserId === 0);

    if (needsGuestUser) {
      const guestEmail = (process.env.GUEST_REPORTER_EMAIL || 'guest@alertdavao.local').toLowerCase();

      const [existingGuest] = await connection.query(
        'SELECT id FROM users_public WHERE LOWER(email) = $1 LIMIT 1',
        [guestEmail]
      );

      if (existingGuest.length > 0) {
        effectiveUserId = existingGuest[0].id;
      } else {
        const availableColumns = await getUsersPublicColumns(connection);
        const guestPassword = resolveGuestPasswordHash();
        const guestContact = process.env.GUEST_REPORTER_CONTACT || '+639000000000';

        const columns = [];
        const placeholders = [];
        const values = [];

        const addValue = (column, value) => {
          if (!availableColumns.has(column)) return;
          columns.push(column);
          values.push(value);
          placeholders.push(`$${values.length}`);
        };

        const addNow = (column) => {
          if (!availableColumns.has(column)) return;
          columns.push(column);
          placeholders.push('NOW()');
        };

        addValue('email', guestEmail);
        addValue('firstname', 'Guest');
        addValue('lastname', 'Reporter');
        addValue('contact', guestContact);
        addValue('password', guestPassword);
        addValue('is_verified', false);
        addValue('email_verified', false);
        addValue('user_role', 'user');
        addValue('role', 'user');
        addValue('email_verified_at', null);
        addNow('created_at');
        addNow('updated_at');

        if (columns.length === 0) {
          throw new Error('users_public schema not detected - cannot create guest user');
        }

        const [guestInsert] = await connection.query(
          `INSERT INTO users_public (${columns.join(', ')})
           VALUES (${placeholders.join(', ')})
           RETURNING id`,
          values
        );
        effectiveUserId = guestInsert[0].id;
      }

      console.log('👤 Using guest reporter user_id:', effectiveUserId);
    }

    // Abuse prevention: DB-backed throttles (in addition to express-rate-limit)
    const clientIp = normalizeIp(getClientIp(req));
    await enforceReportAbusePrevention(connection, { clientIp, effectiveUserId, needsGuestUser });

    // Check if user is restricted (skip guest submissions)
    if (!needsGuestUser && effectiveUserId) {
      const [userCheck] = await connection.query(
        'SELECT restriction_level, total_flags FROM users_public WHERE id = $1',
        [effectiveUserId]
      );

      if (userCheck.length > 0) {
        const { restriction_level, total_flags } = userCheck[0];
        if (restriction_level && restriction_level !== 'none') {
          await connection.rollback();
          return res.status(403).json({
            success: false,
            message: `Your account is ${restriction_level}. You cannot submit reports until the restriction is lifted by an administrator.`,
            restriction: {
              level: restriction_level,
              flags: total_flags
            }
          });
        }
      }
    }

    // ⚠️ CRITICAL: Date Validation - Prevent Future Dates & Enforce 24-Hour Window
    // NOTE: Mobile app sends incident_date without timezone (e.g. "YYYY-MM-DD HH:mm:ss")
    // Render/servers often run in UTC; naive parsing can shift by +8h and falsely mark as "future".
    console.log('🔍 Validating incident date:', incident_date);

    const DEFAULT_LOCAL_TZ_OFFSET = '+08:00'; // Asia/Manila
    const FUTURE_SKEW_MINUTES = Number(process.env.INCIDENT_FUTURE_SKEW_MINUTES || 5);

    const parseIncidentDate = (value) => {
      if (!value) return null;
      if (value instanceof Date) return value;

      const str = String(value).trim();
      if (!str) return null;

      // If ISO-like with timezone (Z or +/-hh:mm), trust native parsing
      const hasExplicitTz = /([zZ]|[+-]\d{2}:?\d{2})$/.test(str);
      if (hasExplicitTz) {
        const d = new Date(str);
        return isNaN(d.getTime()) ? null : d;
      }

      // Handle common app format: "YYYY-MM-DD HH:mm:ss" (or without seconds)
      if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/.test(str)) {
        const iso = str.replace(' ', 'T');
        const withSeconds = iso.length === 16 ? `${iso}:00` : iso;
        const d = new Date(`${withSeconds}${DEFAULT_LOCAL_TZ_OFFSET}`);
        return isNaN(d.getTime()) ? null : d;
      }

      // Fallback
      const d = new Date(str);
      return isNaN(d.getTime()) ? null : d;
    };

    const incidentTime = parseIncidentDate(incident_date);
    const now = new Date();

    // Check if date is valid
    if (!incidentTime || isNaN(incidentTime.getTime())) {
      await connection.rollback();
      return res.status(422).json({
        success: false,
        message: "Invalid incident date format",
        errors: {
          incident_date: ["The incident date is not valid. Please try again."]
        }
      });
    }

    // Check if date is in the future
    const futureDiffMs = incidentTime.getTime() - now.getTime();
    const futureThresholdMs = FUTURE_SKEW_MINUTES * 60 * 1000;

    if (futureDiffMs > futureThresholdMs) {
      const futureMinutes = Math.round(futureDiffMs / 60000);
      console.log(`❌ Future date detected: ${futureMinutes} minutes in the future`);

      await connection.rollback();
      return res.status(422).json({
        success: false,
        message: "Cannot report future incidents",
        errors: {
          incident_date: [
            "You cannot report an incident that hasn't happened yet. " +
            "The incident date is in the future. Please check the date and time."
          ]
        }
      });
    }

    // Check if incident is within 24 hours
    const hoursDiff = (now.getTime() - incidentTime.getTime()) / (1000 * 60 * 60);

    if (hoursDiff > 24) {
      console.log(`❌ Incident too old: ${hoursDiff.toFixed(1)} hours ago`);

      await connection.rollback();
      return res.status(422).json({
        success: false,
        message: "Incident is too old to report through this app",
        errors: {
          incident_date: [
            "This app is for reporting recent incidents within the last 24 hours. " +
            `Your incident was ${Math.floor(hoursDiff)} hours ago. ` +
            "For older incidents, please visit your nearest police station to file a formal report."
          ]
        }
      });
    }

    console.log(`✅ Date validation passed: ${hoursDiff.toFixed(1)} hours ago`);

    // Parse crime types if it's a JSON string
    let crimeTypesArray;
    try {
      crimeTypesArray =
        typeof crime_types === "string"
          ? JSON.parse(crime_types)
          : crime_types;
    } catch (e) {
      crimeTypesArray = [crime_types];
    }
    const crimeTypesNormalized = (Array.isArray(crimeTypesArray) ? crimeTypesArray : [crimeTypesArray])
      .map((c) => (typeof c === 'string' ? c.trim() : String(c ?? '').trim()))
      .filter(Boolean);

    const crimeTypesLower = crimeTypesNormalized.map((c) => c.toLowerCase());
    const reportType = JSON.stringify(crimeTypesNormalized);

    // ⚠️ EVIDENCE REQUIREMENT: Make evidence mandatory (with exceptions)
    // This reduces fake reports and improves validation quality
    const EVIDENCE_OPTIONAL_CRIMES = [
      'threats', 'harassment', 'missing person', 'suspicious activity', 'noise complaint'
    ];

    const requiresEvidence = !crimeTypesLower.some(crime =>
      EVIDENCE_OPTIONAL_CRIMES.includes(crime)
    );

    if (requiresEvidence) {
      if (!hasFiles) {
        console.log('❌ Evidence required but not provided for:', crimeTypesNormalized);
        await connection.rollback();
        return res.status(422).json({
          success: false,
          message: "Evidence is required for this type of report",
          errors: {
            media: [
              "Please upload BOTH a photo AND a video of this incident. " +
              "This helps police verify and respond faster to your report."
            ]
          }
        });
      }

      const hasImage = files.some((f) => (f.mimetype || '').startsWith('image/'));
      const hasVideo = files.some((f) => (f.mimetype || '').startsWith('video/'));

      if (!hasImage && !hasVideo) {
        console.log('❌ Evidence incomplete (need photo or video). Provided:', files.map(f => f.mimetype));
        await connection.rollback();
        return res.status(422).json({
          success: false,
          message: "Incomplete evidence",
          errors: {
            media: ["Please upload a photo OR a video of this incident."]
          }
        });
      }
      console.log('✅ Evidence check passed: hasImage=' + hasImage + ', hasVideo=' + hasVideo);
    }

    if (hasFiles) {
      console.log('✅ Evidence files provided:', files.length);
    } else if (!requiresEvidence) {
      console.log('ℹ️ Evidence optional for this crime type');
    }

    // Duplicate report prevention (anti-spam)
    // Keep it strict and simple: within a time window, block multiple submissions by the same account.
    try {
      const windowMinutes = getIntEnv('DUPLICATE_REPORT_WINDOW_MINUTES', 10);
      const contentHash = sha256Hex(`${reportType}|${normalizeDuplicateText(description)}`);

      if (effectiveUserId) {
        const [recent] = await connection.query(
          `SELECT report_id
           FROM reports
           WHERE user_id = $1
             AND created_at > NOW() - ($2::text || ' minutes')::interval
           ORDER BY created_at DESC
           LIMIT 1`,
          [effectiveUserId, String(windowMinutes)]
        );

        if (recent && recent.length > 0) {
          const err = new Error('You recently submitted a report. Please wait a few minutes before submitting another one.');
          err.statusCode = 409;
          throw err;
        }
      }

      req.__contentHash = contentHash;
    } catch (dupErr) {
      if (dupErr && dupErr.statusCode === 409) throw dupErr;
      console.warn('⚠️ Duplicate detection skipped due to error:', dupErr?.message || dupErr);
    }

    // 🚨 URGENCY SCORING: Calculate priority for police operations (Item #11, #13)
    // Score range: 0-100 (higher = more urgent)
    // When multiple crime types are selected, use the HIGHEST urgency level

    const CRITICAL_CRIMES = [
      'Murder',
      'Homicide',
      'Rape',
      'Sexual Assault'
    ];

    const HIGH_PRIORITY = [
      'Robbery',
      'Physical Injury',
      'Domestic Violence',
      'Missing Person',
      'Harassment'
    ];

    const MEDIUM_PRIORITY = [
      'Theft',
      'Burglary',
      'Break-in',
      'Carnapping',
      'Motornapping',
      'Threats',
      'Fraud',
      'Cybercrime'
    ];

    // 8 Focus Crimes as per DCPO standards
    const FOCUS_CRIMES = [
      'Murder',
      'Homicide',
      'Physical Injury',
      'Rape',
      'Robbery',
      'Theft',
      'Carnapping',
      'Motorcycle Theft',
      'Motornapping'
    ];

    // Check if report contains any Focus Crime
    const isFocusCrime = crimeTypesNormalized.some(crime => {
      const crimeLower = crime.toLowerCase();
      const isFocus = FOCUS_CRIMES.some(fc => crimeLower.includes(fc.toLowerCase()));
      if (isFocus) console.log(`🔴 FOCUS CRIME detected: ${crime}`);
      return isFocus;
    });

    // Check information sufficiency (must return boolean, not the last truthy value)
    const hasSufficientInfo = Boolean(
      description &&
      description.length >= 20 &&
      latitude &&
      longitude
    );

    console.log(`🚨 Priority Flags:`, {
      isFocusCrime,
      hasSufficientInfo,
      isAnonymous: isAnon
    });

    // Keep urgency_score for backward compatibility (deprecated)
    let urgencyScore = 30; // Base score for all reports
    let urgencyLevel = 'LOW';

    // Check each crime type and find the highest priority
    console.log(`📋 Analyzing crime types:`, crimeTypesNormalized);

    const hasCritical = crimeTypesNormalized.some(crime => {
      // Case-insensitive partial match to support labels like "Murder/Homicide"
      const crimeLower = crime.toLowerCase();
      const isCritical = CRITICAL_CRIMES.some(c => crimeLower.includes(c.toLowerCase()));
      if (isCritical) console.log(`🔴 CRITICAL crime detected: ${crime}`);
      return isCritical;
    });

    const hasHigh = crimeTypesNormalized.some(crime => {
      // Case-insensitive partial match for combined labels
      const crimeLower = crime.toLowerCase();
      const isHigh = HIGH_PRIORITY.some(c => crimeLower.includes(c.toLowerCase()));
      if (isHigh) console.log(`🟠 HIGH priority crime detected: ${crime}`);
      return isHigh;
    });

    const hasMedium = crimeTypesNormalized.some(crime => {
      // Case-insensitive partial match for combined labels
      const crimeLower = crime.toLowerCase();
      const isMedium = MEDIUM_PRIORITY.some(c => crimeLower.includes(c.toLowerCase()));
      if (isMedium) console.log(`🟡 MEDIUM priority crime detected: ${crime}`);
      return isMedium;
    });

    // Assign score based on HIGHEST priority found
    if (hasCritical) {
      urgencyScore = 100; // Critical - immediate response
      urgencyLevel = 'CRITICAL';
    } else if (hasHigh) {
      urgencyScore = 75; // High priority
      urgencyLevel = 'HIGH';
    } else if (hasMedium) {
      urgencyScore = 50; // Medium priority
      urgencyLevel = 'MEDIUM';
    }

    // Bonus points for having evidence (+10)
    if (hasFiles) {
      urgencyScore = Math.min(100, urgencyScore + 10);
      console.log(`📸 Evidence bonus: +10 points`);
    }

    // Recency bonus: Reports within 1 hour get +5
    // Note: hoursDiff was calculated earlier for validation
    if (hoursDiff < 1) {
      urgencyScore = Math.min(100, urgencyScore + 5);
      console.log(`⏱️ Recency bonus: +5 points (${hoursDiff.toFixed(1)}h ago)`);
    }

    console.log(`🚨 FINAL Urgency Score: ${urgencyScore}/100 (${urgencyLevel})`);

    // Create location record
    const lat = latitude ? parseFloat(latitude) : 0;
    const lng = longitude ? parseFloat(longitude) : 0;

    // Use provided barangay name or fallback to coordinates
    const barangayName = barangay || (lat !== 0 && lng !== 0 ? `Lat: ${lat}, Lng: ${lng}` : "Unknown");
    const address = reporters_address || null;

    // 🔐 ENCRYPT location data before storing
    console.log("🔐 Encrypting location data (AES-256-CBC):");
    console.log("   Original Barangay:", barangayName);
    const encryptedBarangayName = encrypt(barangayName);
    console.log("   Encrypted Barangay (base64):", encryptedBarangayName.substring(0, 50) + "...");

    const encryptedAddress = address ? encrypt(address) : null;
    if (address) {
      console.log("   Original Address:", address);
      console.log("   Encrypted Address (base64):", encryptedAddress.substring(0, 50) + "...");
    }

    const [locationResult] = await connection.query(
      "INSERT INTO locations (barangay, reporters_address, latitude, longitude, created_at, updated_at) VALUES ($1, $2, $3, $4, NOW(), NOW()) RETURNING location_id",
      [encryptedBarangayName, encryptedAddress, lat, lng]
    );

    const locationId = locationResult[0].location_id;
    console.log("✅ Location created with ID:", locationId);
    console.log("   Barangay:", barangayName);
    console.log("   Address:", address);
    console.log("   Coordinates:", lat, lng);

    // Get the station_id from provided barangay_id or calculate from coordinates
    let stationId = null;

    // Check if this is a cybercrime report - ONLY if explicitly contains "cybercrime"
    // Route to Cybercrime Division globally
    // Check if this is a cybercrime report - Broad check for "cyber" keyword
    // Route to Cybercrime Division globally
    const isCybercrime = crimeTypesArray.some(crime => {
      const normalized = crime.toLowerCase().trim();
      return normalized.includes('cybercrime') ||
        normalized.includes('cyber crime');
    });

    if (isCybercrime) {
      // Route cybercrime reports to Cybercrime Division (global assignment, no location-based assignment)
      try {
        const [cybercrimeStation] = await connection.query(
          `SELECT station_id FROM police_stations WHERE station_name = 'Cybercrime Division' LIMIT 1`
        );
        if (cybercrimeStation && cybercrimeStation.length > 0) {
          stationId = cybercrimeStation[0].station_id;
          console.log("🚨 Cybercrime report detected! Routing to Cybercrime Division (Station ID:", stationId, ")");
        } else {
          console.log("⚠️  Cybercrime Division station not found in database");
        }
      } catch (err) {
        console.log("⚠️  Error routing cybercrime report:", err.message);
      }
    } else {
      // Location-based routing for non-cybercrime reports
      // POLICY UPDATE: Strict Polygon Verification
      // We ignore the frontend provided barangay_id for assignment to ensure
      // the report is physically inside our defined jurisdiction polygons.
      /*
      if (barangay_id) {
        // Use provided barangay_id to get station_id
        try {
          const [barangayResult] = await connection.query(
            `SELECT station_id FROM barangays WHERE barangay_id = $1`,
            [barangay_id]
          );
          if (barangayResult && barangayResult.length > 0) {
            stationId = barangayResult[0].station_id;
            console.log("✅ Station ID assigned from barangay_id:", stationId);
          }
        } catch (err) {
          console.log("⚠️  Could not get station from barangay_id:", err.message);
        }
      }
      */

      // Fallback: Use point-in-polygon to find barangay by GPS coordinates
      // ONLY assign if point falls within a barangay's polygon boundary
      if (!stationId && lat !== 0 && lng !== 0) {
        try {
          console.log(`🗺️  Checking if coordinates (${lat}, ${lng}) fall within any barangay polygon...`);

          // Get all barangays with boundary polygons
          const [barangays] = await connection.query(
            `SELECT barangay_id, barangay_name, station_id, boundary_polygon 
             FROM barangays 
             WHERE boundary_polygon IS NOT NULL`
          );

          let foundBarangay = null;

          // Check each barangay's polygon
          for (const barangay of barangays) {
            if (barangay.boundary_polygon) {
              try {
                const polygon = JSON.parse(barangay.boundary_polygon);
                if (polygon && polygon.coordinates && polygon.coordinates[0]) {
                  // Use point-in-polygon algorithm
                  if (isPointInPolygon(lat, lng, polygon.coordinates[0])) {
                    foundBarangay = barangay;
                    break;
                  }
                }
              } catch (parseError) {
                console.log(`⚠️  Could not parse polygon for ${barangay.barangay_name}`);
              }
            }
          }

          if (foundBarangay && foundBarangay.station_id) {
            stationId = foundBarangay.station_id;
            console.log(`✅ Point falls within ${foundBarangay.barangay_name} polygon → Station ID: ${stationId}`);
          } else {
            console.log("⚠️  Coordinates do not fall within any barangay polygon - report will remain UNASSIGNED");
            stationId = null; // Explicitly set to null - do not assign
          }
        } catch (err) {
          console.log("⚠️  Error checking polygon boundaries:", err.message);
          stationId = null; // On error, leave unassigned
        }
      }
    }

    let reportTitle = title;

    if (!reportTitle || reportTitle.trim() === '') {
      const primaryCrimeType = crimeTypesNormalized[0] || crimeTypesArray[0] || 'Incident'; 
      const dateObj = new Date(incident_date);
      const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const formattedDate = `${monthNames[dateObj.getMonth()]} ${dateObj.getDate()}, ${dateObj.getFullYear()}`;

      reportTitle = `${primaryCrimeType} - ${barangayName} - ${formattedDate}`;
      console.log(`📝 Auto-generated title: "${reportTitle}"`);
    } else {
      console.log(`📝 Using provided title: "${reportTitle}"`);
    }

    // ENCRYPT SENSITIVE DATA (AES-256-CBC)
    // Capstone requirement: encrypt incident reports for confidentiality
    console.log(" Encrypting report description (AES-256-CBC):");
    console.log("  Original Description Length:", description.length, "characters");
    console.log("  Original Description Preview:", description.substring(0, 50) + "...");

    const encryptedDescription = encrypt(description);

    console.log("Encrypted Description (base64):", encryptedDescription.substring(0, 50) + "...");
    console.log("Encrypted Length:", encryptedDescription.length, "characters");
    console.log("Encryption complete - data secured with AES-256-CBC");

    // Note: stationId can be NULL if coordinates don't fall within any polygon
    let reportResult;
    try {
      [reportResult] = await connection.query(
        `INSERT INTO reports
        (user_id, location_id, title, report_type, description, date_reported, status, is_anonymous, assigned_station_id, urgency_score, is_focus_crime, has_sufficient_info, content_hash, created_at, updated_at) 
         VALUES($1, $2, $3, $4, $5, $6, 'pending', $7, $8, $9, $10, $11, $12, NOW(), NOW()) RETURNING report_id`,
        [effectiveUserId, locationId, reportTitle, reportType, encryptedDescription, incident_date, isAnon, stationId, urgencyScore, isFocusCrime, hasSufficientInfo, req.__contentHash || null]
      );
    } catch (e) {
      const message = String(e?.message || '');
      if (message.includes('column "content_hash"')) {
        [reportResult] = await connection.query(
          `INSERT INTO reports
          (user_id, location_id, title, report_type, description, date_reported, status, is_anonymous, assigned_station_id, urgency_score, is_focus_crime, has_sufficient_info, created_at, updated_at) 
           VALUES($1, $2, $3, $4, $5, $6, 'pending', $7, $8, $9, $10, $11, NOW(), NOW()) RETURNING report_id`,
          [effectiveUserId, locationId, reportTitle, reportType, encryptedDescription, incident_date, isAnon, stationId, urgencyScore, isFocusCrime, hasSufficientInfo]
        );
      } else {
        throw e;
      }
    }

    const reportId = reportResult[0].report_id;
    console.log("✅ Report created with ID:", reportId);

    // Handle media uploads if files exist
    let mediaData = [];
    if (hasFiles) {
      console.log(`📸 Processing ${files.length} file upload(s)...`);

      // ☁️ Upload to Cloudinary for persistent storage (and optional moderation)
      const { uploadFile, isConfigured } = require('./cloudinaryService');

      const moderationEnabled = String(process.env.MEDIA_MODERATION_ENABLED || '').trim() === '1';
      const cloudinaryModerationImageRaw = (process.env.CLOUDINARY_MODERATION_IMAGE || process.env.CLOUDINARY_MODERATION || '').trim();
      const cloudinaryModerationVideoRaw = (process.env.CLOUDINARY_MODERATION_VIDEO || '').trim();

      const normalizeModerationValue = (raw) => {
        const list = raw
          ? raw.split(',').map((s) => s.trim()).filter(Boolean)
          : [];
        return list.length === 1 ? list[0] : (list.length > 1 ? list : '');
      };

      const cloudinaryModerationImage = normalizeModerationValue(cloudinaryModerationImageRaw);
      const cloudinaryModerationVideo = normalizeModerationValue(cloudinaryModerationVideoRaw);
      const requireModeration = String(process.env.MEDIA_MODERATION_REQUIRE || '').trim() === '1';

      for (const file of files) {
        console.log("   Original name:", file.originalname);
        console.log("   Saved as:", file.filename);
        console.log("   Size:", file.size, "bytes");
        console.log("   MIME type:", file.mimetype);
        console.log("   Saved to:", file.path);

        let mediaUrl;
        const mediaType = path.extname(file.originalname).substring(1).toLowerCase();

        if (isConfigured()) {
          console.log("☁️ Uploading evidence to Cloudinary...");

          const isImage = (file.mimetype || '').startsWith('image/');
          const isVideo = (file.mimetype || '').startsWith('video/');
          const moderationForThisFile = isImage ? cloudinaryModerationImage : (isVideo ? cloudinaryModerationVideo : '');

          const uploadResult = await uploadFile(file.path, 'evidence', {
            public_id: `evidence_${reportId}_${Date.now()}_${Math.round(Math.random() * 1e9)}`
            ,
            ...(moderationEnabled && moderationForThisFile ? { moderation: moderationForThisFile } : {})
          });

          if (uploadResult.success) {
            // Media moderation decision (only when enabled)
            const decision = evaluateCloudinaryModerationDecision(uploadResult);
            if (decision.decision === 'reject') {
              console.error('🚫 Evidence blocked by moderation:', decision.reason);
              try {
                if (uploadResult.public_id) {
                  const { deleteFile } = require('./cloudinaryService');
                  await deleteFile(uploadResult.public_id);
                }
              } catch (cleanupError) {
                console.warn('⚠️ Failed to cleanup moderated Cloudinary asset:', cleanupError?.message || cleanupError);
              }
              const err = new Error(`Evidence rejected: ${decision.reason}`);
              err.statusCode = 422;
              throw err;
            }

            mediaUrl = uploadResult.url;
            console.log("✅ Evidence uploaded to Cloudinary:", mediaUrl);

            // Persist spoiler/sensitive flags per media item
            file.__moderation = {
              decision: decision?.decision || 'allow',
              is_sensitive: decision?.decision === 'flag',
              moderation_provider: decision?.moderation_provider || null,
              moderation_status: decision?.moderation_status || null,
              moderation_raw: uploadResult.moderation || null,
            };
          } else {
            console.error("❌ Cloudinary upload failed:", uploadResult.error);
            // Fallback to local path (will be lost on redeploy)
            if (moderationEnabled && requireModeration) {
              const err = new Error('Evidence moderation is required but Cloudinary upload failed.');
              err.statusCode = 503;
              throw err;
            }
            mediaUrl = `/evidence/${file.filename}`;
            console.log("⚠️ Using local fallback path:", mediaUrl);
          }
        } else {
          console.log("⚠️ Cloudinary not configured, using local storage (will be lost on redeploy!)");
          if (moderationEnabled && requireModeration) {
            const err = new Error('Evidence moderation is required but Cloudinary is not configured.');
            err.statusCode = 503;
            throw err;
          }
          mediaUrl = `/evidence/${file.filename}`;
        }

        console.log("   Media URL:", mediaUrl);
        console.log("   Media Type:", mediaType);

        const moderationMeta = file.__moderation || null;
        const isSensitive = Boolean(moderationMeta?.is_sensitive);
        const moderationProvider = moderationMeta?.moderation_provider || null;
        const moderationStatus = moderationMeta?.moderation_status || null;
        // Ensure moderation_raw is properly serialized as JSON string for PostgreSQL JSONB column
        const moderationRawData = moderationMeta?.moderation_raw || null;
        const moderationRaw = moderationRawData ? JSON.stringify(moderationRawData) : null;

        try {
          let mediaResult;
          try {
            [mediaResult] = await connection.query(
              "INSERT INTO report_media (report_id, media_url, media_type, is_sensitive, moderation_provider, moderation_status, moderation_raw, created_at, updated_at) VALUES ($1, $2, $3, $4, $5, $6, $7, NOW(), NOW()) RETURNING media_id",
              [reportId, mediaUrl, mediaType, isSensitive, moderationProvider, moderationStatus, moderationRaw]
            );
          } catch (e) {
            // Backward-compatible fallback if DB hasn't been migrated yet.
            const message = String(e?.message || '');
            const missingColumn = message.includes('column "is_sensitive"') || message.includes('column "moderation_provider"') || message.includes('column "moderation_status"') || message.includes('column "moderation_raw"');
            if (!missingColumn) throw e;

            [mediaResult] = await connection.query(
              "INSERT INTO report_media (report_id, media_url, media_type, created_at, updated_at) VALUES ($1, $2, $3, NOW(), NOW()) RETURNING media_id",
              [reportId, mediaUrl, mediaType]
            );
          }

          mediaData.push({
            media_id: mediaResult[0].media_id,
            media_url: mediaUrl,
            media_type: mediaType,
            is_sensitive: isSensitive,
            file_size: file.size,
            original_name: file.originalname,
          });

          console.log("✅ Media uploaded successfully!");
          console.log("   Media ID:", mediaResult[0].media_id);
        } catch (dbError) {
          console.error("❌ Database insertion failed:", dbError);
          throw new Error("Failed to save media to database: " + dbError.message);
        }
      }
    } else {
      console.log("⚠️  No files uploaded with this report");
    }

    // Track IP address for security and audit purposes
    try {
      const clientIp = normalizeIp(getClientIp(req));
      const userAgent = getUserAgent(req);

      console.log("📍 Tracking submission IP address...");
      console.log("   IP Address:", clientIp);
      console.log("   User Agent:", userAgent);

      await connection.query(
        `INSERT INTO report_ip_tracking(report_id, ip_address, user_agent, submitted_at) 
         VALUES($1, $2, $3, NOW())`,
        [reportId, clientIp, userAgent]
      );

      console.log("✅ IP address tracked successfully");
    } catch (ipError) {
      // Don't fail the entire submission if IP tracking fails
      console.error("⚠️  Failed to track IP address:", ipError.message);
      // Continue with the transaction
    }

    // Commit transaction
    await connection.commit();

    // Determine submitter role for attachment visibility in response
    let submitterRole = 'user';
    try {
      if (effectiveUserId) {
        const [rows] = await connection.query(
          'SELECT role FROM users_public WHERE id = $1',
          [effectiveUserId]
        );
        if (rows?.[0]?.role) submitterRole = rows[0].role;
      }
    } catch {
      // ignore role lookup failures
    }

    const canViewAttachments = submitterRole === 'admin' || submitterRole === 'police';
    const responseMedia = canViewAttachments
      ? mediaData
      : mediaData.map((m) => ({
        media_id: m.media_id,
        media_type: m.media_type,
        is_sensitive: Boolean(m.is_sensitive),
      }));

    // Return success response
    res.status(201).json({
      success: true,
      message: "Report submitted successfully",
      data: {
        report_id: reportId,
        title: title,
        report_type: reportType,
        status: "pending",
        is_anonymous: isAnon,
        date_reported: incident_date,
        station_id: stationId,
        location: {
          location_id: locationId,
          latitude: lat,
          longitude: lng,
          barangay: barangay,
          reporters_address: address,
        },
        media: responseMedia,
      },
    });

    console.log("🎉 Report submitted successfully!");
  } catch (error) {
    await connection.rollback();
    console.error("❌ Error submitting report:", error);
    const statusCode = typeof error?.statusCode === 'number' ? error.statusCode : 500;
    res.status(statusCode).json({
      success: false,
      message: statusCode >= 500
        ? 'Failed to submit report'
        : (error?.message || 'Request rejected'),
    });
  } finally {
    connection.release();
  }
}

// Get reports for a specific user
async function getUserReports(req, res) {
  try {
    const { userId } = req.params;
    // 🔒 SECURITY: Get verified role from database, NOT from client input
    const requestingUserId = req.query.requestingUserId || req.body.requestingUserId;
    let userRole = 'user';
    if (requestingUserId) {
      const [requestingUsers] = await db.query(
        "SELECT role FROM users_public WHERE id = $1",
        [requestingUserId]
      );
      if (requestingUsers.length > 0) {
        userRole = requestingUsers[0].role || 'user';
      }
    }

    const [reports] = await db.query(
      `SELECT 
        r.report_id,
      r.title,
      r.report_type:: text,
      r.description,
      r.status,
      r.is_anonymous,
      r.date_reported,
      r.created_at,
      r.assigned_station_id as station_id,
      l.latitude,
      l.longitude,
      l.barangay,
      l.reporters_address,
      ps.station_name,
      ps.address as station_address,
      ps.contact_number,
      COALESCE(
        json_agg(
          json_build_object('media_id', rm.media_id, 'media_url', rm.media_url, 'media_type', rm.media_type, 'is_sensitive', COALESCE(rm.is_sensitive, false))
          ORDER BY rm.media_id
        ) FILTER (WHERE rm.media_id IS NOT NULL),
        '[]'::json
      ) as media
      FROM reports r
      LEFT JOIN locations l ON r.location_id = l.location_id
      LEFT JOIN police_stations ps ON r.assigned_station_id = ps.station_id
      LEFT JOIN report_media rm ON r.report_id = rm.report_id
      WHERE r.user_id = $1 
        AND r.location_id IS NOT NULL 
        AND r.location_id != 0
        AND l.latitude IS NOT NULL 
        AND l.longitude IS NOT NULL
        AND l.latitude != 0
        AND l.longitude != 0
      GROUP BY r.report_id, r.title, r.report_type:: text, r.description, r.status, r.is_anonymous, r.date_reported, r.created_at, r.assigned_station_id, l.latitude, l.longitude, l.barangay, l.reporters_address, ps.station_name, ps.address, ps.contact_number
      ORDER BY r.created_at DESC`,
      [userId]
    );

    const canViewAttachments = userRole === 'admin' || userRole === 'police';

    // Parse media data and decrypt if authorized
    const formattedReports = reports.map((report) => {
      let mediaArray = [];
      if (Array.isArray(report.media)) {
        mediaArray = report.media;
      } else if (typeof report.media === 'string') {
        try { mediaArray = JSON.parse(report.media); } catch { mediaArray = []; }
      }

      if (!canViewAttachments) {
        mediaArray = [];
      }

      // 🔓 DECRYPT sensitive data for authorized roles (police/admin)
      // Regular users can only see their own encrypted data decrypted
      const decryptedDescription = decrypt(report.description);
      const decryptedBarangay = report.barangay ? decrypt(report.barangay) : null;
      const decryptedAddress = report.reporters_address ? decrypt(report.reporters_address) : null;

      // Parse report_type from JSON string to array
      let parsedReportType;
      try {
        parsedReportType = typeof report.report_type === 'string'
          ? JSON.parse(report.report_type)
          : report.report_type;
      } catch (e) {
        parsedReportType = [report.report_type];
      }

      return {
        report_id: report.report_id,
        title: report.title,
        report_type: parsedReportType,
        description: decryptedDescription,
        status: report.status,
        is_anonymous: Boolean(report.is_anonymous),
        date_reported: report.date_reported,
        created_at: report.created_at,
        station_id: report.station_id,
        location: {
          latitude: report.latitude,
          longitude: report.longitude,
          barangay: decryptedBarangay,
          reporters_address: decryptedAddress,
        },
        station: report.station_id ? {
          station_id: report.station_id,
          station_name: report.station_name,
          address: report.station_address,
          contact_number: report.contact_number,
        } : null,
        media: mediaArray,
      };
    });

    res.json({
      success: true,
      data: formattedReports,
    });
  } catch (error) {
    console.error("❌ Error fetching user reports:", error);
    res.status(500).json({
      success: false,
      message: "Failed to fetch reports",
    });
  }
}

// Get all reports
async function getAllReports(req, res) {
  try {
    // 🔒 SECURITY: Get verified role and station from database, NOT from client input
    const requestingUserId = req.query.requestingUserId || req.body.requestingUserId;
    let userRole = 'user';
    let userStationId = null;

    if (requestingUserId) {
      const [requestingUsers] = await db.query(
        "SELECT role, COALESCE(assigned_station_id, station_id) as station_id FROM users_public WHERE id = $1",
        [requestingUserId]
      );
      if (requestingUsers.length > 0) {
        userRole = requestingUsers[0].role || 'user';
        userStationId = requestingUsers[0].station_id;
      }
    }

    // Build query with role-based filtering
    let query = `SELECT 
        r.report_id,
      r.title,
      r.report_type:: text,
      r.description,
      r.status,
      r.is_anonymous,
      r.date_reported,
      r.created_at,
      r.user_id,
      r.assigned_station_id as station_id,
      l.latitude,
      l.longitude,
      l.barangay,
      l.reporters_address,
      u.firstname,
      u.lastname,
      u.email,
      u.role as user_role,
      ps.station_name,
      ps.address as station_address,
      ps.contact_number,
      COALESCE(
        json_agg(
          json_build_object('media_id', rm.media_id, 'media_url', rm.media_url, 'media_type', rm.media_type, 'is_sensitive', COALESCE(rm.is_sensitive, false))
          ORDER BY rm.media_id
        ) FILTER (WHERE rm.media_id IS NOT NULL),
        '[]'::json
      ) as media
      FROM reports r
      LEFT JOIN locations l ON r.location_id = l.location_id
      LEFT JOIN users_public u ON r.user_id = u.id
      LEFT JOIN police_stations ps ON r.assigned_station_id = ps.station_id
      LEFT JOIN report_media rm ON r.report_id = rm.report_id
      WHERE r.location_id IS NOT NULL 
        AND r.location_id != 0
        AND l.latitude IS NOT NULL 
        AND l.longitude IS NOT NULL
        AND l.latitude != 0
        AND l.longitude != 0`;

    // 🚨 ROLE-BASED FILTERING:
    // - Police: Only see reports assigned to their station
    // - Admin: See all reports (assigned and unassigned)
    // - User: See only their own reports
    const queryParams = [];
    if (userRole === 'police' && userStationId) {
      query += ` AND r.assigned_station_id = $1`;
      queryParams.push(userStationId);
      console.log(`👮 Police user requesting reports - filtering by station_id: ${userStationId}`);
    } else if (userRole === 'admin') {
      console.log(`👨‍💼 Admin user requesting reports - showing all reports(assigned and unassigned)`);
    } else if (userRole === 'user' && requestingUserId) {
      query += ` AND r.user_id = $1`;
      queryParams.push(requestingUserId);
      console.log(`👤 Regular user requesting reports - filtering by user_id: ${requestingUserId}`);
    }

    query += ` GROUP BY r.report_id, r.title, r.report_type:: text, r.description, r.status, r.is_anonymous, r.date_reported, r.created_at, r.user_id, r.assigned_station_id, l.latitude, l.longitude, l.barangay, l.reporters_address, u.firstname, u.lastname, u.email, u.role, ps.station_name, ps.address, ps.contact_number ORDER BY r.created_at DESC`;

    const [reports] = await db.query(query, queryParams);

    const canViewAttachments = userRole === 'admin' || userRole === 'police';

    // Parse media data and decrypt for authorized roles
    const formattedReports = reports.map((report) => {
      let mediaArray = [];
      if (Array.isArray(report.media)) {
        mediaArray = report.media;
      } else if (typeof report.media === 'string') {
        try { mediaArray = JSON.parse(report.media); } catch { mediaArray = []; }
      }

      if (!canViewAttachments) {
        mediaArray = [];
      }

      // 🔓 DECRYPT sensitive data ONLY for police/admin roles
      // As per capstone requirement: only authorized personnel can view decrypted incident reports
      let decryptedDescription = report.description;
      let decryptedBarangay = report.barangay;
      let decryptedAddress = report.reporters_address;

      if (canDecrypt(userRole)) {
        console.log(`🔓 Decrypting report ${report.report_id} for authorized role: ${userRole} `);
        decryptedDescription = decrypt(report.description);
        decryptedBarangay = report.barangay ? decrypt(report.barangay) : null;
        decryptedAddress = report.reporters_address ? decrypt(report.reporters_address) : null;
      } else {
        console.log(`🔒 Keeping report ${report.report_id} encrypted for role: ${userRole} `);
      }

      // Parse report_type from JSON string to array
      let parsedReportType;
      try {
        parsedReportType = typeof report.report_type === 'string'
          ? JSON.parse(report.report_type)
          : report.report_type;
      } catch (e) {
        parsedReportType = [report.report_type];
      }

      return {
        report_id: report.report_id,
        title: report.title,
        report_type: parsedReportType,
        description: decryptedDescription,
        status: report.status,
        is_anonymous: Boolean(report.is_anonymous),
        date_reported: report.date_reported,
        created_at: report.created_at,
        station_id: report.station_id,
        user: {
          user_id: report.user_id,
          firstname: report.firstname,
          lastname: report.lastname,
          email: report.email,
        },
        location: {
          latitude: report.latitude,
          longitude: report.longitude,
          barangay: decryptedBarangay,
          reporters_address: decryptedAddress,
        },
        station: report.station_id ? {
          station_id: report.station_id,
          station_name: report.station_name,
          address: report.station_address,
          contact_number: report.contact_number,
        } : null,
        media: mediaArray,
      };
    });

    res.json({
      success: true,
      data: formattedReports,
    });
  } catch (error) {
    console.error("❌ Error fetching all reports:", error);
    res.status(500).json({
      success: false,
      message: "Failed to fetch reports",
    });
  }
}

module.exports = {
  upload,
  submitReport,
  getUserReports,
  getAllReports,
};


