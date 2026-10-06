# Panduan Ark Media: Menulis Artikel & Monetisasi

## 1. Menulis dan menerbitkan artikel

WordPress hanya jalan **lokal** sebagai editor. Yang tayang adalah hasil ekspor
statis ke Vercel.

```powershell
# 1. nyalakan situs lokal (butuh Docker Desktop)
.\scripts\up.ps1

# 2. buka editor
#    http://localhost:8080/wp-admin/   (kredensial di .env)

# 3. tulis artikelnya (lihat checklist di bawah), lalu Publish

# 4. export + QA + commit + push dalam satu perintah
.\scripts\publish.ps1 -Message "Tambah artikel Judul Artikel"
```

Vercel men-deploy otomatis setelah `git push`. Situs baru tayang 1-2 menit
kemudian di `https://lazy-media-five.vercel.app`.

Backup database (sekali seminggu atau sebelum perubahan besar):

```powershell
docker compose exec -T db mariadb-dump -u$DB_USER -p$DB_PASSWORD $DB_NAME > backup-$(Get-Date -Format yyyyMMdd).sql
```

## 2. Checklist tiap artikel

- [ ] **Excerpt wajib diisi** (panel "Excerpt" di bawah editor) — inilah meta
      description di Google; kalau kosong, Google ambil kalimat acak.
- [ ] Judul di bawah 60 karakter, ada kata kunci di depan.
- [ ] Kategori dipilih (bukan cuma "Uncategorized") — QA menolak artikel
      di luar kategori terdaftar.
- [ ] Featured image terpasang, JPG/PNG maksimal 2000px sisi terpanjang,
      kompres dulu sebelum upload.
- [ ] Alt text gambar diisi (deskripsi singkat berbahasa Indonesia).
- [ ] Minimal satu tautan internal ke artikel lain.
- [ ] Paragraf pendek (2-3 kalimat), subjudul `H2`/`H3` tiap 300-400 kata.
- [ ] Publish, lalu `.\scripts\publish.ps1 -Message "..."`.

## 3. Strategi konten

**Fokus utama: game gacha / gaming (semua umur).** Alasannya monetisasi:
konten dewasa/18+ **melanggar kebijakan Google AdSense** — situs akan ditolak
atau akun iklan dinonaktifkan. Kalau suatu saat mau membahas game 18+, buat
situs terpisah dengan jaringan iklan lain (bukan AdSense).

Ide rubrik yang sudah ada tempatnya di struktur kategori:

| Rubrik | Kategori | Contoh |
| --- | --- | --- |
| Update banner/gacha | Game News | "Banner baru X, worth it?" |
| Review | Reviews | Review game/gear |
| Build & tier list | Hardware / Tech Tips | Build PC, setting GPU |
| Event & komunitas | Esports / Entertainment | Turnamen, collab |

Frekuensi ideal untuk AdSense: **minimal 2-3 artikel/minggu**, 15+ artikel
mutu sebelum apply.

## 4. Monetisasi AdSense

### 4a. Ganti ID publisher (sekali saja)

Situs sudah punya slot skrip AdSense, masih terisi placeholder:

1. Buka `theme/lazymedia/inc/adsense.php`.
2. Ganti `ca-pub-0000000000000000` dengan ID asli Anda (`ca-pub-...`).
3. Jalankan `.\scripts\publish.ps1 -Message "Aktifkan AdSense"`.
   Skrip di `<head>` dan `dist/ads.txt` ikut terganti otomatis — cukup dari
   satu file itu, jangan edit di tempat lain.

### 4b. Domain sendiri (wajib sebelum apply)

Google AdSense **tidak menerima** subdomain gratis seperti `*.vercel.app`.
Harus domain yang Anda beli sendiri (mis. `arkmedia.id` / `arkmedia.com`).

1. Beli domain di registrar (Cloudflare, Namecheap, Idwebhost, Pregna, dll).
2. Di Vercel: project → **Settings → Domains** → tambahkan domain → ikuti
   instruksi DNS (A record atau CNAME) di panel registrar.
3. Setelah domain aktif, update `.env`:

   ```
   LM_PUBLIC_URL=https://domain-anda.com
   ```

4. Ekspor ulang supaya canonical/OG/sitemap pindah:

   ```powershell
   .\scripts\publish.ps1 -Message "Pindah ke domain baru"
   ```

### 4c. Apply ke AdSense

1. Buka <https://adsense.google.com> → **Add site** → masukkan URL domain.
2. Prasyarat yang sudah terpenuhi situs ini:
   - [x] Privacy Policy (`/privacy-policy/`) — memuat cookie, vendor pihak
         ketiga, dan link opt-out Google.
   - [x] About & Contact.
   - [x] `https://<domain>/ads.txt`.
   - [x] Navigasi rapi, tidak ada link rusak (dicek QA tiap export).
   - [x] Konten original minimal 15+ artikel.
3. Tunggu review (beberapa hari sampai 2-4 minggu).
4. **Setelah disetujui**: di dashboard AdSense aktifkan **Auto ads** (tanpa
   perlu ubah kode) — iklan otomatis tayang di semua halaman.
5. Muncul masalah "site not approved"? Cek email Google, perbaiki, apply
   ulang.

### 4d. Setelah tayang

- Pendapatan muncul di dashboard AdSense, bukan di situs.
- Jangan klik iklan sendiri (pelanggaran → banned).
- Pertahankan ritme terbit; traffic dari Google naik butuh 3-6 bulan konsisten.

## 5. Keamanan

- Password admin di `.env` hanya untuk lokal — jangan dipakai ulang di
  layanan lain.
- Situs produksi statis di Vercel: tidak ada PHP/database yang bisa diserang;
  header keamanan sudah diatur di `vercel.json`.
- Repo GitHub publik — `.env` dan `*.sql` di-`.gitignore` dan tidak boleh
  pernah masuk commit (dicek: aman).
