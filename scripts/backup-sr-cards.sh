#!/bin/bash
# Backup all .md files with SR tags before migration
# Usage: ./backup-sr-cards.sh

set -e

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="backup_sr_${TIMESTAMP}"

echo "🔍 Finding all .md files with SR tags in container..."

# Create backup directory in container
docker exec nextcloud-aio-nextcloud bash -c "
cd /mnt/ncdata
mkdir -p /tmp/${BACKUP_DIR}

# Find all .md files with SR tags
find . -name '*.md' -type f 2>/dev/null | while read file; do
    if grep -q '<!--SR:' \"\$file\"; then
        # Preserve directory structure
        dir=\$(dirname \"\$file\")
        mkdir -p \"/tmp/${BACKUP_DIR}/\$dir\"
        cp \"\$file\" \"/tmp/${BACKUP_DIR}/\$file\"
        echo \"✓ Backed up: \$file\"
    fi
done

echo \"\"
echo \"📦 Backup created in /tmp/${BACKUP_DIR}\"
echo \"📊 Files backed up:\"
find /tmp/${BACKUP_DIR} -name '*.md' | wc -l
"

# Copy backup to host
echo ""
echo "💾 Copying backup to host..."
docker cp "nextcloud-aio-nextcloud:/tmp/${BACKUP_DIR}" "./${BACKUP_DIR}"

echo ""
echo "✅ Backup complete!"
echo "📁 Location: ./${BACKUP_DIR}"
echo ""
echo "To restore:"
echo "  docker cp ./${BACKUP_DIR}/. nextcloud-aio-nextcloud:/mnt/ncdata/"
