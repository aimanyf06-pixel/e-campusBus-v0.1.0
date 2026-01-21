# e-campusBus Database Setup

_Version: e-campusBus v0.1.0_

## 📋 Ringkasan
Panduan ini untuk menyediakan database yang sama pada mana-mana peranti dengan menyalin dan import skema asal. Semua fail SQL sudah disediakan dalam repo.

## 📁 Lokasi Fail Penting
- 📄 **Skema asas sistem**: [public/sql/bus_management.sql](public/sql/bus_management.sql)
- 📄 **Skema notifikasi** (jika perlu): [public/sql/create_notification_system.sql](public/sql/create_notification_system.sql)
- 🔧 **Skrip setup automasi**: [public/setup/setup_notifications.php](public/setup/setup_notifications.php)

## 🌐 Cara Import (phpMyAdmin)
1. ✅ Log masuk phpMyAdmin.
2. ✅ Cipta database `bus_management` (charset utf8mb4).
3. ✅ Pilih database → Import → pilih `public/sql/bus_management.sql` → Go.
4. ✅ Untuk modul notifikasi, ulang Import dengan `public/sql/create_notification_system.sql` (jika belum tersedia dalam bus_management.sql anda).

## 💻 Cara Import (CLI MySQL)
```bash
# ganti <mysql_user> jika bukan root; tambah -p jika ada kata laluan
mysql -u <mysql_user> -e "CREATE DATABASE IF NOT EXISTS bus_management CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u <mysql_user> bus_management < public/sql/bus_management.sql
# Modul notifikasi (jika perlu)
mysql -u <mysql_user> bus_management < public/sql/create_notification_system.sql
```

## ⚡ Setup Notifikasi Pantas (alternatif)
Jika tidak mahu import fail kedua secara manual, layari:
- 🔗 [public/setup/setup_notifications.php](public/setup/setup_notifications.php)

Halaman ini akan mencipta jadual `booking_notifications` dan `activity_logs` jika belum wujud.

## ✔️ Semak Selepas Import
- 📊 **Pastikan jadual utama wujud**: `users`, `routes`, `buses`, `schedules`, `bookings`, `notifications` (jika ada), `activity_logs` (selepas setup notifikasi).
- 🧪 **Uji sambungan dengan skrip diagnostik**:
  - 🔍 [public/tests/check_database.php](public/tests/check_database.php)
  - 👥 [public/tests/check_users.php](public/tests/check_users.php)

## 🔄 Petua Migrasi/Salinan
- 📦 **Untuk pindah ke peranti lain**, cukup salin fail projek dan dump DB:
```bash
mysqldump -u <mysql_user> bus_management > backup_bus_management.sql
```
- 🔧 **Pulih di peranti baharu**:
```bash
mysql -u <mysql_user> -e "CREATE DATABASE IF NOT EXISTS bus_management;"
mysql -u <mysql_user> bus_management < backup_bus_management.sql
```
- ⚙️ **Pastikan konfigurasi sambungan** di [public/config/database.php](public/config/database.php) sepadan (host, user, password, nama DB).
