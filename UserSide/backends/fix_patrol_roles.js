const db = require('./db.js');

async function fixPatrolRoles() {
  console.log('🔍 Starting patrol role fix...');
  try {
    const [result] = await db.query(
      `UPDATE users_public 
       SET user_role = 'patrol_officer', role = 'patrol_officer' 
       WHERE LOWER(email) LIKE 'ps%.patrol@alertdavao.local'`
    );
    
    console.log(`✅ Success! Updated ${result.rowCount || result.affectedRows || 'multiple'} patrol accounts.`);
    console.log('The accounts now have the correct "patrol_officer" database role.');
  } catch (err) {
    console.error('❌ Error updating roles:', err);
  } finally {
    if (db.end) await db.end();
    else if (db.pool) await db.pool.end();
    process.exit(0);
  }
}

fixPatrolRoles();
