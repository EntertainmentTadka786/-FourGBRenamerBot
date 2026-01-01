#!/bin/bash
# Quick setup script for Video Renamer Bot

echo "🤖 Video Renamer Bot Setup"
echo "=========================="

# Create directory structure
mkdir -p {downloads,thumbs,logs,backups}

# Create minimal composer.json
cat > composer.json << 'EOF'
{
    "name": "video-renamer-bot",
    "description": "Telegram Video Renamer Bot",
    "type": "project",
    "require": {
        "danog/madelineproto": "^8.0",
        "vlucas/phpdotenv": "^5.5"
    },
    "config": {
        "optimize-autoloader": true
    }
}
EOF

# Create .env file if not exists
if [ ! -f .env ]; then
    cat > .env << 'EOF'
API_ID=38609654
API_HASH=a0e8ee97b9c10331ef8be11a6c0793e6
BOT_TOKEN=8521792734:AAF3AiUcZdtfD2JgsPZ9vE1kXZlurRtqmFQ
BOT_OWNER_ID=8521792734
BOT_USERNAME=FourGBRenamerBot
MAIN_CHANNEL=EntertainmentTadka786
EOF
    echo "✅ .env file created"
fi

# Create empty users.json
echo '{"users":{},"stats":{"total_files":0}}' > users.json

# Create error.log
touch error.log
chmod 666 error.log

echo ""
echo "✅ Setup completed!"
echo ""
echo "Next steps:"
echo "1. docker-compose up -d --build"
echo "2. docker-compose logs -f app"
echo "3. Visit: http://localhost:8080"
