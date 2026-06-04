const express = require('express');
const router = express.Router();
const db = require('../db');

function requireDispatchKey(req, res, next) {
    const expected = process.env.DISPATCH_SYNC_KEY;
    if (!expected) return next();
    const provided = req.headers['x-dispatch-key'];
    if (!provided || String(provided) !== String(expected)) {
        return res.status(401).json({
            success: false,
            message: 'Unauthorized'
        });
    }
    next();
}

/**
 * Save user's push notification token
 */
router.post('/push-token', async (req, res) => {
    try {
        const { user_id, push_token } = req.body;

        if (!user_id || !push_token) {
            return res.status(400).json({
                success: false,
                message: 'User ID and push token are required'
            });
        }

        // Update user's push token
        const query = `
      UPDATE users_public 
      SET push_token = $1, 
          updated_at = NOW() 
      WHERE id = $2
    `;

        await db.query(query, [push_token, user_id]);

        console.log(`✅ Push token saved for user ${user_id}`);

        res.json({
            success: true,
            message: 'Push token saved successfully'
        });

    } catch (error) {
        console.error('Error saving push token:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to save push token',
            error: error.message
        });
    }
});

/**
 * Get user's push notification token (AdminSide fallback)
 */
router.get('/push-token/:userId', requireDispatchKey, async (req, res) => {
    try {
        const userId = req.params.userId;
        if (!userId) {
            return res.status(400).json({ success: false, message: 'userId is required' });
        }

        const [rows] = await db.query(
            'SELECT push_token FROM users_public WHERE id = $1',
            [userId]
        );

        if (!rows || rows.length === 0) {
            return res.status(404).json({ success: false, message: 'User not found' });
        }

        return res.json({ success: true, push_token: rows[0].push_token || null });
    } catch (error) {
        console.error('Error fetching push token:', error);
        return res.status(500).json({
            success: false,
            message: 'Failed to fetch push token',
            error: error.message
        });
    }
});

/**
 * Toggle user's on-duty status (for patrol officers)
 */
router.post('/duty-status', async (req, res) => {
    try {
        const { user_id, is_on_duty } = req.body;

        if (!user_id || typeof is_on_duty !== 'boolean') {
            return res.status(400).json({
                success: false,
                message: 'User ID and duty status are required'
            });
        }

                const query = `
            UPDATE users_public 
            SET is_on_duty = $1, 
                    updated_at = NOW() 
            WHERE id = $2
                AND (
                  LOWER(COALESCE(user_role::text, role::text, '')) LIKE '%patrol%'
                  OR LOWER(COALESCE(email, '')) LIKE '%patrol%'
                  OR COALESCE(assigned_station_id, 0) > 0
                )
            RETURNING id, firstname, lastname, is_on_duty, COALESCE(user_role::text, role::text, 'user') AS role
        `;

        // db.query returns [rows, fields] - destructure properly
        const [rows] = await db.query(query, [is_on_duty, user_id]);

        if (!rows || rows.length === 0) {
            return res.status(404).json({
                success: false,
                message: 'Patrol officer not found'
            });
        }

        console.log(`✅ Duty status updated for officer ${user_id}: ${is_on_duty ? 'ON' : 'OFF'} duty (role: ${rows[0]?.role || 'unknown'})`);

        res.json({
            success: true,
            message: 'Duty status updated successfully',
            data: rows[0]
        });

    } catch (error) {
        console.error('Error updating duty status:', error);
        res.status(500).json({
            success: false,
            message: 'Failed to update duty status',
            error: error.message
        });
    }
});

module.exports = router;
