#!/bin/bash

# Start Laravel development server with custom PHP configuration for large file uploads

echo "=== Laravel Development Server with Large Upload Support ==="
echo ""

# Get the absolute path to the backend directory
BACKEND_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUBLIC_DIR="$BACKEND_DIR/public"

echo "🔍 Running upload limits diagnostic..."
php -d upload_max_filesize=1024M -d post_max_size=1024M -d memory_limit=1024M check-upload-limits.php

echo ""
echo "🚀 Starting Laravel development server..."
echo "📁 Backend directory: $BACKEND_DIR"
echo "📁 Public directory: $PUBLIC_DIR"
echo "🌐 Server will be available at: http://localhost:8000"
echo "📊 Upload limit: 1GB (1024MB)"
echo "⏱️  Execution time: Unlimited"
echo ""
echo "Press Ctrl+C to stop the server"
echo "================================================"
echo ""

# Start the server with explicit PHP settings for large uploads
php -d upload_max_filesize=1024M \
    -d post_max_size=1024M \
    -d memory_limit=1024M \
    -d max_execution_time=0 \
    -d max_input_time=300 \
    -d max_file_uploads=20 \
    -d file_uploads=On \
    -S 127.0.0.1:8000 \
    -t "$PUBLIC_DIR"
