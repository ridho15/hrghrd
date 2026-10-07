#!/bin/sh
set -e

# Tunggu database MySQL siap menerima koneksi (jika koneksi bertipe mysql)
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "mysql" ]; then
  echo "Menunggu koneksi database MySQL di $DB_HOST:${DB_PORT:-3306}..."
  until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Throwable \$e) { exit(1); }"; do
    echo "Database belum siap, mencoba lagi dalam 2 detik..."
    sleep 2
  done
  echo "Database MySQL berhasil terhubung!"
fi

# Pastikan folder runtime storage Laravel tersedia dengan perizinan yang tepat
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate APP_KEY jika belum terdefinisi
if [ -z "$APP_KEY" ]; then
  echo "APP_KEY belum disetel. Menghasilkan APP_KEY baru..."
  php artisan key:generate --force
fi

# Jalankan migrasi database otomatis jika diizinkan
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "Menjalankan migrasi database..."
  php artisan migrate --force
fi

# Cache konfigurasi & rute untuk optimalisasi produksi
if [ "$APP_ENV" = "production" ]; then
  echo "Melakukan caching konfigurasi dan view..."
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

echo "Aplikasi HR Group siap berjalan. Memulai web server..."
exec "$@"
