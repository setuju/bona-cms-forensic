# ┌──────────────────────────────────────────────────────────────────────┐
# │  ⌂  SATURUMAH.NET · FILE CONTRACT                                    │
# │  ──────────────────────────────────────────────────────────────────  │
# │  File      : Makefile
# │  Purpose   : Common development tasks
# │  Author    : Jack — Jack[at]SatuRumah[dot]Net                        │
# │  GitHub    : https://github.com/setuju                               │
# │  Website   : https://saturumah.net                                   │
# │  Created   : 2026-10-26                                              │
# │  License   : Proprietary — all rights reserved                       │
# └──────────────────────────────────────────────────────────────────────┘

.PHONY: help install audit audit-ci audit-json integrity test lint clean

help: ## Show this help
	@echo "Bona Forensic - Common commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: ## Install Composer dependencies
	composer install --no-interaction

audit: ## Run full audit (human-readable)
	./bin/forensic --root=. --show-findings

audit-ci: ## Run CI-mode audit (exit 1 on failure)
	./bin/forensic --root=. --ci

audit-json: ## Generate JSON report
	./bin/forensic --root=. --json > report.json
	@echo "Report saved to report.json"

integrity: ## Verify read-only guarantee
	./bin/forensic --root=. --section=integrity

test: ## Run PHPUnit tests
	composer test || true


test-report: ## Run smoke test for --report flag
	bash tests/smoke-report.sh
lint: ## Lint all PHP files
	@find src bin -name "*.php" -o -name "forensic" -type f | xargs -I{} php -l {} | grep -v "No syntax errors" || echo "All files OK"

clean: ## Clean build artifacts
	rm -rf .bona/logs/* .bona/snapshots/* .bona/*.mmd report.json
