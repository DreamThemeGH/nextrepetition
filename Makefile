.PHONY: build dev watch lint test clean package deploy

NPM := npm
COMPOSER := composer

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
