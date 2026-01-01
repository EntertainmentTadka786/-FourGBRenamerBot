# 🤖 Video Renamer Bot

Advanced Telegram Bot for video processing, renaming, and uploading with permanent filename templates.

## ✨ Features
- ✅ Permanent filename templates
- ✅ Auto quality detection (720p/1080p/4K)
- ✅ Thumbnail auto-resize
- ✅ Bold caption format
- ✅ Queue system
- ✅ 4GB file support

## 🚀 Quick Start

### Docker Method (Recommended)
```bash
# Clone repository
git clone https://github.com/fourgb/video-renamer-bot.git
cd video-renamer-bot

# Copy environment file
cp .env.example .env

# Edit .env with your credentials
nano .env

# Start with Docker Compose
docker-compose up -d