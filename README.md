# خوش‌حساب

مخزن کامل پروژه خوش‌حساب شامل:

- `backend/`: API لاراول، migrationها، تست‌ها و تنظیمات Docker مستقل
- `android-app/`: اپلیکیشن Native اندروید با Kotlin و Jetpack Compose
- `docker/` و `docker-compose.yml`: اجرای محیط توسعه بک‌اند از ریشه پروژه

## اجرای بک‌اند

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

API روی `http://localhost:8000/api` در دسترس خواهد بود.

## اجرای اندروید

پوشه `android-app` را در Android Studio باز کنید، آدرس API را در
`android-app/app/build.gradle.kts` متناسب با Emulator یا IP شبکه تنظیم کنید
و پروژه را Run بگیرید.

## امنیت

فایل‌های `.env`، توکن‌ها، وابستگی‌ها، خروجی Build، لاگ‌ها و فایل‌های
بارگذاری‌شده کاربران در Git ذخیره نمی‌شوند.
