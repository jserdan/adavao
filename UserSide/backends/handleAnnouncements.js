const db = require('./db');
const { uploadFile, isConfigured } = require('./cloudinaryService');

// Admin site base URL for storage assets (announcements, etc.)
const ADMIN_BASE_URL = process.env.ADMIN_KEEP_ALIVE_URL || 'https://adavao-r8zo.onrender.com';

/**
 * Convert relative attachment paths to full URLs
 * Paths stored as 'announcements/file.ext' → 'https://admin-url/storage/announcements/file.ext'
 */
function resolveAttachmentUrls(attachments) {
    if (!attachments) return [];
    const parsed = typeof attachments === 'string' ? JSON.parse(attachments) : attachments;
    if (!Array.isArray(parsed)) return [];
    return parsed.map(path => {
        // If already a full URL, return as-is
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        // Otherwise prepend admin storage URL
        return `${ADMIN_BASE_URL}/storage/${path}`;
    });
}

/**
 * Get all announcements (public, no auth required)
 * Returns latest announcements first
 */
async function getAnnouncements(req, res) {
    try {
        const limit = parseInt(req.query.limit) || 10;

        const [rows] = await db.query(
            `SELECT 
                a.id,
                a.title,
                a.content,
                a.attachments,
                a.created_at,
                a.updated_at,
                u.firstname AS author_firstname,
                u.lastname AS author_lastname
             FROM announcements a
             LEFT JOIN user_admin u ON a.user_id = u.id
             ORDER BY a.created_at DESC
             LIMIT $1`,
            [limit]
        );

        // Format the response
        const announcements = rows.map(row => ({
            id: row.id,
            title: row.title,
            content: row.content,
            attachments: resolveAttachmentUrls(row.attachments),
            author: row.author_firstname ? `${row.author_firstname} ${row.author_lastname || ''}`.trim() : 'Admin',
            created_at: row.created_at,
            // Format date for display
            date: formatDate(row.created_at)
        }));

        return res.json({
            success: true,
            data: announcements,
            count: announcements.length
        });

    } catch (error) {
        console.error('Error fetching announcements:', error);
        return res.status(500).json({
            success: false,
            message: 'Failed to fetch announcements',
            error: error.message
        });
    }
}

/**
 * Get a single announcement by ID
 */
async function getAnnouncementById(req, res) {
    try {
        const { id } = req.params;

        const [rows] = await db.query(
            `SELECT 
                a.id,
                a.title,
                a.content,
                a.attachments,
                a.created_at,
                a.updated_at,
                u.firstname AS author_firstname,
                u.lastname AS author_lastname
             FROM announcements a
             LEFT JOIN user_admin u ON a.user_id = u.id
             WHERE a.id = $1`,
            [id]
        );

        if (!rows || rows.length === 0) {
            return res.status(404).json({
                success: false,
                message: 'Announcement not found'
            });
        }

        const row = rows[0];
        const announcement = {
            id: row.id,
            title: row.title,
            content: row.content,
            attachments: resolveAttachmentUrls(row.attachments),
            author: row.author_firstname ? `${row.author_firstname} ${row.author_lastname || ''}`.trim() : 'Admin',
            created_at: row.created_at,
            date: formatDate(row.created_at)
        };

        return res.json({
            success: true,
            data: announcement
        });

    } catch (error) {
        console.error('Error fetching announcement:', error);
        return res.status(500).json({
            success: false,
            message: 'Failed to fetch announcement',
            error: error.message
        });
    }
}

/**
 * Helper function to format date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return 'Today';
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return `${diffDays} days ago`;
    } else {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
        });
    }
}

/**
 * Upload announcement attachment files to Cloudinary
 * Called by the Laravel AdminSide when creating announcements
 * Accepts multipart file upload, returns Cloudinary URLs
 */
async function uploadAnnouncementFiles(req, res) {
    try {
        if (!isConfigured()) {
            return res.status(500).json({
                success: false,
                message: 'Cloudinary is not configured on the server'
            });
        }

        const files = req.files;
        if (!files || files.length === 0) {
            return res.status(400).json({
                success: false,
                message: 'No files provided'
            });
        }

        console.log(`☁️ Uploading ${files.length} announcement file(s) to Cloudinary...`);

        const uploadedUrls = [];
        for (const file of files) {
            const result = await uploadFile(file.path, 'announcements', {
                resource_type: 'auto'
            });

            if (result.success) {
                uploadedUrls.push(result.url);
                console.log(`   ✅ Uploaded: ${result.url}`);
            } else {
                console.error(`   ❌ Failed to upload ${file.originalname}: ${result.error}`);
            }
        }

        if (uploadedUrls.length === 0) {
            return res.status(500).json({
                success: false,
                message: 'All file uploads failed'
            });
        }

        return res.json({
            success: true,
            urls: uploadedUrls,
            count: uploadedUrls.length
        });

    } catch (error) {
        console.error('Error uploading announcement files:', error);
        return res.status(500).json({
            success: false,
            message: 'Failed to upload files',
            error: error.message
        });
    }
}

module.exports = {
    getAnnouncements,
    getAnnouncementById,
    uploadAnnouncementFiles
};
