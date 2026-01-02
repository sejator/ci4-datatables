# Changelog

Semua perubahan penting pada library ini akan didokumentasikan di file ini.

---

## v1.0.4

### ✨ Fitur Baru

- Menambahkan method **`withRelation()`** untuk memuat relasi data tanpa menggunakan `JOIN`
- Mendukung **lazy-loading relasi**, relasi hanya di-load jika kolom terkait digunakan
- Mendukung **nested relation (relasi bertingkat)** untuk kebutuhan data kompleks
- Relasi dimuat setelah query utama sehingga aman untuk pagination server-side DataTables

### ⚙️ Performa & Arsitektur

- Menghindari duplikasi row akibat `JOIN` pada relasi 1–N
- Menghilangkan risiko error pagination dan count pada query relasional
- Menggunakan 1 query utama + 1 query relasi (tanpa N+1 query)
- Optimasi beban query dengan opsi `onlyIfUsedIn`

### 🔐 Stabilitas & Kompatibilitas

- 100% **backward compatible** dengan kode DataTables existing
- Tidak memengaruhi mekanisme:
  - Pagination
  - Searching
  - Ordering
  - Count total & filtered
- Aman digunakan bersamaan dengan fitur `searchable()` dan `orderable()`

---

## v1.0.2

### 🔧 Perbaikan

- Memperbaiki mekanisme **search** agar aman digunakan pada query dengan `JOIN`
- Memperbaiki **ordering** agar hanya menggunakan kolom yang tervalidasi
- Mencegah error SQL akibat kolom ambigu pada proses search dan ordering
- Menyesuaikan prefix tabel secara otomatis pada query join

### 🔐 Keamanan & Stabilitas

- Validasi kolom search dan order menggunakan whitelist
- Mencegah SQL injection melalui parameter DataTables
- Mengabaikan request order/search pada kolom yang tidak terdaftar

### ⚙️ Optimalisasi

- Menyederhanakan logika mapping kolom DataTables ke Query Builder
- Query lebih stabil untuk dataset besar dengan banyak relasi (`JOIN`)

---

## v1.0.1

### 🔧 Perbaikan

- Memperbaiki perhitungan `recordsTotal` dan `recordsFiltered` agar sesuai standar DataTables server-side
- Memperbaiki bug pagination yang menyebabkan jumlah data tidak konsisten
- Perbaikan mekanisme counting pada query yang menggunakan `GROUP BY` atau `COUNT DISTINCT`
- Mencegah error SQL pada query `COUNT` dengan membungkus subquery secara aman

### 🔐 Keamanan & Stabilitas

- Mengamankan proses search dengan whitelist kolom (`searchable()`)
- Mencegah search dan ordering pada kolom tidak valid atau tanpa prefix tabel
- Mengurangi ketergantungan terhadap request DataTables dari frontend

### ⚙️ Optimalisasi

- Optimalisasi cloning Query Builder untuk kebutuhan:
  - Query data
  - Count total
  - Count filtered
- Query lebih konsisten dan stabil untuk penggunaan `JOIN` dan pagination besar

---

## v1.0.0

- Rilis awal CI4 DataTables Helper
- Mendukung pagination, searching, filtering, dan ordering
- Dukungan debug SQL dan logging query
