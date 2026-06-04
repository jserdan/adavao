/**
 * Authentication Middleware
 * Verifies user roles from the database to prevent role spoofing
 */

const db = require("./db");

function normalizeRole(role) {
  let normalized = String(role || 'user').toLowerCase();
  if (normalized === 'superadmin' || normalized === 'super_admin') normalized = 'admin';
  // Patrol officers are operationally police for access control
  if (normalized === 'patrol_officer') normalized = 'police';
  return normalized;
}

/**
 * Middleware to verify user role from database
 * Expects userId to be passed in query, body, or params
 * Sets req.userRole to the verified role from database
 */
const verifyUserRole = async (req, res, next) => {
  try {
    // Get userId from various sources
    const userId = req.params.userId || req.query.userId || req.body.userId || req.headers['x-user-id'];

    if (!userId) {
      return res.status(400).json({
        success: false,
        message: "User ID is required"
      });
    }

    // Query database to get actual user role (check BOTH admin/officer and public users)
    const role = await getVerifiedUserRole(userId);
    req.userRole = role;
    req.userId = userId;

    console.log(`✅ Verified user ${userId} has role: ${req.userRole}`);

    next();
  } catch (error) {
    console.error("Error verifying user role:", error);
    res.status(500).json({
      success: false,
      message: "Failed to verify user role",
      error: error.message
    });
  }
};

/**
 * Middleware to require admin or police role
 * Must be used after verifyUserRole middleware
 */
const requireAuthorizedRole = (req, res, next) => {
  const authorizedRoles = ['admin', 'police'];

  if (!req.userRole || !authorizedRoles.includes(req.userRole)) {
    return res.status(403).json({
      success: false,
      message: "Unauthorized: This action requires admin or police privileges"
    });
  }

  console.log(`✅ User has authorized role: ${req.userRole}`);
  next();
};

/**
 * Middleware to require admin role only
 * Must be used after verifyUserRole middleware
 */
const requireAdminRole = (req, res, next) => {
  if (req.userRole !== 'admin') {
    return res.status(403).json({
      success: false,
      message: "Unauthorized: This action requires admin privileges"
    });
  }

  console.log(`✅ User has admin role`);
  next();
};

/**
 * Get verified user role from request (for file serving routes)
 * This checks the database instead of trusting client input
 * Checks BOTH user_admin (AdminSide) and users_public (UserSide) tables
 * Uses RBAC tables (user_admin_roles, roles) with optimized single query
 */

// Cache for RBAC table existence (checked once per server startup)
let rbacTablesExist = null;

const getVerifiedUserRole = async (userId) => {
  try {
    if (!userId) {
      return 'user';
    }

    // Check RBAC tables exist (cached after first check)
    if (rbacTablesExist === null) {
      try {
        const [tableCheck] = await db.query(
          "SELECT to_regclass('public.user_admin_roles') AS uar, to_regclass('public.roles') AS roles",
          []
        );
        rbacTablesExist = Boolean(tableCheck?.[0]?.uar && tableCheck?.[0]?.roles);
      } catch {
        rbacTablesExist = false;
      }
    }

    // Optimized: Single query to check user_admin with RBAC fallback
    if (rbacTablesExist) {
      const [adminUsers] = await db.query(
        `SELECT 
           ua.id,
           COALESCE(NULLIF(ua.user_role, ''), r.role_name, 'admin') AS effective_role
         FROM user_admin ua
         LEFT JOIN user_admin_roles uar ON uar.user_admin_id = ua.id
         LEFT JOIN roles r ON r.role_id = uar.role_id
         WHERE ua.id = $1
         LIMIT 1`,
        [userId]
      );

      if (adminUsers.length > 0) {
        const role = normalizeRole(adminUsers[0].effective_role);
        console.log(`✅ Found admin/police user ${userId} with role: ${role} (RBAC optimized)`);
        return role;
      }
    } else {
      // Fallback if RBAC tables don't exist: just check user_role column
      const [adminUsers] = await db.query(
        "SELECT id, COALESCE(user_role, 'admin') AS user_role FROM user_admin WHERE id = $1",
        [userId]
      );

      if (adminUsers.length > 0) {
        const role = normalizeRole(adminUsers[0].user_role);
        console.log(`✅ Found admin/police user ${userId} with role: ${role}`);
        return role;
      }
    }

    // If not in user_admin, check users_public (UserSide app users)
    const [publicUsers] = await db.query(
      "SELECT COALESCE(user_role::text, role::text, 'user') AS role, email, assigned_station_id FROM users_public WHERE id = $1",
      [userId]
    );

    if (publicUsers.length > 0) {
      const rawRole = publicUsers[0].role || 'user';
      const email = String(publicUsers[0].email || '').toLowerCase();
      const stationId = parseInt(publicUsers[0].assigned_station_id || '0', 10);

      // Identify patrol officers by role, email pattern, or station assignment
      const isPatrol = rawRole === 'patrol_officer' || email.includes('patrol') || stationId > 0;
      if (isPatrol) {
        console.log(`👮 Identified patrol officer (user ${userId}): role=${rawRole}, email=${email}, station=${stationId}`);
        return 'police'; // Grant police-level access for dispatch endpoints
      }

      return normalizeRole(rawRole);
    }

    console.log(`⚠️ User ${userId} not found in any user table`);
    return 'user';
  } catch (error) {
    console.error("Error getting user role:", error);
    return 'user';
  }
};

module.exports = {
  verifyUserRole,
  requireAuthorizedRole,
  requireAdminRole,
  getVerifiedUserRole
};
