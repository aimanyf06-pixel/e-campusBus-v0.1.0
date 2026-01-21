# e-campusBus Project Structure

_Version: e-campusBus v0.1.0_

## 🗂️ Root Layout (Ringkas)
```
i-bus/
├── public/                 # Web root
│   ├── admin/              # Admin: dashboard, manage buses/routes/users, reports, reset
│   ├── student/            # Student: dashboard, bookings, make/view booking, routes, profile
│   ├── driver/             # Driver: dashboard, notifications, assign bus, schedule, passengers, performance, profile
│   ├── api/                # API endpoints (notifications, etc.)
│   ├── config/             # Database and path configs
│   ├── includes/           # Shared auth, autoload, helpers, header
│   ├── assets/             # CSS/JS
│   ├── sql/                # Database schema files
│   ├── tests/              # Diagnostic and helper scripts
│   ├── index.php           # Landing (public entry)
│   ├── login.php           # Login form
│   ├── login_new.php       # Alt login form
│   ├── register.php        # Registration
│   ├── forgot_password.php # Reset flow (request)
│   ├── reset_password.php  # Reset form
│   ├── create_admin.php    # Bootstrap admin creator
│   └── logout.php          # Session logout
├── USAGE_GUIDE.md              # Panduan penggunaan (student/driver)
├── ADMIN_MAINTENANCE_GUIDE.md  # Panduan penyelenggaraan admin
├── SYSTEM_STATUS.md            # Ringkasan ciri & baikan semasa
├── NOTIFICATION_SYSTEM_SUMMARY.txt # Ringkasan sistem notifikasi (teks)
└── sql/                        # Salinan skema luar public (jika ada)
```

## 📌 Nota Pantas
- ✅ `public/` ialah akar dokumen pelayan web; semua halaman utama berada di sini.
- ✅ `public/tests/` menyimpan skrip diagnostik; lindungi atau pindahkan jika di produksi.
- ✅ `public/sql/` menyimpan skema seperti `i_bus_system.sql`; guna untuk import awal.
- ✅ Fail panduan utama: `USAGE_GUIDE.md`, `ADMIN_MAINTENANCE_GUIDE.md`, `SYSTEM_STATUS.md`.
