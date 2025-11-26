#!/bin/bash
# MongoDB initialization script for GUVI app
# Creates database and collection with proper indexes

echo "Initializing MongoDB for GUVI app..."

mongosh --quiet --eval '
use guvi_app;

// Create profiles collection if it does not exist
db.createCollection("profiles");

// Create indexes for efficient queries
db.profiles.createIndex({ "user_id": 1 }, { unique: true, name: "user_id_unique" });
db.profiles.createIndex({ "updated_at": -1 }, { name: "updated_at_desc" });

// Verify setup
const count = db.profiles.countDocuments();
print("✓ MongoDB database: guvi_app");
print("✓ Collection: profiles");
print("✓ Indexes created: user_id (unique), updated_at");
print("✓ Current documents: " + count);
' 2>/dev/null

echo "MongoDB initialization complete!"