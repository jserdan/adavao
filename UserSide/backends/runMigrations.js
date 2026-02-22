/**
 * Startup Script for AlertDavao (UserSide Node.js Backend)
 * Runs automatically on server startup
 * 
 * NOTE: One-time schema migrations (columns, tables, indexes) have been removed.
 * Those were already applied to the production database.
 * Only recurring startup tasks remain here.
 */

const db = require('./db');
const { decrypt, encrypt } = require('./encryptionService');

async function runMigrations() {
  console.log('🚀 Running startup tasks...');

  try {
    // Health check - verify database connection
    console.log('🔍 Verifying database connection...');
    const result = await db.query('SELECT NOW() as server_time');
    console.log(`✅ Database connected. Server time: ${result[0][0].server_time}`);

    // ── Widen contact & address columns to TEXT for encrypted data ────
    // Encrypted values are 60+ chars; VARCHAR(15) truncates/errors on PostgreSQL.
    console.log('🔧 Ensuring contact/address columns are TEXT type...');
    const schemaQueries = [
      `ALTER TABLE users_public ALTER COLUMN contact TYPE TEXT`,
      `ALTER TABLE users_public ALTER COLUMN address TYPE TEXT`,
      `ALTER TABLE user_admin ALTER COLUMN contact TYPE TEXT`,
      `ALTER TABLE user_admin ALTER COLUMN address TYPE TEXT`,
      `ALTER TABLE pending_user_admin_registrations ALTER COLUMN contact TYPE TEXT`,
      `ALTER TABLE patrol_dispatches ADD COLUMN IF NOT EXISTS verification_media_url TEXT`,
    ];
    for (const q of schemaQueries) {
      try {
        await db.query(q);
      } catch (colErr) {
        // Column might already be TEXT, or table/column might not exist — safe to ignore
        if (!colErr.message.includes('does not exist')) {
          console.log(`  ℹ️  ${q.split('ALTER COLUMN ')[1]?.split(' ')[0] || ''}: ${colErr.message.includes('already') ? 'already TEXT/Column exists' : colErr.message}`);
        }
      }
    }
    console.log('✅ Schema modifications complete.');

    // ── Repair corrupted encrypted data ──────────────────────────
    // Previously, VARCHAR(15) truncated encrypted values making them unrecoverable.
    // Detect values that look like truncated base64 — try to decrypt them first;
    // if decryption fails, they're corrupted and should be cleared.
    console.log('🔧 Checking for corrupted encrypted data...');
    const repairTables = [
      { table: 'users_public', idCol: 'id' },
      { table: 'user_admin', idCol: 'id' },
    ];
    for (const { table, idCol } of repairTables) {
      try {
        const [rows] = await db.query(
          `SELECT ${idCol}, contact, address FROM ${table} 
           WHERE (contact IS NOT NULL AND contact ~ '^[A-Za-z0-9+/=]+$')
              OR (address IS NOT NULL AND address ~ '^[A-Za-z0-9+/=]+$')`
        );
        for (const row of rows) {
          const updates = [];
          const vals = [];
          let idx = 1;
          for (const col of ['contact', 'address']) {
            const val = row[col];
            if (!val || !/^[A-Za-z0-9+/=]+$/.test(val)) continue;
            // Try to decrypt — if it fails, value is corrupted
            const decrypted = decrypt(val);
            if (decrypted === val) {
              // decrypt returned original = failed to decrypt = corrupted data
              // But skip plaintext values that just happen to be base64-ish
              // Only clear if it looks like a truncated encrypted value (20-50 chars of pure base64)
              if (val.length >= 20 && val.length <= 50) {
                updates.push(`${col} = $${idx++}`);
                vals.push(null);
                console.log(`  ⚠️  ${table} ${idCol}=${row[idCol]}: clearing corrupted ${col} (len=${val.length})`);
              }
            }
          }
          if (updates.length > 0) {
            vals.push(row[idCol]);
            await db.query(`UPDATE ${table} SET ${updates.join(', ')} WHERE ${idCol} = $${idx}`, vals);
          }
        }
      } catch (repairErr) {
        console.warn(`  ℹ️  Could not repair ${table}: ${repairErr.message}`);
      }
    }
    console.log('✅ Data integrity check complete.');

    // ── Fix double/multi-encrypted data ──────────────────────────
    // The old isAlreadyEncrypted() missed short encrypted values (<50 chars),
    // causing them to be re-encrypted on every server restart.
    // This step recursively decrypts until we get plaintext, then re-encrypts once.
    console.log('🔧 Fixing double-encrypted data...');
    const fixTables = [
      { table: 'users_public', idCol: 'id' },
      { table: 'user_admin', idCol: 'id' },
    ];
    for (const { table, idCol } of fixTables) {
      try {
        const [rows] = await db.query(
          `SELECT ${idCol}, contact, address FROM ${table}
           WHERE (contact IS NOT NULL AND contact != '')
              OR (address IS NOT NULL AND address != '')`
        );
        let fixed = 0;
        for (const row of rows) {
          const updates = [];
          const vals = [];
          let idx = 1;

          for (const col of ['contact', 'address']) {
            const raw = row[col];
            if (!raw) continue;
            // Decrypt repeatedly until we get plaintext
            let current = raw;
            let layers = 0;
            for (let i = 0; i < 20; i++) {  // max 20 layers
              const decrypted = decrypt(current);
              if (decrypted === current) break;  // no change = plaintext reached
              current = decrypted;
              layers++;
            }
            if (layers > 1) {
              // Was multi-encrypted. Re-encrypt the plaintext once.
              const reEncrypted = encrypt(current);
              updates.push(`${col} = $${idx++}`);
              vals.push(reEncrypted);
              console.log(`  🔧 ${table} ${idCol}=${row[idCol]}: fixed ${col} (${layers} encryption layers → 1)`);
            }
          }
          if (updates.length > 0) {
            vals.push(row[idCol]);
            await db.query(`UPDATE ${table} SET ${updates.join(', ')} WHERE ${idCol} = $${idx}`, vals);
            fixed++;
          }
        }
        if (fixed > 0) console.log(`  ✅ Fixed ${fixed} rows in ${table}`);
      } catch (fixErr) {
        console.warn(`  ℹ️  Could not fix double-encryption in ${table}: ${fixErr.message}`);
      }
    }
    console.log('✅ Double-encryption fix complete.');

    console.log('✅ Startup tasks completed successfully!');
    return true;
  } catch (error) {
    console.error('❌ Startup error:', error);
    console.warn('⚠️ Server will continue despite startup errors');
    return false;
  }
}

module.exports = { runMigrations };
