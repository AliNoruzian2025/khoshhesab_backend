# خوش‌حساب — Laravel API

بک‌اند اپلیکیشن خوش‌حساب، ساخته‌شده با Laravel، MySQL و Laravel Sanctum.

## اجرای محلی با Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

API پس از اجرا روی `http://localhost:8000/api` و phpMyAdmin روی
`http://localhost:8082` در دسترس است.

برای MySQL مقادیر زیر را در `.env` تنظیم کنید:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=khoshhesab
DB_USERNAME=root
DB_PASSWORD=یک-رمز-قوی
```

کلیدهای سامانه پیامکی نیز فقط باید در `.env` شخصی قرار بگیرند و این فایل در
Git ذخیره نمی‌شود:

```dotenv
MSGWAY_API_KEY=
MSGWAY_TEMPLATE_ID=
```

## آزمون‌ها

```bash
docker compose exec app php artisan test
```

## نکات امنیتی

- فایل `.env`، توکن‌ها، لاگ‌ها و تصاویر بارگذاری‌شده در `.gitignore` قرار دارند.
- در محیط Production مقدار `APP_DEBUG` را `false` کنید.
- رمزها و کلیدهای واقعی را هیچ‌وقت داخل Repository قرار ندهید.
