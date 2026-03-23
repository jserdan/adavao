// AES helper for sensitive fields.
// Works with both Node format and Laravel format.

const crypto = require('crypto');

// Matches Laravel's default cipher.
const ALGORITHM = 'aes-256-cbc';
const ENCRYPTION_KEY = process.env.APP_KEY || 'base64:ciPqFYTQJ2bGZ0NUrfY7mvwODuOZ6zyUTlIh1D+pb+w=';

// Get the key from APP_KEY.
// If APP_KEY is base64:..., decode it first.
function getEncryptionKey() {
  let key = ENCRYPTION_KEY;

  // Decode Laravel-style base64 key.
  if (key.startsWith('base64:')) {
    key = key.substring(7);
    return Buffer.from(key, 'base64');
  }

  // Fallback: normalize to 32 bytes for AES-256.
  return Buffer.from(key.padEnd(32, '0').substring(0, 32));
}

// Encrypt plain text using AES-256-CBC.
// Returns base64(iv + ciphertext).
function encrypt(text) {
  if (!text) return text;

  try {
    // Random 16-byte IV.
    const iv = crypto.randomBytes(16);

    const key = getEncryptionKey();

    const cipher = crypto.createCipheriv(ALGORITHM, key, iv);

    let encrypted = cipher.update(text, 'utf8', 'base64');
    encrypted += cipher.final('base64');

    // Output format: base64(iv + ciphertext).
    const combined = Buffer.concat([iv, Buffer.from(encrypted, 'base64')]);

    return combined.toString('base64');
  } catch (error) {
    console.error('❌ Encryption error:', error.message);
    throw new Error('Failed to encrypt data');
  }
}

// Try to decrypt one layer.
// If not decryptable, just return the original input.
function decryptOnce(encryptedData) {
  if (!encryptedData) return encryptedData;

  // Quick guard for non-encrypted values.
  if (typeof encryptedData !== 'string' || encryptedData.length < 24) {
    return encryptedData;
  }

  try {
    const key = getEncryptionKey();

    // Try Laravel payload format first.
    try {
      const decoded = Buffer.from(encryptedData, 'base64').toString('utf8');
      const payload = JSON.parse(decoded);
      if (payload && payload.iv && payload.value) {
        const iv = Buffer.from(payload.iv, 'base64');
        const encrypted = Buffer.from(payload.value, 'base64');
        const decipher = crypto.createDecipheriv(ALGORITHM, key, iv);
        let decrypted = decipher.update(encrypted, undefined, 'utf8');
        decrypted += decipher.final('utf8');
        // Strip PHP serialized wrapper if present.
        const phpMatch = decrypted.match(/^s:\d+:"(.*)";$/s);
        if (phpMatch) return phpMatch[1];
        return decrypted;
      }
    } catch (laravelError) {
      // Not Laravel format.
    }

    // Try Node format: base64(iv + ciphertext).
    let combined;
    try {
      combined = Buffer.from(encryptedData, 'base64');
    } catch (decodeError) {
      return encryptedData;
    }

    // Need at least IV (16 bytes) + 1 byte payload.
    if (combined.length < 17) {
      return encryptedData;
    }

    // Extract IV and ciphertext.
    const iv = combined.slice(0, 16);
    const encrypted = combined.slice(16);

    const decipher = crypto.createDecipheriv(ALGORITHM, key, iv);

    let decrypted = decipher.update(encrypted, undefined, 'utf8');
    decrypted += decipher.final('utf8');

    return decrypted;
  } catch (error) {
    // Keep original value if decryption fails.
    return encryptedData;
  }
}

// Fully decrypt text.
// Some old values were encrypted multiple times, so we unwrap up to 20 layers.
function decrypt(encryptedData) {
  if (!encryptedData) return encryptedData;
  if (typeof encryptedData !== 'string' || encryptedData.length < 24) return encryptedData;

  let current = encryptedData;
  for (let i = 0; i < 20; i++) {
    const decrypted = decryptOnce(current);
    if (decrypted === current) break;
    current = decrypted;
  }
  return current;
}

// Encrypt file bytes.
// Output format is IV + encrypted bytes.
function encryptFile(fileBuffer) {
  if (!fileBuffer) return fileBuffer;

  try {
    const iv = crypto.randomBytes(16);
    const key = getEncryptionKey();
    const cipher = crypto.createCipheriv(ALGORITHM, key, iv);

    const encrypted = Buffer.concat([cipher.update(fileBuffer), cipher.final()]);

    // Prefix IV for later decryption.
    return Buffer.concat([iv, encrypted]);
  } catch (error) {
    console.error('❌ File encryption error:', error.message);
    throw new Error('Failed to encrypt file');
  }
}

// Decrypt file bytes.
// If decrypt fails, return original bytes so we don't break file handling.
function decryptFile(encryptedBuffer) {
  if (!encryptedBuffer) return encryptedBuffer;

  // Need IV (16 bytes) + 1 byte payload.
  if (encryptedBuffer.length < 17) {
    console.log('⚠️ File too small to decrypt, returning as-is');
    return encryptedBuffer;
  }

  try {
    const key = getEncryptionKey();

    // Extract IV and ciphertext.
    const iv = encryptedBuffer.slice(0, 16);
    const encrypted = encryptedBuffer.slice(16);

    const decipher = crypto.createDecipheriv(ALGORITHM, key, iv);

    return Buffer.concat([decipher.update(encrypted), decipher.final()]);
  } catch (error) {
    // If decrypt fails, keep original buffer.
    console.log('⚠️ File decryption failed, returning original (likely not encrypted)');
    return encryptedBuffer;
  }
}

// Encrypt only selected fields in an object.
function encryptFields(obj, fields) {
  const encrypted = { ...obj };

  fields.forEach(field => {
    if (encrypted[field]) {
      encrypted[field] = encrypt(String(encrypted[field]));
    }
  });

  return encrypted;
}

// Decrypt only selected fields in an object.
function decryptFields(obj, fields) {
  const decrypted = { ...obj };

  fields.forEach(field => {
    if (decrypted[field]) {
      decrypted[field] = decrypt(decrypted[field]);
    }
  });

  return decrypted;
}

// Simple role check for who can view decrypted values.
function canDecrypt(userRole) {
  const authorizedRoles = ['police', 'admin'];
  return authorizedRoles.includes(userRole);
}

module.exports = {
  encrypt,
  decrypt,
  encryptFile,
  decryptFile,
  encryptFields,
  decryptFields,
  canDecrypt,
  ALGORITHM
};
