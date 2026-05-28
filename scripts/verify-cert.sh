#!/usr/bin/env bash
# scripts/verify-cert.sh
#
# Verifies your certificate + key pair is valid and matches.
# Run after you've received the signed .crt from Nextcloud.
#
# Usage:
#   bash scripts/verify-cert.sh

set -e

APP_ID="flashcards"
CERT_DIR="$HOME/.nextcloud/certificates"

KEY="$CERT_DIR/$APP_ID.key"
CRT="$CERT_DIR/$APP_ID.crt"

ok=1
[ -f "$KEY" ] || { echo "✗ Missing key: $KEY"; ok=0; }
[ -f "$CRT" ] || { echo "✗ Missing cert: $CRT"; ok=0; }
[ "$ok" -eq 0 ] && exit 1

echo "Checking key + cert match..."
KEY_MOD=$(openssl rsa -noout -modulus -in "$KEY" 2>/dev/null | openssl md5)
CRT_MOD=$(openssl x509 -noout -modulus -in "$CRT" 2>/dev/null | openssl md5)

if [ "$KEY_MOD" = "$CRT_MOD" ]; then
  echo "✓ Key and certificate match"
else
  echo "✗ Key and certificate DO NOT match"
  exit 1
fi

echo
echo "Certificate info:"
openssl x509 -noout -subject -issuer -dates -in "$CRT"

echo
echo "=== Computing App Store registration signature ==="
echo "Use this when registering the app at: https://apps.nextcloud.com/developer/apps/new"
echo
echo -n "$APP_ID" | openssl dgst -sha512 -sign "$KEY" | openssl base64
