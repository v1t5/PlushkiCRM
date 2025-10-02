## Цели проекта

Приложение для автоматизации процессов пекарни:

- Централизованное поступление заказов от клиентов через Telegram-бота и предусматривает возможность интеграции других каналов (например, веб-формы, сторонние CRM или мессенджеры).
- Простой, но расширяемый учет товарных позиций, остатков и движения продукции.
- Автоматизированный расчет раскладки (производственного задания) для пекарей на смену на основе поступивших заявок и текущих остатков.
- Возможность расширения функционала и интеграции с внешними системами (например доставка, аналитика).

## Описание

Модульное PHP-приложение (Symfony), разделённое на самостоятельные компоненты, каждый из которых развёртывается как отдельный Docker-образ. Основная бизнес-логика и API реализованы в [testo-core](https://github.com/v1t5/testo-core), Telegram-бот — в [testo-tg-bot](https://github.com/v1t5/testo-tg-bot), проксирование — в [testo-nginx-proxy](https://github.com/v1t5/testo-nginx-proxy).

## Быстрый старт

```sh
# Клонируйте репозиторий и перейдите в папку проекта
git clone <repo-url>
cd testo-app

# Скопируйте .env.example → .env и настройте переменные
cp .env.example .env

# Запустите проект (pull, build, up, composer install, миграции, фикстуры)
make init

# Остановить проект
docker compose down
```

## Основные сервисы (docker-compose)

- **php-fpm** — основной backend (Symfony, [testo-core](https://github.com/v1t5/testo-core))
- **php-worker** — обработчик очередей (Messenger)
- **tg-bot** — Telegram-бот ([testo-tg-bot](https://github.com/v1t5/testo-tg-bot))
- **nginx** — прокси ([testo-nginx-proxy](https://github.com/v1t5/testo-nginx-proxy))
- **database** — Postgres 16
- **redis** — Кэш
- **EFK** — Логирование

## Полезные команды Makefile

- `make init` — полный запуск (pull, build, up, composer install, миграции, фикстуры)
- `make up` — поднять сервисы
- `make migrate` — применить миграции
- `make fixtures` — загрузить тестовые данные
- `make clean-log` / `make clean-cache` — очистка логов/кеша
- `make elk-up` — поднять стек логирования
- `make messenger-consume` — запустить обработчик очередей
- `make refactoring` — автолинтинг JS и PHP
- `make phpstan` — статический анализ PHP

## Переменные окружения

Создайте файл `.env` на основе `.env.example` и укажите:

- APP_ENV, APP_SECRET, JWT_SECRET
- DATABASE_URL, POSTGRES_DB, POSTGRES_USER, POSTGRES_PASSWORD
- ELASTIC_HOST, ELASTIC_PORT, ELASTIC_INDEX, ELASTIC_HTTP_USER, ELASTIC_HTTP_PASS
- MESSENGER_TRANSPORT_DSN, RABBITMQ_DEFAULT_USER, RABBITMQ_DEFAULT_PASS
- MONGO_ROOT_USERNAME, MONGO_ROOT_PASSWORD
- TELEGRAM_BOT_TOKEN, TELEGRAM_WEBHOOK_URL, API_USER, API_PASSWORD

## Ссылки на компоненты

- [testo-core](https://github.com/v1t5/testo-core)
- [testo-tg-bot](https://github.com/v1t5/testo-tg-bot)
- [testo-nginx-proxy](https://github.com/v1t5/testo-nginx-proxy)