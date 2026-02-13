#!/bin/bash
# Apply migration to all .md files with SR tags
set -e

MIGRATION_SCRIPT="/tmp/migrate.php"
DATA_DIR="/mnt/ncdata"

echo "🔄 Applying migration to all .md files..."
echo ""

total=0
migrated=0

# Find all .md files with SR tags
docker exec nextcloud-aio-nextcloud bash -c "
find ${DATA_DIR} -name '*.md' -type f 2>/dev/null | while read file; do
    if grep -q '<!--SR:' \"\$file\"; then
        echo \"📄 Processing: \$(basename \"\$file\")\"
        php ${MIGRATION_SCRIPT} \"\$file\" | grep -E '(Statistics|Corrupted|Capped|Unchanged|File updated)'
        echo \"\"
    fi
done
"

echo ""
echo "✅ Migration complete!"
