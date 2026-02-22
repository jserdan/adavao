// handleLogout.js
const db = require("./db");

/**
 * Handle user logout - clears server-side session data
 * Note: Only push_token column exists in users_public schema
 * @param {Object} req - Express request object
 * @param {Object} res - Express response object
 */
const handleLogout = async (req, res) => {
  const { userId, email } = req.body;

  if (!userId && !email) {
    return res.status(400).json({
      success: false,
      message: "User ID or email is required"
    });
  }

  try {
    // Clear push_token and set off-duty on logout
    if (userId) {
      await db.query(
        `UPDATE users_public 
         SET push_token = NULL, is_on_duty = false
         WHERE id = $1`,
        [userId]
      );
    } else if (email) {
      await db.query(
        `UPDATE users_public 
         SET push_token = NULL, is_on_duty = false
         WHERE LOWER(TRIM(email)) = LOWER(TRIM($1))`,
        [email]
      );
    }

    console.log(`✅ User logged out: ${userId || email}`);

    return res.status(200).json({
      success: true,
      message: "Logged out successfully"
    });
  } catch (error) {
    console.error("❌ Logout error:", error);
    return res.status(500).json({
      success: false,
      message: "Failed to logout"
    });
  }
};

/**
 * Handle patrol officer logout - also clears patrol location tracking
 * Note: Uses try-catch for individual queries to handle missing columns gracefully
 * @param {Object} req - Express request object
 * @param {Object} res - Express response object
 */
const handlePatrolLogout = async (req, res) => {
  const { odId, odEmail } = req.body;

  if (!odId && !odEmail) {
    return res.status(400).json({
      success: false,
      message: "Officer ID or email is required"
    });
  }

  try {
    let officerId = odId;

    if (!officerId && odEmail) {
      // Check in users_public first (new schema)
      const [publicOfficer] = await db.query(
        `SELECT id FROM users_public WHERE LOWER(TRIM(email)) = LOWER(TRIM($1))`,
        [odEmail]
      );
      if (publicOfficer.length > 0) {
        officerId = publicOfficer[0].id;
      } else {
        // Fallback to user_admin
        const [adminOfficer] = await db.query(
          `SELECT id FROM user_admin WHERE LOWER(TRIM(email)) = LOWER(TRIM($1))`,
          [odEmail]
        );
        if (adminOfficer.length > 0) {
          officerId = adminOfficer[0].id;
        }
      }
    }

    if (officerId) {
      // Update users_public (new schema)
      try {
        await db.query(
          `UPDATE users_public 
           SET is_on_duty = false, push_token = NULL
           WHERE id = $1`,
          [officerId]
        );
      } catch (e) {
        console.warn("⚠️ Could not update users_public is_on_duty:", e.message);
      }
      // Try to update user_admin - gracefully handle missing columns
      try {
        // First try with is_online only (more likely to exist)
        await db.query(
          `UPDATE user_admin 
           SET is_online = false
           WHERE id = $1`,
          [officerId]
        );
      } catch (adminUpdateError) {
        // If is_online doesn't exist, just log and continue
        console.warn("⚠️ Could not update user_admin is_online:", adminUpdateError.message);
      }

      // Clear active patrol location
      try {
        await db.query(
          `UPDATE patrol_locations 
           SET is_active = false, 
               last_updated = NOW() 
           WHERE officer_id = $1`,
          [officerId]
        );
      } catch (patrolError) {
        // patrol_locations table might not exist
        console.warn("⚠️ Could not update patrol_locations:", patrolError.message);
      }
    }

    console.log(`✅ Patrol officer logged out: ${odId || odEmail}`);

    return res.status(200).json({
      success: true,
      message: "Patrol logged out successfully"
    });
  } catch (error) {
    console.error("❌ Patrol logout error:", error);
    return res.status(500).json({
      success: false,
      message: "Failed to logout"
    });
  }
};

module.exports = { handleLogout, handlePatrolLogout };
