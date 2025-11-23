// MongoDB initialization script for GUVI Internship App
// Run with: mongo guvi_app mongo-init.js

const dbName = 'guvi_app';
const collectionName = 'profiles';

const db = db.getSiblingDB(dbName);

if (!db.getCollectionNames().includes(collectionName)) {
  db.createCollection(collectionName, { capped: false });
  print('Created collection: ' + collectionName);
} else {
  print('Collection already exists: ' + collectionName);
}

// Useful index for quick lookup by user_id
db[collectionName].createIndex({ user_id: 1 }, { unique: true });
print('Ensured unique index on user_id');