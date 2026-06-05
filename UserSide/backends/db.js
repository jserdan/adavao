// db.js
const { Pool, types } = require("pg");
require("dotenv").config();

// Override parser for TIMESTAMP WITHOUT TIME ZONE (type 1114) to parse as Asia/Manila
types.setTypeParser(1114, function(stringValue) {
  const isoString = stringValue.replace(' ', 'T') + '+08:00';
  return new Date(isoString);
});

// Optimized connection pool configuration for scalability
const poolConfig = process.env.DATABASE_URL
  ? {
    connectionString: process.env.DATABASE_URL,
    ssl: {
      rejectUnauthorized: false // Required for Render Postgres
    },
    // Connection pool optimization
    max: 20,                      // Maximum connections in pool
    min: 2,                       // Minimum connections to keep open
    idleTimeoutMillis: 30000,     // Close idle connections after 30s
    connectionTimeoutMillis: 10000, // Timeout for new connections
    acquireTimeoutMillis: 30000,  // Timeout for acquiring from pool
    allowExitOnIdle: false,       // Keep pool open for server lifetime
  }
  : {
    host: (() => {
      let host = process.env.DB_HOST || "127.0.0.1";
      if (host.startsWith("dpg-") && !host.includes(".")) {
        host = `${host}.singapore-postgres.render.com`;
      }
      return host;
    })(),
    user: process.env.DB_USER || "postgres",
    password: process.env.DB_PASSWORD || "1234",
    database: process.env.DB_DATABASE || "alertdavao",
    port: process.env.DB_PORT || 5432,
    ssl: false,
    // Connection pool optimization
    max: 20,
    min: 2,
    idleTimeoutMillis: 30000,
    connectionTimeoutMillis: 10000,
  };

// Create optimized connection pool
const pool = new Pool(poolConfig);

// Handle pool errors gracefully
pool.on('error', (err) => {
  console.error('🔴 Unexpected database pool error:', err.message);
});

pool.on('connect', (client) => {
  // Set session timezone to Asia/Manila to match Laravel backend timestamps
  client.query("SET timezone = 'Asia/Manila';").catch((err) => {
    console.error('🔴 Failed to set session timezone to Asia/Manila:', err.message);
  });
});

// Wrapper that returns [rows, fields] tuple for consistent interface
const db = {
  query: async (text, params) => {
    const result = await pool.query(text, params);
    return [result.rows, result.fields];
  },
  getConnection: async () => {
    // For transactions
    const client = await pool.connect();
    // Wrap client for consistent [rows, fields] interface
    const connection = {
      query: async (text, params) => {
        const result = await client.query(text, params);
        return [result.rows, result.fields];
      },
      beginTransaction: () => client.query('BEGIN'),
      commit: () => client.query('COMMIT'),
      rollback: () => client.query('ROLLBACK'),
      release: () => client.release()
    };
    return connection;
  },
  // Get pool stats for monitoring
  getPoolStats: () => ({
    totalCount: pool.totalCount,
    idleCount: pool.idleCount,
    waitingCount: pool.waitingCount,
  }),
  // Graceful shutdown
  end: () => pool.end(),
};

module.exports = db;
