# Benjo - Workshop Management System

## Ringkasan Project

**Benjo** adalah sistem manajemen bengkel (workshop management system) berbasis web yang dibangun dengan Laravel 13.8 dan Filament 5.0 sebagai admin panel. Sistem ini dirancang untuk mencatat transaksi servis, mengelola barang, menghitung keuntungan, mengelola cashout (pengeluaran), serta sistem komisi untuk karyawan.

---

## Tech Stack

| Komponen | Versi/Tech |
|----------|-----------|
| PHP | ^8.3 |
| Laravel | ^13.8 |
| Filament (Admin Panel) | ^5.0 |
| Spatie Laravel Permission | ^8.3 (RBAC) |
| Laravel Octane | ^2.17 (FrankenPHP) |
| Database | SQLite (default), MySQL/MariaDB/PostgreSQL supported |
| Frontend (Admin) | Filament (Livewire + Tailwind CSS) |
| Frontend (Public) | Laravel Blade + Tailwind CSS 4 |
| Build Tool | Vite 8 |
| Deployment | Docker (FrankenPHP) |

---

## Struktur Direktori Utama

```
d:/projects/benjo/
├── app/
│   ├── Filament/              # Admin panel (Filament)
│   │   ├── Auth/              # Custom login (username-based)
│   │   ├── Pages/             # Dashboard, MyCommissionPage
│   │   ├── Resources/         # CRUD resources (Filament)
│   │   │   ├── TransactionResource/
│   │   │   ├── ItemResource/
│   │   │   ├── CashoutResource/
│   │   │   ├── SettingResource/
│   │   │   ├── UserResource/
│   │   │   ├── RoleResource/
│   │   │   ├── PermissionResource/   (hidden from nav)
│   │   │   └── PriceListResource/
│   │   └── Widgets/           # Dashboard widgets & charts
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php
│   │   │   └── TransactionPrintController
│   │   └── Middleware/
│   │       └── EnsureAdminAuthenticated.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Transaction.php
│   │   ├── TransactionItem.php
│   │   ├── TransactionCommission.php
│   │   ├── Item.php
│   │   ├── PriceList.php
│   │   ├── Cashout.php
│   │   └── Setting.php
│   ├── Policies/
│   │   ├── BasePolicy.php
│   │   ├── TransactionPolicy.php
│   │   ├── ItemPolicy.php
│   │   ├── CashoutPolicy.php
│   │   ├── SettingPolicy.php
│   │   ├── UserPolicy.php
│   │   ├── RolePolicy.php
│   │   ├── PermissionPolicy.php
│   │   └── PriceListPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── Filament/
│   │       └── AdminPanelProvider.php
│   └── Support/
│       ├── Access.php
│       ├── Money.php
│       └── PermissionMatrix.php
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── database.php
│   └── permission.php
├── database/
│   ├── database.sqlite
│   ├── migrations/            # 14 migrations
│   └── seeders/
│       └── DatabaseSeeder.php
├── docker/
│   ├── entrypoint.sh
│   └── php/php.ini
├── Dockerfile
├── resources/
│   ├── views/
│   │   ├── welcome.blade.php
│   │   ├── transactions/print.blade.php
│   │   └── filament/pages/my-commission-page.blade.php
│   ├── css/app.css
│   └── js/app.js
└── routes/
    ├── web.php
    └── console.php
```

---

## Alur Sistem (Flow)

### 1. Autentikasi & Otorisasi

```
User mengakses /admin
       │
       ▼
Filament Auth Middleware (Authenticate::class)
       │
       ▼
Login Page (app/Filament/Auth/Login.php)
  - Field: name (username), password
  - Menggunakan session-based auth (web guard)
       │
       ▼
canAccessPanel() check → User harus punya maksimal 1 role
       │
       ▼
Gate::before di AppServiceProvider
  - Jika user punya role "master" → bypass semua permission check
       │
       ▼
Setiap resource/page/policy method → check permission via $user->can()
  - Menggunakan Spatie Permission (RBAC)
  - Permissions tersimpan di table `permissions` (guard: web)
  - Roles tersimpan di table `roles`
```

### 2. Role & Permission System

**Tiga role default (dari DatabaseSeeder):**
- **master** → Akses penuh semua permission (hardcoded bypass via Gate::before)
- **admin** → Dashboard, Transaksi (CRUD), Barang (CRUD), Daftar Harga (CRUD), Cashout (CRUD), Pengaturan (View+Update)
- **kasir** → Dashboard, Transaksi (View Any, View, Create)

**Struktur Permission (dari Access.php):**
- `dashboard.view`
- `transactions.view_any`, `transactions.view`, `transactions.create`, `transactions.update`, `transactions.delete`, `transactions.delete_any`
- `items.view_any`, `items.view`, `items.create`, `items.update`, `items.delete`, `items.delete_any`
- `price_lists.view_any`, `price_lists.view`, `price_lists.create`, `price_lists.update`, `price_lists.delete`, `price_lists.delete_any`
- `cashouts.view_any`, `cashouts.view`, `cashouts.create`, `cashouts.update`, `cashouts.delete`, `cashouts.delete_any`
- `settings.view_any`, `settings.update`
- `users.view_any`, `users.view`, `users.create`, `users.update`, `users.delete`, `users.delete_any`
- `roles.view_any`, `roles.view`, `roles.create`, `roles.update`, `roles.delete`
- `permissions.view_any`

---

### 3. Transaction Flow (Core Business Logic)

**Pembuatan Transaksi:**
```
User klik "Tambah Transaksi" di Filament Admin
       │
       ▼
Form TransactionResource::form()
  Section 1: Data Customer & Kendaraan
    - customer_name, customer_phone, vehicle_name, service_description
  Section 2: Barang yang Digunakan
    - Repeater: transactionItems (hanya item dengan price_list_id = NULL)
      → Pilih dari master Item, qty, subtotal auto-calc
  Section 3: Layanan dari Daftar Harga
    - CheckboxList: price_list_picks[] (bukan relasi langsung)
    - Placeholder: total estimated price
  Section 4: Biaya Servis
    - service_fee (manual input)
       │
       ▼
CreateTransaction::mutateFormDataBeforeCreate()
  - Extract price_list_picks
  - Generate transaction_number: TRX-YYYYMMDD-XXXX
  - Set transaction_date = today()
       │
       ▼
Transaction::creating() boot event
  - Set default transaction_date & transaction_number
       │
       ▼
Transaction::saving() boot event
  - Hitung total_item_cost (dari items tanpa price_list_id)
  - Hitung total_income = service_fee + income dari price_list items
  - Hitung gross_profit = total_income - total_item_cost
       │
       ▼
afterCreate() → syncPriceListServices()
  - Sync baris layanan daftar harga sebagai transaction_items
  - Hapus yang tidak dipilih, tambah yang baru
       │
       ▼
Transaction::saved() boot event → syncCommissions()
  - Hapus semua commission lama untuk transaksi ini
  - Untuk setiap user dengan commission_active=true & percent > 0:
    - Buat TransactionCommission baru
    - amount = total_income × commission_percent / 100
       │
       ▼
TransactionItem::saved/deleted() → transaction->recalculateTotals()
  - Re-calculate totals when items change
  - Triggers syncCommissions again
```

**Edit Transaksi:**
```
EditTransaction page → load existing items
       │
       ▼
mutateFormDataBeforeSave() → extract price_list_picks
       │
       ▼
afterSave() → syncPriceListServices() → recalculateTotals()
       │
       ▼
Header Actions: Print PDF, Delete
```

---

### 4. Commission System

**Mekanisme Komisi:**
- Setiap User memiliki `commission_percent` (decimal) dan `commission_active` (boolean)
- Komisi dihitung dari `total_income` transaksi (bukan dari profit)
- Formula: `commission_amount = total_income × commission_percent / 100`
- Setiap kali transaksi di-save (create/update/delete item), semuanya di-recalculate
- Komisi di-delete dan di-recreate ulang setiap kali transaksi berubah
- User bisa melihat komisi sendiri di page "Komisi Saya" (MyCommissionPage)

**Syarat user mendapat komisi:**
- `commission_active` = true
- `commission_percent` > 0

---

### 5. Profit Calculation

```
total_item_cost = SUM(subtotal dari transaction_items WHERE price_list_id IS NULL)
                 → Barang yang digunakan (modal/pengeluaran)

total_income = service_fee + SUM(subtotal dari transaction_items WHERE price_list_id IS NOT NULL)
               → Biaya servis + layanan daftar harga (pemasukan)

gross_profit = total_income - total_item_cost
               → Untung kotor

net_profit (Dashboard) = gross_profit - total_cashout
                         → Untung bersih setelah pengeluaran
```

---

### 6. Dashboard & Widgets

**Dashboard (Pages/Dashboard.php):**
- Filter: Select year (dari data transaksi/cashout terlama sampai sekarang)
- Stats Overview:
  - Total Barang (count all items)
  - Total Transaction (count per year)
  - Total Profit (net profit = gross profit - cashout, per year)
  - Total Cashout (sum per year)
- Charts:
  - Monthly Transactions Chart (bar) — jumlah transaksi per bulan
  - Monthly Profit Chart (line) — gross profit & net profit per bulan
  - Monthly Cashout Chart (line) — total cashout per bulan

**MyCommissionPage (Komisi Saya):**
- MyCommissionStatsWidget:
  - Komisi Harian (hari ini)
  - Komisi Mingguan (minggu ini)
  - Komisi Bulanan (bulan ini)
  - Total Komisi (semua waktu)
- Data dari TransactionCommission WHERE user_id = auth()->user()->id

---

### 7. Item & Price List

**Item (Barang):**
- Master data barang yang digunakan di transaksi
- Fields: name, price (decimal), description
- Price used as default when selected in transaction
- Ini adalah "barang habis pakai" (modal)

**Price List (Daftar Harga):**
- Daftar layanan/jasa servis dengan harga tetap
- Fields: name, price (decimal), description
- Di transaksi, price_list menambah income (bukan cost)
- Tipe: jasa/layanan (bukan barang fisik)

---

### 8. Cashout

- Mencatat pengeluaran bengkel
- Fields: cashout_date, title, amount (decimal), description
- Cashout mengurangi net profit di dashboard
- Tidak berhubungan langsung dengan transaksi (standalone)

---

### 9. Settings

- Singleton record (hanya 1 row)
- Fields: shop_name, logo (file upload), address, phone_number
- Digunakan untuk:
  - Brand name di Filament panel
  - Brand logo di Filament panel
  - Header di print receipt
- SettingResource: hanya ada Edit page (redirect dari index)

---

### 10. Print Transaction

**Route:** `GET /admin/transactions/{transaction}/print`
- Middleware: Authenticate (Filament)
- Policy: TransactionPolicy::view
- Controller: TransactionPrintController (invokable)
- View: `resources/views/transactions/print.blade.php`
- Menampilkan:
  - Info toko (dari Settings)
  - Data customer & kendaraan
  - Daftar barang digunakan (price_list_id = NULL)
  - Daftar layanan (price_list_id NOT NULL)
  - Total pembayaran customer
- Ada tombol "Print" di UI

---

## Database Schema

### users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar UNIQUE | Username (login identifier) |
| password | varchar | Hashed |
| remember_token | varchar nullable | |
| commission_percent | decimal(5,2) nullable | Persentase komisi |
| commission_active | boolean default false | Aktif/tidak komisi |
| created_at | timestamp | |
| updated_at | timestamp | |

### items (Master Barang)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar | Nama barang |
| price | decimal(15,2) | Harga default |
| description | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### price_lists (Daftar Harga Layanan)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar | Nama layanan |
| price | decimal(15,2) | Harga layanan |
| description | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### transactions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| transaction_number | varchar UNIQUE | TRX-YYYYMMDD-XXXX |
| transaction_date | date | |
| customer_name | varchar | |
| customer_phone | varchar nullable | |
| vehicle_name | varchar nullable | |
| service_description | text nullable | |
| service_fee | decimal(15,2) | Biaya servis manual |
| total_item_cost | decimal(15,2) | Auto-calc: sum items tanpa price_list |
| total_income | decimal(15,2) | Auto-calc: service_fee + layanan |
| gross_profit | decimal(15,2) | Auto-calc: income - cost |
| created_at | timestamp | |
| updated_at | timestamp | |

### transaction_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| transaction_id | bigint FK → transactions | cascadeOnDelete |
| item_id | bigint FK → items nullable | nullOnDelete |
| price_list_id | bigint FK → price_lists nullable | restrictOnDelete |
| item_name | varchar | Snapshot nama saat transaksi |
| item_price | decimal(15,2) | Snapshot harga saat transaksi |
| quantity | unsignedInt default 1 | Min 1 |
| subtotal | decimal(15,2) | Auto-calc: price × qty |
| created_at | timestamp | |
| updated_at | timestamp | |

### transaction_commissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| transaction_id | bigint FK → transactions | cascadeOnDelete |
| user_id | bigint FK → users | cascadeOnDelete |
| percent | decimal(5,2) | Snapshot percent saat transaksi |
| amount | decimal(15,2) | Calculated: income × percent / 100 |
| created_at | timestamp | |
| updated_at | timestamp | |
| UNIQUE(transaction_id, user_id) | | |

### cashouts
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| cashout_date | date | |
| title | varchar | Judul pengeluaran |
| amount | decimal(15,2) | Jumlah |
| description | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### settings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shop_name | varchar | Nama bengkel |
| logo | varchar nullable | Path file logo |
| address | text nullable | |
| phone_number | varchar nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Spatie Permission Tables
- **permissions** (id, name, guard_name, timestamps)
- **roles** (id, name, guard_name, timestamps)
- **model_has_permissions** (permission_id, model_type, model_id)
- **model_has_roles** (role_id, model_type, model_id)
- **role_has_permissions** (permission_id, role_id)

---

## Model Events & Business Rules

### Transaction Model
```
creating:
  - transaction_date default = today()
  - transaction_number auto-generated if blank

saving:
  - Recalculate: total_item_cost, total_income, gross_profit
  - Barang (price_list_id = NULL) → cost
  - Layanan (price_list_id NOT NULL) → income

saved:
  → syncCommissions() (delete old + create new for active users)
```

### TransactionItem Model
```
saving:
  - Jika ada item_id & item_name kosong → auto-fill dari Item
  - Jika ada price_list_id & item_name kosong → auto-fill dari PriceList
  - quantity minimal 1
  - subtotal = item_price × quantity

saved / deleted:
  → transaction->recalculateTotals()
```

### User Model
```
canAccessPanel():
  - return true jika user punya minimal 1 role (maksimal 1 role dijamin database)

isMaster():
  - return true jika punya role "master"

hasActiveCommission():
  - return true jika commission_active = true AND percent > 0
```

---

## Key Support Classes

### Access.php
- Centralized permission constants (37 permissions)
- Human-readable labels (Bahasa Indonesia)
- `all()` — array semua permission slug
- `allGrouped()` — permissions grouped by module with sorted order
- Module order: dashboard → transactions → items → price_lists → cashouts → users → roles → permissions → settings

### Money.php
- `Money::rupiah(1000)` → "Rp 1.000"
- Format: `Rp ` + number_format(0 decimals, thousands separator `.`)

### PermissionMatrix.php
- UI helper untuk Role permission matrix
- `groupedPermissions()` — format untuk CheckboxList
- `groupedOptions()` — format grouped options
- `summary($selected)` — stats untuk role

### HasYearlyDashboardData (trait)
- `getSelectedYear()` — dari filter dashboard
- `getMonthLabels()` — Jan s/d Des
- `getMonthlyTransactionCounts()` — array 12 bulan
- `getMonthlyGrossProfitTotals()` — array 12 bulan
- `getMonthlyCashoutTotals()` — array 12 bulan
- `getMonthlyNetProfitTotals()` — array 12 bulan (gross - cashout)

---

## Routes

```php
// Public
GET /  → welcome.blade.php (landing page)

// Admin (Filament auto-generates resource routes)
GET /admin/transactions/{transaction}/print
  → TransactionPrintController (invokable)
  → Policy: TransactionPolicy::view
  → Middleware: Authenticate (Filament session)
```

---

## Docker / Deployment

**Dockerfile (Multi-stage):**
1. Stage 1: Composer install (vendor only)
2. Stage 2: FrankenPHP base image
   - Install su-exec
   - Copy app + vendor
   - Run `package:discover` + `filament:upgrade`
   - Create storage dirs + chown www-data
   - EXPOSE 8000
   - ENTRYPOINT: `/entrypoint.sh`
   - CMD: `php artisan octane:frankenphp --workers=auto --max-requests=500`

**Entrypoint.sh:**
1. mkdir + chown storage/data
2. `php artisan optimize:clear`
3. `php artisan migrate --force`
4. `php artisan config:cache`
5. `php artisan event:cache`
6. `php artisan route:cache`
7. `php artisan filament:optimize`
8. `php artisan storage:link`
9. Drop to www-data via su-exec

**PHP Config (Production):**
- upload_max_filesize: 50M
- post_max_size: 55M
- max_execution_time: 120
- memory_limit: 256M
- opcache enabled with aggressive caching

---

## Default Seeded Data

```php
// Roles created:
// 1. master (all permissions)
// 2. admin (dashboard, transactions CRUD, items CRUD, price_lists CRUD, cashouts CRUD, settings view+update)
// 3. kasir (dashboard, transactions view + create)

// Default user:
// Username: benjo
// Password: benjogarage2018
// Role: master
```

---

## Pola & Konvensi Kode

1. **Permission Check Pattern**: Semua policy method menggunakan `$user->can(Access::PERMISSION_CONSTANT)`
2. **Master Bypass**: Gate::before di AppServiceProvider — master role selalu return true
3. **Decimal Casting**: Semua field money di-cast ke `decimal:2`
4. **Model Events**: Business logic (auto-calc, sync) ada di booted() model
5. **Filament Resource Pattern**: Setiap resource punya Pages/ subfolder dengan custom page classes
6. **Soft Delete**: Tidak digunakan — delete langsung hard delete
7. **Indonesian Labels**: Semua UI label menggunakan Bahasa Indonesia
8. **Money Format**: Selalu gunakan `Money::rupiah()` untuk display currency

---

## Catatan Penting

1. **Transaksi terdiri dari dua jenis item:**
   - **Barang** (price_list_id = NULL) → mengurangi profit (cost)
   - **Layanan** (price_list_id NOT NULL) → menambah income (revenue)

2. **Komisi dihitung dari total income**, bukan dari profit

3. **Setting adalah singleton** — hanya ada 1 record di database

4. **Print receipt** menggunakan HTML view biasa (bukan PDF library), bisa di-print via browser print dialog

5. **Permission Resource** disembunyikan dari navigasi (`shouldHaveNavigation() = false`) — permission dikelola melalui Role Resource

6. **Dashboard year filter** otomatis menyesuaikan range tahun dari data tersedia

7. **Transaction number format**: `TRX-YYYYMMDD-XXXX` (sequence per hari)

8. **User Policy** melindungi master user dari edit/delete oleh user non-master

9. **Role Policy** melindungi master role dari edit/delete oleh user non-master

10. **Tidak ada soft delete** di semua model — semua delete bersifat permanen
