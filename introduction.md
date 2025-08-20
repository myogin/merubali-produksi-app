# Sistem Manajemen Produksi & Stok (Laravel)

**Perusahaan**: CV. Merubali Natural  
**Versi**: 1.0 (Draft Laravel)  
**Penyusun**: Yogi

---

## 📌 Gambaran Umum

Aplikasi ini menggantikan sistem Google Sheets + Apps Script menjadi aplikasi berbasis **Laravel + MySQL**.  
Tujuannya: melacak **stok kemasan** dan **barang jadi per batch**, dengan alur:

1. **Form Penerimaan** → menambah stok kemasan.
2. **Form Produksi** → mengurangi stok kemasan sesuai **BOM**, lalu menambah stok barang jadi per batch.
3. **Form Pengiriman** → mengurangi stok barang jadi per batch.
4. Semua pergerakan dicatat di **Log Transaksi (Ledger)** untuk audit.

---

## 📊 Struktur Database (ERD)

### 1. Master Data

-**products**

    -   Kode barang jadi (contoh: `CCO-CTN50`, `CCO-CTN24`)
    -   Nama barang jadi (contoh: `STP-CCO-50`, `STP-CCO-24`)

-**packaging_items**

    -   Kode kemasan (contoh: `STP-CCO-50`, `CTN50`, `CTN24`)
    -   Nama kemasan
    -   Satuan dasar (`pcs`)

-**boms** (Bill of Materials) - Definisi kebutuhan kemasan untuk membuat 1 karton produk jadi - Contoh: - `CCO-CTN50` → 50 × `STP-CCO-50` + 1 × `CTN50` - `CCO-CTN24` → 24 × `STP-CCO-50` + 1 × `CTN24`

### 2. Transaksi

-**receipts** (header penerimaan kemasan) -**receipt_items** (detail penerimaan kemasan)

-**production_batches** (batch produksi barang jadi)

    -   Menyimpan No PO, kode batch (MFD), jumlah produksi, dan catatan.

-**shipments** (header pengiriman barang jadi) -**shipment_items** (detail pengiriman per batch produk)

### 3. Audit / Buku Besar

-**stock_movements** - Semua pergerakan stok: +/− kemasan, + barang jadi, − barang jadi. - Menjadi **sumber tunggal kebenaran** untuk laporan stok & audit trail.

---

## 📂 Alur Bisnis

### 1. Penerimaan (Receiving)

-User isi form penerimaan (tanggal, jenis kemasan, jumlah diterima, link surat jalan).
-Sistem simpan ke `receipts` + `receipt_items`.
-Tambah stok kemasan di `stock_movements` (qty positif).

### 2. Produksi (Production)

-User isi form produksi (tanggal, No PO, jenis produk, jumlah karton, kode batch).
-Sistem cek BOM produk → hitung total kebutuhan kemasan.
-Validasi stok kemasan cukup.
-Simpan batch ke `production_batches`.
-Catat ke `stock_movements`: -**Kemasan (−)** sesuai BOM × jumlah karton. -**Barang jadi (+)** per batch.

### 3. Pengiriman (Shipping)

-User isi form pengiriman (tanggal, tujuan, No SJ, batch, jumlah karton).
-Sistem cek sisa stok batch.
-Jika cukup, buat `shipments` + `shipment_items`.
-Catat ke `stock_movements`: -**Barang jadi (−)** per batch.

---

## 📑 Contoh Data

### Produk (`products`)

| product_code | name       |
| ------------ | ---------- |
| CCO-CTN50    | STP-CCO-50 |
| CCO-CTN24    | STP-CCO-24 |

### Kemasan (`packaging_items`)

| packaging_code | name               | base_uom |
| -------------- | ------------------ | -------- |
| STP-CCO-50     | Standing Pouch 50g | pcs      |
| CTN50          | Karton Box 50 pcs  | pcs      |
| CTN24          | Karton Box 24 pcs  | pcs      |

### BOM (`boms`)

| product_code | packaging_code | qty_per_unit | uom |
| ------------ | -------------- | ------------ | --- |
| CCO-CTN50    | STP-CCO-50     | 50           | pcs |
| CCO-CTN50    | CTN50          | 1            | pcs |
| CCO-CTN24    | STP-CCO-50     | 24           | pcs |
| CCO-CTN24    | CTN24          | 1            | pcs |

---

## 📈 Laporan & Query Penting

-**Stok Kemasan Saat Ini**

-sql
SELECT packaging_item_id, SUM(qty) AS stok_pcs
FROM stock_movements
WHERE item_type='packaging'
GROUP BY packaging_item_id;
