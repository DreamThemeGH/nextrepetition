#!/usr/bin/env bash
# scripts/get-cert.sh
#
# One-time setup to get your app signing certificate from Nextcloud.
# Run this ONCE before your first App Store release.
#
# Steps performed:
#   1. Generate private key + CSR for app id "flashcards"
#   2. Print the CSR — you submit it as a PR to:
#      https://github.com/nextcloud/app-certificate-requests
#   3. After Nextcloud approves the PR, download the .crt and save it here.
#
# Usage:
#   bash scripts/get-cert.sh

set -e

APP_ID="flashcards"
CERT_DIR="$HOME/.nextcloud/certificates"

echo "=== Nextcloud App Certificate Setup for '$APP_ID' ==="
echo

mkdir -p "$CERT_DIR"

if [ -f "$CERT_DIR/$APP_ID.key" ]; then
  echo "✓ Private key already exists: $CERT_DIR/$APP_ID.key"
else
  echo "Generating 4096-bit RSA private key + CSR..."
  openssl req -nodes -newkey rsa:4096 \
    -keyout "$CERT_DIR/$APP_ID.key" \
    -out    "$CERT_DIR/$APP_ID.csr" \
    -subj   "/CN=$APP_ID"
  chmod 600 "$CERT_DIR/$APP_ID.key"
  echo "✓ Key saved to:  $CERT_DIR/$APP_ID.key  (KEEP SECRET)"
  echo "✓ CSR saved to:  $CERT_DIR/$APP_ID.csr"
fi

echo
echo "════════════════════════════════════════════════════════════"
echo " NEXT STEP — submit this CSR as a Pull Request to:"
echo " https://github.com/nextcloud/app-certificate-requests"
echo
echo " 1. Fork the repo above"
echo " 2. Create folder:  $APP_ID/"
echo " 3. Create file:    $APP_ID/$APP_ID.csr  with the content below"
echo " 4. Open a PR — link your public repo in the PR description"
echo "════════════════════════════════════════════════════════════"
echo
cat "$CERT_DIR/$APP_ID.csr"
echo
echo "════════════════════════════════════════════════════════════"
echo " After Nextcloud signs your CSR:"
echo "  1. Copy the .crt content from the PR response"
echo "  2. Save it to: $CERT_DIR/$APP_ID.crt"
echo "  3. Run:  bash scripts/verify-cert.sh"
echo "════════════════════════════════════════════════════════════"
