.PHONY: help start stop restart logs fresh test diagrams

help: ## List targets
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  %-10s %s\n", $$1, $$2}'

start: ## Build and start the whole stack on http://localhost:8000
	@sh scripts/start.sh

stop: ## Stop the stack (data is kept)
	docker compose down

restart: stop start ## Stop, rebuild and start

logs: ## Follow the app's logs
	docker compose logs -f web php worker scheduler reverb

fresh: ## Delete all data (database, images) and start again
	docker compose down -v
	@sh scripts/start.sh

test: ## Run Pint, Larastan and the test suite in Docker, on the production PHP
	docker compose --profile test build test
	docker compose --profile test run --rm test

diagrams: ## Render the flow diagram (docs/diagrams/flow.mmd, Mermaid) to PNG, in Docker
	docker run --rm -u "$$(id -u):$$(id -g)" -v "$(CURDIR)/docs/diagrams:/data" \
		minlag/mermaid-cli:12.0.1 -i flow.mmd -o flow.png -s 3 -b white -q
