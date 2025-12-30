# 📘 CI4 DataTables Helper

Library helper untuk integrasi jQuery DataTables (server-side) di CodeIgniter 4, dengan fokus:

✅ Aman  
✅ Mudah dipakai  
✅ Siap production

### Fitur Utama

- Pagination (server-side)
- Searching (global search)
- Filtering (where & conditional)
- Ordering
- Debug SQL lengkap
- Search aman (whitelist kolom)

### 1️⃣ Penggunaan Dasar

```php
use Sejator\DataTables\DataTables;

return DataTables::from('users')
    ->select('id, name, email, status')
    ->searchable(['name', 'email']) // 🔒 whitelist kolom search
    ->make();
```

📌 Catatan Penting

- `searchable()` sangat direkomendasikan
- Kolom di luar whitelist tidak akan ikut di-search
- Tanpa `searchable()`, library akan fallback ke request DataTables (kurang aman)

### 2️⃣ Dengan Filter (Where & Conditional)

Filter statis

```php
return DataTables::from('users')
    ->select('id, name, email, status')
    ->where('status', 'active')
    ->searchable(['name', 'email'])
    ->make();
```

Filter dinamis (opsional)

```php
$status = $this->request->getGetPost('status');

return DataTables::from('users')
    ->select('*')
    ->when($status, fn ($q, $v) => $q->where('status', $v))
    ->searchable(['name', 'email'])
    ->make();
```

✔ Jika $status kosong (null, ''), filter tidak diterapkan

### 3️⃣ Debug SQL Lengkap (Mode Debug)

Digunakan untuk melihat seluruh query yang dipakai DataTables:

- Query data
- Count total
- Count filtered

```php
return DataTables::from('users')
    ->select('id, name, email, status')
    ->where('status', 'active')
    ->searchable(['name', 'email'])
    ->debug()
    ->make();
```

Output JSON Debug:

```json
{
  "debug": true,
  "queries": {
    "data": "SELECT id, name, email, status FROM users WHERE status = 'active' LIMIT 10 OFFSET 0",
    "count_all": "SELECT COUNT(*) FROM users",
    "count_filtered": "SELECT COUNT(*) FROM users WHERE status = 'active'"
  }
}
```

📌 Kegunaan debug

- Validasi pagination
- Cek search & filter
- Audit performa query
- Troubleshooting hasil DataTables

### 4️⃣ Dump SQL Langsung (Development Only)

Digunakan saat development untuk dump SQL dan menghentikan eksekusi.

```php
DataTables::from('users')
    ->select('*')
    ->where('status', 'inactive')
    ->ddSql();
```

Output:

```sql
SELECT * FROM users WHERE status = 'inactive'
```

⚠️ Jangan dipakai di production

### 5️⃣ Logging Query ke Log CI4

Query akan dicatat ke file log CI4:

📁 writable/logs/log-YYYY-MM-DD.php

```php
DataTables::from('users')
    ->select('*')
    ->where('status', 'pending')
    ->logSql()
    ->make();
```

Contoh log:

```txt
DEBUG - DataTables SQL: SELECT * FROM users WHERE status = 'pending'
```

### 6️⃣ Search Aman (Whitelist Column)

📌 Wajib digunakan jika query menggunakan JOIN atau alias kolom.

Tanpa `searchable()`, DataTables dapat menghasilkan query tidak valid atau fitur pencarian tidak sesuai.

```php
->searchable(['name', 'email'])
```

✔ Mencegah:

- SQL injection via DataTables request
- Search ke kolom yang tidak diinginkan

❌ Tanpa searchable():

- Semua kolom `searchable=true` dari frontend akan dipakai
- Kurang aman & sulit dikontrol

### 7️⃣ Ringkasan Method Penting

| Method              | Fungsi                 |
| ------------------- | ---------------------- |
| `from($table)`      | Tentukan tabel         |
| `select()`          | Kolom yang diambil     |
| `where()`           | Filter data            |
| `when()`            | Filter kondisional     |
| `searchable()`      | Whitelist kolom search |
| `orderBy()`         | Sorting manual         |
| `debug()`           | Tampilkan SQL lengkap  |
| `ddSql()`           | Dump SQL & stop        |
| `logSql()`          | Log SQL ke CI4         |
| `make()` / `draw()` | Eksekusi DataTables    |

📌 `make()` dan `draw()` setara (pilih salah satu untuk konsistensi)

### 🔄 reset()

Digunakan untuk mengembalikan DataTables ke kondisi awal.

📌 Wajib dipakai jika instance digunakan lebih dari sekali

```php
$dt = DataTables::from('users');

$dt->where('status', 'active')->make();

$dt->reset()
   ->where('status', 'inactive')
   ->make();
```
