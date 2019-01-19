.PHONY: build
.PHONY: cs
.PHONY: phpstan
.PHONY: assets
.PHONY: prepare-rabbit


build:
	composer install --no-interaction
	npm install
	./node_modules/.bin/gulp


build-staging:
	composer validate
	composer install --no-interaction
	npm install
	./node_modules/.bin/gulp

prepare-rabbit:
    php www/index.php rabbitmq:setup-fabric


assets:
	npm install
	gulp

cs:
	git clean -xdf output.cs
	composer install --no-interaction
	- vendor/bin/phpcs app/ --standard=vendor/pd/coding-standard/src/PeckaCodingStandard/ruleset.xml --report-file=output.cs
	- vendor/bin/phpcs app/ --standard=vendor/pd/coding-standard/src/PeckaCodingStandardStrict/ruleset.xml --report-file=output-strict.cs
	- test -f output-strict.cs && cat output-strict.cs >> output.cs && rm output-strict.cs


phpstan:
	git clean -xdf output.phpstan
	composer install --no-interaction
	- ./vendor/bin/phpstan analyse app/ --level 1 -c phpstan.neon --no-progress &> output.phpstan
