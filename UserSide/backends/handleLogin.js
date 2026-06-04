// handleLogin.js
const bcrypt = require("bcryptjs");
const db = require("./db");
const { checkUserRestrictions } = require("./handleUserRestrictions");
const { decrypt } = require("./encryptionService");

// Sanitize email input
const sanitizeEmail = (email) => {
  if (!email) return '';
  return email.toString().trim().toLowerCase().replace(/[<>'"]/g, '');
};

const normalizeRole = (role) => String(role || '').trim().toLowerCase();

const emailWhereClause = (columnName) => `LOWER(TRIM(${columnName})) = LOWER(TRIM($1))`;

const handleLogin = async (req, res) => {
  const { email, password } = req.body;

  // Sanitize inputs
  const sanitizedEmail = sanitizeEmail(email);

  // Validate email format
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(sanitizedEmail)) {
    return res.status(400).json({ message: "Invalid email format" });
  }

  // Validate password presence (allow existing accounts with older passwords)
  if (!password) {
    return res.status(400).json({ message: "Password is required" });
  }

  try {
    console.log("📩 Login attempt:", { email: sanitizedEmail });

    // 1. Query user from users_public table (UserSide app users only)
    const [rows] = await db.query(
      `SELECT * FROM users_public WHERE ${emailWhereClause('email')}`,
      [sanitizedEmail]
    );
    console.log("📊 Query result:", rows.length > 0 ? "User found" : "User not found");

    if (rows.length === 0) {
      // User not found in users_public.
      // Patrol login fallback:
      // If the account exists in AdminSide (user_admin) AND has patrol_officer role,
      // auto-create/sync it into users_public (mobile app source of truth) then continue.
      console.log("⚠️ User not found in users_public table for:", sanitizedEmail);

      try {
        console.log('🔎 Patrol fallback: checking AdminSide user_admin for', sanitizedEmail);

        // Use RBAC: ONLY patrol_officer accounts from user_admin can cross-login into UserSide.
        // Prefer explicit user_admin.user_role; optionally fallback to RBAC join if those tables exist.
        const [adminRows] = await db.query(
          `SELECT id, firstname, lastname, email, contact, password, email_verified_at, user_role
           FROM user_admin
           WHERE ${emailWhereClause('email')}
           LIMIT 1`,
          [sanitizedEmail]
        );

        if (adminRows.length === 0) {
          console.log('ℹ️ Patrol fallback: no user_admin row found for email (same DB):', sanitizedEmail);

          // If AdminSide now uses strict verification (pending table), provide a clearer message.
          try {
            const [reg] = await db.query(
              "SELECT to_regclass('public.pending_user_admin_registrations') AS pending",
              []
            );
            if (reg?.[0]?.pending) {
              const [pendingRows] = await db.query(
                "SELECT email, user_role, token_expires_at FROM pending_user_admin_registrations WHERE LOWER(TRIM(email)) = LOWER(TRIM($1)) LIMIT 1",
                [sanitizedEmail]
              );
              if (pendingRows.length > 0) {
                console.log('ℹ️ Patrol fallback: pending admin registration exists (not yet verified):', {
                  email: pendingRows[0].email,
                  user_role: pendingRows[0].user_role,
                });
                return res.status(403).json({
                  message: 'Your account registration is pending email verification. Please click the verification link sent to your email, then try logging in again.',
                  emailNotVerified: true,
                  email: sanitizedEmail,
                });
              }
            }
          } catch (pendingErr) {
            console.warn('⚠️ Patrol fallback: pending lookup skipped/failed:', pendingErr.message);
          }
        }

        if (adminRows.length > 0) {
          const adminUser = adminRows[0];

          let rbacRole = null;
          try {
            const [reg] = await db.query(
              "SELECT to_regclass('public.user_admin_roles') AS uar, to_regclass('public.roles') AS roles",
              []
            );
            if (reg?.[0]?.uar && reg?.[0]?.roles) {
              const [rbacRows] = await db.query(
                `SELECT r.role_name AS rbac_role
                 FROM user_admin ua
                 INNER JOIN user_admin_roles uar ON uar.user_admin_id = ua.id
                 INNER JOIN roles r ON r.role_id = uar.role_id
                 WHERE ${emailWhereClause('ua.email')}
                 LIMIT 1`,
                [sanitizedEmail]
              );
              if (rbacRows.length > 0) {
                rbacRole = rbacRows[0].rbac_role;
              }
            }
          } catch (rbacErr) {
            console.warn('⚠️ Patrol fallback: RBAC role lookup skipped/failed:', rbacErr.message);
          }

          const effectiveRole = normalizeRole(adminUser.user_role || rbacRole);
          console.log('🔐 Patrol fallback: AdminSide user found:', {
            email: adminUser.email,
            effectiveRole,
            emailVerified: Boolean(adminUser.email_verified_at),
          });

          if (effectiveRole === 'patrol_officer') {
            // Look up the officer's station_id from AdminSide user_admin table
            let adminStationId = null;
            try {
              const [stationRows] = await db.query(
                `SELECT station_id FROM user_admin WHERE id = $1`,
                [adminUser.id]
              );
              if (stationRows.length > 0) {
                adminStationId = stationRows[0].station_id;
              }
            } catch (stErr) {
              console.warn('⚠️ Could not look up station for admin user:', stErr.message);
            }

            await db.query(
              `INSERT INTO users_public (
                  firstname, lastname, email, contact, password,
                  user_role, is_on_duty, assigned_station_id,
                  email_verified_at,
                  created_at, updated_at
                )
                SELECT $1, $2, $3, $4, $5, 'patrol_officer', FALSE, $7, COALESCE($6, NOW()), NOW(), NOW()
                WHERE NOT EXISTS (
                  SELECT 1 FROM users_public WHERE LOWER(TRIM(email)) = LOWER(TRIM($3))
                )`,
              [adminUser.firstname, adminUser.lastname, adminUser.email, adminUser.contact, adminUser.password, adminUser.email_verified_at, adminStationId]
            );

            await db.query(
              `UPDATE users_public
               SET user_role = 'patrol_officer',
                   assigned_station_id = COALESCE($3, assigned_station_id),
                   email_verified_at = COALESCE(email_verified_at, COALESCE($2, NOW())),
                   updated_at = NOW()
               WHERE LOWER(TRIM(email)) = LOWER(TRIM($1))`,
              [adminUser.email, adminUser.email_verified_at, adminStationId]
            );

            const [syncedRows] = await db.query(
              `SELECT * FROM users_public WHERE ${emailWhereClause('email')}`,
              [sanitizedEmail]
            );
            if (syncedRows.length > 0) {
              console.log('✅ Synced patrol_officer from user_admin into users_public:', sanitizedEmail);
              rows.push(syncedRows[0]);
            } else {
              console.log('⚠️ Patrol fallback: attempted sync but users_public row still not found for:', sanitizedEmail);
            }
          } else {
            console.log('🚫 AdminSide account exists but is not patrol_officer:', { email: sanitizedEmail, effectiveRole });
          }
        }
      } catch (syncError) {
        console.warn('⚠️ Patrol RBAC sync attempt failed:', syncError.message);
      }

      if (rows.length === 0) {
        return res.status(401).json({
          message: "The provided credentials do not match our records. This account may be restricted to the AdminSide application.",
          userNotFound: true
        });
      }
    }

    const user = rows[0];
    console.log("👤 Found user:", user.email);

    // 2. Check if account is locked
    if (user.lockout_until) {
      const lockoutTime = new Date(user.lockout_until);
      const now = new Date();

      if (now < lockoutTime) {
        const remainingMinutes = Math.ceil((lockoutTime - now) / (1000 * 60));
        console.log("🔒 Account locked for:", user.email, "Remaining:", remainingMinutes, "minutes");
        return res.status(403).json({
          message: `Account temporarily locked due to too many failed login attempts. Please try again in ${remainingMinutes} minute(s).`,
          accountLocked: true,
          lockoutUntil: user.lockout_until
        });
      } else {
        // Lockout expired, reset attempts
        await db.query(
          "UPDATE users_public SET failed_login_attempts = 0, lockout_until = NULL WHERE id = $1",
          [user.id]
        );
        user.failed_login_attempts = 0;
        user.lockout_until = null;
      }
    }

    // 3. Check if email is verified
    if (!user.email_verified_at) {
      console.log("🚫 Email not verified for:", user.email);
      return res.status(403).json({
        message: "Please verify your email address before logging in. Check your inbox for the verification link.",
        emailNotVerified: true,
        email: user.email
      });
    }

    // 4. Check for user restrictions BEFORE password verification
    try {
      const restrictions = await checkUserRestrictions(user.id);

      if (restrictions.isRestricted) {
        console.log("🚫 User is restricted:", restrictions.restrictionType);

        // If user is banned, deny login completely
        if (restrictions.restrictionType === 'banned') {
          return res.status(403).json({
            message: "Your account has been suspended",
            restricted: true,
            restrictionType: restrictions.restrictionType,
            reason: restrictions.reason,
            expiresAt: restrictions.expiresAt
          });
        }

        // For other restrictions, allow login but include restriction info
        console.log("⚠️ User has active restrictions but can still login");
      }
    } catch (restrictionError) {
      // If restriction check fails (e.g., tables don't exist yet), continue with login
      console.log("⚠️ Could not check restrictions (tables may not exist):", restrictionError.message);
    }

    // 5. Compare password
    const passwordMatch = await bcrypt.compare(password, user.password);
    if (!passwordMatch) {
      console.log("❌ Invalid password for:", sanitizedEmail);

      // Increment failed login attempts
      const newAttempts = (user.failed_login_attempts || 0) + 1;
      let lockoutUntil = null;
      let lockoutMessage = "";

      if (newAttempts >= 15) {
        // 15+ attempts: 15 minute lockout + email alert
        lockoutUntil = new Date(Date.now() + 15 * 60 * 1000);
        lockoutMessage = "Account locked for 15 minutes due to 15 failed login attempts. A security alert has been sent to your email.";

        // Send email notification (if email service is configured)
        try {
          const { sendLockoutEmail } = require('./emailService');
          await sendLockoutEmail(user.email, user.firstname, newAttempts);
        } catch (emailError) {
          console.error("Failed to send lockout email:", emailError.message);
        }
      } else if (newAttempts >= 10) {
        // 10-14 attempts: 10 minute lockout
        lockoutUntil = new Date(Date.now() + 10 * 60 * 1000);
        lockoutMessage = "Account locked for 10 minutes due to multiple failed login attempts.";
      } else if (newAttempts >= 5) {
        // 5-9 attempts: 5 minute lockout
        lockoutUntil = new Date(Date.now() + 5 * 60 * 1000);
        lockoutMessage = "Account locked for 5 minutes due to multiple failed login attempts.";
      }

      // Update database with new attempt count and lockout time
      await db.query(
        "UPDATE users_public SET failed_login_attempts = $1, lockout_until = $2, last_failed_login = NOW() WHERE id = $3",
        [newAttempts, lockoutUntil, user.id]
      );

      if (lockoutUntil) {
        return res.status(403).json({
          message: lockoutMessage,
          accountLocked: true,
          lockoutUntil: lockoutUntil
        });
      } else {
        const remainingAttempts = 5 - newAttempts;
        return res.status(401).json({
          message: `Invalid credentials. ${remainingAttempts} attempt(s) remaining before account lockout.`
        });
      }
    }

    // 6. Password verified - reset failed login attempts
    await db.query(
      "UPDATE users_public SET failed_login_attempts = 0, lockout_until = NULL, last_failed_login = NULL WHERE id = $1",
      [user.id]
    );

    // 7. Get any active restrictions to include in response
    let userRestrictions = null;
    try {
      userRestrictions = await checkUserRestrictions(user.id);
    } catch (err) {
      // Ignore if tables don't exist
    }

    // 8. Return full user data for login
    console.log("✅ Login successful for:", user.email);

    // Role normalization: DB uses `user_role` but older clients may read `role`.
    const effectiveRole = user.user_role || user.role || 'user';

    // 🔓 Decrypt sensitive fields for display (handle possible double-encryption)
    let decryptedAddress = user.address ? decrypt(user.address) : '';
    if (decryptedAddress && (decryptedAddress.startsWith('ENC_') || (decryptedAddress.length > 80 && /^[A-Za-z0-9+/=:]+$/.test(decryptedAddress)))) {
      decryptedAddress = decrypt(decryptedAddress);
    }
    let decryptedContact = user.contact ? decrypt(user.contact) : '';
    if (decryptedContact && (decryptedContact.startsWith('ENC_') || (decryptedContact.length > 80 && /^[A-Za-z0-9+/=:]+$/.test(decryptedContact)))) {
      decryptedContact = decrypt(decryptedContact);
    }

    // Return complete user data
    const responsePayload = {
      success: true,
      message: 'Login successful',
      user: {
        id: user.id,
        firstname: user.firstname,
        lastname: user.lastname,
        lastName: user.lastname,
        email: user.email,
        contact: decryptedContact,
        phone: decryptedContact,
        address: decryptedAddress,
        is_verified: user.is_verified,
        profile_image: user.profile_image,
        user_role: effectiveRole,
        role: effectiveRole,
        assigned_station_id: user.assigned_station_id || null,
        is_on_duty: user.is_on_duty || false,
        createdAt: user.created_at,
        updatedAt: user.updated_at,
        emailVerified: Boolean(user.email_verified_at)
      },
      restrictions: userRestrictions
    };

    // DEBUG LOGGING FOR PATROL IDENTIFICATION
    console.log("\n=================== LOGIN DIAGNOSTICS ===================");
    console.log("Email from DB:", user.email);
    console.log("Raw user_role/role:", user.user_role || user.role || 'NULL');
    console.log("Effective Role:", effectiveRole);
    console.log("Assigned Station ID:", user.assigned_station_id || 'NULL');
    
    const userEmailForCheck = String(user.email || '').toLowerCase();
    const effectiveRoleForCheck = String(effectiveRole || '').toLowerCase();
    const assignedStationForCheck = parseInt(user.assigned_station_id || '0', 10);
    const isPatrolBackendSimulation = effectiveRoleForCheck.includes('patrol') || userEmailForCheck.includes('patrol') || assignedStationForCheck > 0;
    
    console.log("Frontend `isPatrol` evaluation will be:", isPatrolBackendSimulation);
    console.log("=======================================================\n");

    res.json(responsePayload);
  } catch (error) {
    // ✅ Print full error details in terminal
    console.error("❌ Login error occurred:", error);
    res.status(500).json({
      message: "Server error",
      error: error.message || "Unknown error",
    });
  }
};

module.exports = handleLogin;
