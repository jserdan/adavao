const db = require('./db');
async function test() {
    try {
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
        console.log('Success:', rows);
    } catch (err) {
        console.error('Database query error:', err.message);
    }
    process.exit();
}
test();
