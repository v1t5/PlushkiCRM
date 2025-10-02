NODE_MODULES = ./node_modules
VENDOR = ./vendor

init: pull build up composer-install migrate fixtures

pull:
	docker compose pull

build:
	docker compose build

up:
	docker compose up -d

composer-install:
	docker compose run --rm php-fpm composer install

migrate:
	docker compose run --rm php-fpm php bin/console doctrine:migrations:migrate --no-interaction

fixtures:
	docker compose run --rm php-fpm php bin/console hautelook:fixtures:load --no-interaction

##
# UTILS
##
clean-log:
	rm -rf ./var/log

clean-cache:
	rm -rf ./var/cache

watch:
	npm run watch

##
# ELK
##
elk-up:
	docker compose -f compose.elk.yaml up -d

elk-logs:
	docker compose -f compose.elk.yaml logs -f

elk-status:
	docker compose -f compose.elk.yaml ps elasticsearch kibana

elk-clean:
	docker compose down -v
	docker volume rm testo-app_elasticsearch_data

##
# MESSENGER
##
messenger-consume:
	docker compose run --rm php-fpm php bin/console messenger:consume async -vv

messenger-failed:
	docker compose run --rm php-fpm php bin/console messenger:failed:show

messenger-retry:
	docker compose run --rm php-fpm php bin/console messenger:failed:retry

messenger-setup:
	docker compose run --rm php-fpm php bin/console messenger:setup-transports

##
# RABBITMQ
##
rabbitmq-status:
	docker compose ps rabbitmq

rabbitmq-logs:
	docker compose logs -f rabbitmq

rabbitmq-clean:
	docker compose down rabbitmq
	docker volume rm testo-app_rabbitmq_data

##
# REFACTORING
##

check:
	make refactoring --keep-going

refactoring: eslint php-cs-fixer

eslint:
	${NODE_MODULES}/.bin/eslint assets/js/ --ext .js,.vue --fix

php-cs-fixer:
	${VENDOR}/bin/php-cs-fixer fix src/  --verbose

phpstan:
	${VENDOR}/bin/phpstan analyse src --level 4
