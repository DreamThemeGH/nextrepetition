.PHONY: build dev watch lint test clean package deploy appstore

NPM := npm
COMPOSER := composer
APP_NAME   := flashcards
CERT_DIR   := $(HOME)/.nextcloud/certificates
BUILD_DIR  := build/artifacts/appstore

# Build production JS/CSS
build:
	$(NPM) run build

# Build development JS/CSS
dev:
	$(NPM) run dev

# Watch mode
watch:
	$(NPM) run watch

# Install all dependencies
deps:
	$(COMPOSER) install --no-dev
	$(NPM) ci

# Install dev dependencies
deps-dev:
	$(COMPOSER) install
	$(NPM) ci

# Lint
lint:
	$(NPM) run lint
	$(NPM) run typecheck

# Run tests
test:
	$(COMPOSER) run test
	$(NPM) run test

# Clean build artifacts
clean:
	rm -rf js/ css/ node_modules/ vendor/

# Package for deployment
package: build
	@echo "Packaging flashcards app..."
	@mkdir -p /tmp/flashcards
	@rsync -a --exclude='node_modules' --exclude='.git' --exclude='src' \
		--exclude='tests' --exclude='Makefile' --exclude='*.ts' \
		--exclude='tsconfig.json' --exclude='vite.config.ts' \
		--exclude='ARCHITECTURE.md' --exclude='.github' \
		./ /tmp/flashcards/
	@echo "Package ready at /tmp/flashcards"

# Deploy to NC AIO container
deploy: build
	@echo "Deploying to Nextcloud AIO container..."
	docker cp js/. nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/js/
	docker cp css/. nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/css/
	docker cp lib/. nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/lib/
	docker cp appinfo/. nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/appinfo/
	docker cp templates/. nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/templates/
	docker cp img/. nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/img/
	docker cp l10n/. nextcloud-aio-nextcloud:/var/www/html/custom_apps/flashcards/l10n/
	@echo "Deploy complete!"

# Build signed release archive for Nextcloud App Store
# Requires: ~/.nextcloud/certificates/flashcards.key + flashcards.crt
# Run scripts/get-cert.sh first if you don't have them.
appstore: build
	@echo "=== Building App Store release for $(APP_NAME) ==="
	rm -rf $(BUILD_DIR)
	mkdir -p $(BUILD_DIR)
	rsync -a \
		--exclude='.git' \
		--exclude='node_modules' \
		--exclude='src' \
		--exclude='tests' \
		--exclude='scripts' \
		--exclude='build' \
		--exclude='backup_sr*' \
		--exclude='*.ts' \
		--exclude='tsconfig.json' \
		--exclude='vite.config.ts' \
		--exclude='vitest.config.ts' \
		--exclude='phpunit.xml' \
		--exclude='package.json' \
		--exclude='package-lock.json' \
		--exclude='composer.json' \
		--exclude='Makefile' \
		--exclude='.gitignore' \
		--exclude='.github' \
		--exclude='ARCHITECTURE.md' \
		--exclude='BUGFIXES*' \
		--exclude='TODO.md' \
		--exclude='deploy.sh' \
		./ $(BUILD_DIR)/$(APP_NAME)/
	cd $(BUILD_DIR) && tar -czf $(APP_NAME).tar.gz $(APP_NAME)/
	@echo "=== Archive ready: $(BUILD_DIR)/$(APP_NAME).tar.gz ==="
	@if [ -f $(CERT_DIR)/$(APP_NAME).key ]; then \
		echo "=== Signature for App Store registration/upload ==="; \
		openssl dgst -sha512 -sign $(CERT_DIR)/$(APP_NAME).key \
			$(BUILD_DIR)/$(APP_NAME).tar.gz | openssl base64; \
	else \
		echo "⚠ No private key found at $(CERT_DIR)/$(APP_NAME).key"; \
		echo "  Run scripts/get-cert.sh before App Store publication."; \
	fi
