#!/bin/bash

echo "🚀 Starting Laravel server with 1GB upload support..."
echo "📊 Upload limit: 1GB (1024MB)"
echo "🌐 Server: http://localhost:8000"
echo "⏱️  Execution time: Unlimited"
echo ""
echo "Press Ctrl+C to stop the server"
echo "================================"

# Start server with 1GB upload support
php -d upload_max_filesize=1024M \
    -d post_max_size=1024M \
    -d memory_limit=1024M \
    -d max_execution_time=0 \
    -d max_input_time=300 \
    -d max_file_uploads=20 \
    -d file_uploads=On \
    -S 127.0.0.1:8000 \
    -t public
