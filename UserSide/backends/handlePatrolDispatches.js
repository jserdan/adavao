const db = require('./db');
const { decrypt, canDecrypt } = require('./encryptionService');

function parseJsonMaybe(value, fallback) {
  if (value == null) return fallback;
  if (Array.isArray(value) || typeof value === 'object') return value;
  if (typeof value !== 'string') return fallback;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

async function assertPatrolOfficer(userId) {
  const [rows] = await db.query(
    "SELECT id, COALESCE(user_role::text, role::text, 'user') AS role, email, assigned_station_id, is_on_duty FROM users_public WHERE id = $1",
    [userId]
  );
  if (!rows || rows.length === 0) {
    const err = new Error('User not found');
    err.statusCode = 404;
    throw err;
  }

  const roleRaw = String(rows[0].role || 'user').toLowerCase();
  const email = String(rows[0].email || '').toLowerCase();
  const stationId = parseInt(rows[0].assigned_station_id || '0', 10);
  const isPatrol = roleRaw.includes('patrol') || email.includes('patrol') || stationId > 0;

  if (!isPatrol) {
    const err = new Error('Unauthorized: Patrol officer role required');
    err.statusCode = 403;
    throw err;
  }

  return rows[0];
}

function computeSecondsDiff(from, to) {
  if (!from || !to) return null;
  const a = new Date(from).getTime();
  const b = new Date(to).getTime();
  if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
  return Math.max(0, Math.round((b - a) / 1000));
}

async function getMyDispatches(req, res) {
  try {
    const userId = req.query.userId || req.headers['x-user-id'] || req.body?.userId;
    if (!userId) return res.status(400).json({ success: false, message: 'userId is required' });

    const officer = await assertPatrolOfficer(userId);
    const stationId = parseInt(officer.assigned_station_id || '0', 10);

    const [rows] = await db.query(
      `SELECT
        d.dispatch_id,
        d.report_id,
        d.station_id,
        d.patrol_officer_id,
        d.status,
        d.dispatched_at,
        d.accepted_at,
        d.declined_at,
        d.en_route_at,
        d.arrived_at,
        d.completed_at,
        d.cancelled_at,
        d.three_minute_rule_met,
        d.three_minute_rule_time,
        d.is_valid,
        d.validation_notes,
        d.validated_at,
        d.notes,
        r.title,
        r.report_type::text AS report_type,
        r.description,
        r.created_at AS report_created_at,
        l.latitude,
        l.longitude,
        l.barangay,
        l.reporters_address,
        u.firstname AS officer_firstname,
        u.lastname AS officer_lastname,
        COALESCE(
          json_agg(
            json_build_object(
              'media_id', rm.media_id,
              'media_url', rm.media_url,
              'media_type', rm.media_type,
              'is_sensitive', COALESCE(rm.is_sensitive, false)
            ) ORDER BY rm.media_id
          ) FILTER (WHERE rm.media_id IS NOT NULL),
          '[]'::json
        ) AS media
      FROM patrol_dispatches d
      JOIN reports r ON d.report_id = r.report_id
      LEFT JOIN locations l ON r.location_id = l.location_id
      LEFT JOIN report_media rm ON r.report_id = rm.report_id
      LEFT JOIN users_public u ON d.patrol_officer_id = u.id
      WHERE d.status NOT IN ('completed', 'cancelled', 'declined')
        AND (d.station_id = $1 OR $1 = 0)
      GROUP BY
        d.dispatch_id,
        d.report_id,
        d.station_id,
        d.patrol_officer_id,
        d.status,
        d.dispatched_at,
        d.accepted_at,
        d.declined_at,
        d.en_route_at,
        d.arrived_at,
        d.completed_at,
        d.cancelled_at,
        d.three_minute_rule_met,
        d.three_minute_rule_time,
        d.is_valid,
        d.validation_notes,
        d.validated_at,
        d.notes,
        r.title,
        r.report_type::text,
        r.description,
        r.created_at,
        l.latitude,
        l.longitude,
        l.barangay,
        l.reporters_address,
        u.firstname,
        u.lastname
      ORDER BY d.dispatched_at DESC`,
      [stationId]
    );
    const formatted = rows.map((row) => {
      const media = parseJsonMaybe(row.media, []);
      const reportType = parseJsonMaybe(row.report_type, [row.report_type]);

      return {
        dispatch_id: row.dispatch_id,
        status: row.status,
        dispatched_at: row.dispatched_at,
        accepted_at: row.accepted_at,
        declined_at: row.declined_at,
        en_route_at: row.en_route_at,
        arrived_at: row.arrived_at,
        completed_at: row.completed_at,
        cancelled_at: row.cancelled_at,
        three_minute_rule_met: row.three_minute_rule_met,
        three_minute_rule_time: row.three_minute_rule_time,
        is_valid: row.is_valid,
        validation_notes: row.validation_notes,
        validated_at: row.validated_at,
        notes: row.notes,
        report: {
          report_id: row.report_id,
          title: row.title,
          report_type: reportType,
          description: canDecrypt('police') ? decrypt(row.description) : row.description,
          created_at: row.report_created_at,
          location: {
            latitude: row.latitude,
            longitude: row.longitude,
            barangay: row.barangay ? decrypt(row.barangay) : null,
            reporters_address: row.reporters_address ? decrypt(row.reporters_address) : null,
          },
          media,
        },
      };
    });

    return res.json({ success: true, data: formatted });
  } catch (e) {
    const statusCode = typeof e?.statusCode === 'number' ? e.statusCode : 500;
    return res.status(statusCode).json({ success: false, message: e?.message || 'Failed to load dispatches' });
  }
}

async function getDispatchDetails(req, res) {
  try {
    const userId = req.query.userId || req.headers['x-user-id'] || req.body?.userId;
    const { dispatchId } = req.params;
    if (!userId) return res.status(400).json({ success: false, message: 'userId is required' });
    if (!dispatchId) return res.status(400).json({ success: false, message: 'dispatchId is required' });

    // Patrol officers can only view dispatches assigned to them
    await assertPatrolOfficer(userId);

    const [rows] = await db.query(
      `SELECT
        d.dispatch_id,
        d.report_id,
        d.station_id,
        d.patrol_officer_id,
        d.status,
        d.dispatched_at,
        d.accepted_at,
        d.declined_at,
        d.en_route_at,
        d.arrived_at,
        d.completed_at,
        d.cancelled_at,
        d.three_minute_rule_met,
        d.three_minute_rule_time,
        d.is_valid,
        d.validation_notes,
        d.validated_at,
        d.notes,
        r.title,
        r.report_type::text AS report_type,
        r.description,
        r.created_at AS report_created_at,
        l.latitude,
        l.longitude,
        l.barangay,
        l.reporters_address,
        u.firstname AS officer_firstname,
        u.lastname AS officer_lastname,
        COALESCE(
          json_agg(
            json_build_object(
              'media_id', rm.media_id,
              'media_url', rm.media_url,
              'media_type', rm.media_type,
              'is_sensitive', COALESCE(rm.is_sensitive, false)
            ) ORDER BY rm.media_id
          ) FILTER (WHERE rm.media_id IS NOT NULL),
          '[]'::json
        ) AS media
      FROM patrol_dispatches d
      JOIN reports r ON d.report_id = r.report_id
      LEFT JOIN locations l ON r.location_id = l.location_id
      LEFT JOIN report_media rm ON r.report_id = rm.report_id
      LEFT JOIN users_public u ON d.patrol_officer_id = u.id
      WHERE d.dispatch_id = $1
      GROUP BY
        d.dispatch_id,
        d.report_id,
        d.station_id,
        d.patrol_officer_id,
        d.status,
        d.dispatched_at,
        d.accepted_at,
        d.declined_at,
        d.en_route_at,
        d.arrived_at,
        d.completed_at,
        d.cancelled_at,
        d.three_minute_rule_met,
        d.three_minute_rule_time,
        d.is_valid,
        d.validation_notes,
        d.validated_at,
        d.notes,
        r.title,
        r.report_type::text,
        r.description,
        r.created_at,
        l.latitude,
        l.longitude,
        l.barangay,
        l.reporters_address,
        u.firstname,
        u.lastname`,
      [dispatchId]
    );

    if (!rows || rows.length === 0) {
      return res.status(404).json({ success: false, message: 'Dispatch not found' });
    }

    const row = rows[0];
    const media = parseJsonMaybe(row.media, []);
    const reportType = parseJsonMaybe(row.report_type, [row.report_type]);

    return res.json({
      success: true,
      data: {
        dispatch_id: row.dispatch_id,
        patrol_officer_id: row.patrol_officer_id,
        status: row.status,
        dispatched_at: row.dispatched_at,
        accepted_at: row.accepted_at,
        declined_at: row.declined_at,
        en_route_at: row.en_route_at,
        arrived_at: row.arrived_at,
        completed_at: row.completed_at,
        cancelled_at: row.cancelled_at,
        three_minute_rule_met: row.three_minute_rule_met,
        three_minute_rule_time: row.three_minute_rule_time,
        is_valid: row.is_valid,
        validation_notes: row.validation_notes,
        validated_at: row.validated_at,
        notes: row.notes,
        officer_name: row.officer_firstname ? `${row.officer_firstname} ${row.officer_lastname}`.trim() : null,
        report: {
          report_id: row.report_id,
          title: row.title,
          report_type: reportType,
          description: decrypt(row.description),
          created_at: row.report_created_at,
          location: {
            latitude: row.latitude,
            longitude: row.longitude,
            barangay: row.barangay ? decrypt(row.barangay) : null,
            reporters_address: row.reporters_address ? decrypt(row.reporters_address) : null,
          },
          media,
        },
      }
    });
  } catch (e) {
    const statusCode = typeof e?.statusCode === 'number' ? e.statusCode : 500;
    return res.status(statusCode).json({ success: false, message: e?.message || 'Failed to load dispatch details' });
  }
}

async function acceptDispatch(req, res) {
  try {
    const userId = req.body?.userId || req.query.userId || req.headers['x-user-id'];
    const { dispatchId } = req.params;
    if (!userId) return res.status(400).json({ success: false, message: 'userId is required' });

    await assertPatrolOfficer(userId);

    const [existing] = await db.query(
      'SELECT dispatch_id, dispatched_at, status, patrol_officer_id FROM patrol_dispatches WHERE dispatch_id = $1',
      [dispatchId]
    );
    if (!existing?.length) return res.status(404).json({ success: false, message: 'Dispatch not found' });

    // Check if already accepted by someone else
    if (existing[0].status !== 'pending' && existing[0].status !== 'assigned') {
      return res.status(400).json({ success: false, message: `Dispatch already ${existing[0].status} by another officer` });
    }

    const dispatchedAt = existing[0].dispatched_at;
    const now = new Date();
    const acceptanceTime = computeSecondsDiff(dispatchedAt, now);

    // Note: three_minute_rule_met is NOT set here — it's determined on arrival
    // (3-min rule = must arrive at scene within 3 minutes of acceptance)
    await db.query(
      `UPDATE patrol_dispatches
       SET patrol_officer_id = $1,
           status = 'accepted',
           accepted_at = NOW(),
           acceptance_time = $2,
           updated_at = NOW()
       WHERE dispatch_id = $3`,
      [userId, acceptanceTime, dispatchId]
    );

    // Update report status to 'investigating' when dispatch is accepted
    await db.query(
      `UPDATE reports SET status = 'investigating', updated_at = NOW()
       WHERE report_id = (SELECT report_id FROM patrol_dispatches WHERE dispatch_id = $1)`,
      [dispatchId]
    );

    return res.json({ success: true, message: 'Dispatch accepted', data: { acceptance_time: acceptanceTime } });
  } catch (e) {
    const statusCode = typeof e?.statusCode === 'number' ? e.statusCode : 500;
    return res.status(statusCode).json({ success: false, message: e?.message || 'Failed to accept dispatch' });
  }
}

async function declineDispatch(req, res) {
  try {
    const userId = req.body?.userId || req.query.userId || req.headers['x-user-id'];
    const { dispatchId } = req.params;
    const { reason } = req.body || {};
    if (!userId) return res.status(400).json({ success: false, message: 'userId is required' });

    await assertPatrolOfficer(userId);

    await db.query(
      `UPDATE patrol_dispatches
       SET status = 'declined',
           declined_at = NOW(),
           decline_reason = $1,
           updated_at = NOW()
       WHERE dispatch_id = $2`,
      [reason || null, dispatchId]
    );

    return res.json({ success: true, message: 'Dispatch declined' });
  } catch (e) {
    const statusCode = typeof e?.statusCode === 'number' ? e.statusCode : 500;
    return res.status(statusCode).json({ success: false, message: e?.message || 'Failed to decline dispatch' });
  }
}

/**
 * Get dispatch history for patrol officer (completed, cancelled, declined)
 */
async function getMyHistory(req, res) {
  try {
    const userId = req.query.userId || req.headers['x-user-id'] || req.body?.userId;
    if (!userId) return res.status(400).json({ success: false, message: 'userId is required' });

    await assertPatrolOfficer(userId);

    const [rows] = await db.query(
      `SELECT
        d.dispatch_id,
        d.report_id,
        d.status,
        d.dispatched_at,
        d.accepted_at,
        d.arrived_at,
        d.completed_at,
        d.cancelled_at,
        d.is_valid,
        d.validation_notes,
        r.report_type::text AS report_type,
        l.barangay
      FROM patrol_dispatches d
      JOIN reports r ON d.report_id = r.report_id
      LEFT JOIN locations l ON r.location_id = l.location_id
      WHERE d.patrol_officer_id = $1
        AND d.status IN ('completed', 'cancelled', 'declined')
      ORDER BY COALESCE(d.completed_at, d.cancelled_at, d.declined_at) DESC
      LIMIT 50`,
      [userId]
    );

    const formatted = rows.map((row) => {
      const reportType = parseJsonMaybe(row.report_type, [row.report_type]);
      const crimeTypes = Array.isArray(reportType) ? reportType : [reportType];

      return {
        dispatch_id: row.dispatch_id,
        report_id: row.report_id,
        crime_type: crimeTypes.join(', '),
        barangay: row.barangay ? decrypt(row.barangay) : 'Unknown location',
        status: row.status,
        dispatched_at: row.dispatched_at,
        responded_at: row.accepted_at,
        arrived_at: row.arrived_at,
        completed_at: row.completed_at || row.cancelled_at,
        verification_status: row.is_valid === true ? 'verified' : (row.is_valid === false ? 'false_report' : null),
        verification_notes: row.validation_notes,
      };
    });

    return res.json({ success: true, data: formatted });
  } catch (e) {
    const statusCode = typeof e?.statusCode === 'number' ? e.statusCode : 500;
    return res.status(statusCode).json({ success: false, message: e?.message || 'Failed to fetch history' });
  }
}

module.exports = {
  getMyDispatches,
  getDispatchDetails,
  acceptDispatch,
  declineDispatch,
  getMyHistory,
};
