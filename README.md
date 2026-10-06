# Lazy Media

Situs media Indonesia berbasis WordPress, terinspirasi *The Lazy Monday*. Desain
merupakan homage visual: logo, teks, foto, dan artikel semuanya milik Lazy Media
sendiri.

WordPress hanya dipakai sebagai **editor**. Yang dipublikasikan adalah hasil
ekspor static di `dist/`, disajikan oleh Vercel tanpa build step.

## Stack

| Komponen | Versi |
| --- | --- |
| WordPress | 6.9.4 (`wordpress:6-php8.3-apache`) |
| Database | MariaDB 11 |
| Web server | Apache + PHP 8.3 (container) |
| Tema | `lazymedia` (tema klasik, tanpa plugin) |
| Tooling | WP-CLI di container `wpcli` |
| Hosting | Vercel, `outputDirectory: dist`, build kosong |

Host Windows **tidak** butuh PHP atau MySQL terpasang. Semua berjalan di Docker.

## Menjalankan

```powershell
# copy konfigurasi lokal sekali saja
Copy-Item .env.example .env

# start / stop
.\scripts\up.ps1
.\scripts\up.ps1 -Down

# start bersih (MENGHAPUS database)
.\scripts\up.ps1 -Fresh

# lihat log
.\scripts\up.ps1 -Logs
```

Docker Desktop harus sudah jalan sebelum `up.ps1`.

- Situs: <http://localhost:8080>
- Admin: <http://localhost:8080/wp-admin/>

Port dan kredensial diambil dari `.env`. `.env` tidak pernah di-commit;
`.env.example` adalah templatnya.

## Perintah WP-CLI

```powershell
.\scripts\wp.ps1 option get siteurl
.\scripts\wp.ps1 post list --post_type=post
```

Wrapper ini sudah menambahkan `--path=/var/www/html --allow-root`.

## Isi ulang konten demo

Seed bersifat idempotent, aman dijalankan berkali-kali. Urutannya penting.

```powershell
docker compose run --rm wpcli wp eval-file /seed/seed-structure.php --path=/var/www/html --allow-root
docker compose run --rm wpcli wp eval-file /seed/seed-posts.php    --path=/var/www/html --allow-root
docker compose run --rm wpcli wp eval-file /seed/cleanup.php       --path=/var/www/html --allow-root
docker compose run --rm wpcli wp eval-file /seed/seed-photos.php   --path=/var/www/html --allow-root
```

Verifikasi:

```powershell
docker compose run --rm wpcli wp eval-file /seed/verify.php --path=/var/www/html --allow-root
docker compose run --rm wpcli wp eval-file /seed/qa.php     --path=/var/www/html --allow-root
```

Lint PHP tema dan seed:

```powershell
docker compose run --rm --entrypoint sh wpcli /seed/lint.sh
```

### Isi tiap seed

| File | Fungsi |
| --- | --- |
| `seed/seed-structure.php` | 19 kategori, 6 tag, menu utama bertingkat, menu footer |
| `seed/seed-posts.php` | 21 artikel demo, featured image, 2 artikel video |
| `seed/cleanup.php` | hapus konten default, buat halaman About & Contact |
| `seed/seed-photos.php` | unduh 21 foto Openverse + metadata lisensi |
| `seed/optimize-photos.php` | perkecil original ke maksimal 2000px, quality 82 |
| `seed/export.php` | tulis static export ke `dist/` |
| `seed/qa.php` | regresi tema + export (21 pemeriksaan) |
| `seed/qa-dist.php` | cek link, asset, CSS, font, dan sitemap di `dist/` |
| `seed/verify.php` | laporkan jumlah post, thumbnail, menu, query blok |
| `seed/lint.sh` | `php -l` untuk seluruh file tema dan seed |

## Ekspor static

Satu perintah: wipe `dist/` dari host, ekspor, lalu jalankan dua suite QA.
Ekspor berhenti dengan exit code non-zero bila ada route atau asset yang gagal.

```powershell
.\scripts\export.ps1
```

Bisa juga langkah per langkah:

```powershell
docker compose run --rm wpcli wp eval-file /seed/export.php    --path=/var/www/html --allow-root
docker compose run --rm wpcli wp eval-file /seed/qa.php        --path=/var/www/html --allow-root
docker compose run --rm wpcli wp eval-file /seed/qa-dist.php   --path=/var/www/html --allow-root
```

`dist/` di-wipe dari **host**, bukan dari dalam container: folder itu bind mount
Windows, jadi recursive delete di container hanya melihat file dari run saat ini.

### Pratinjau build di browser

Server kecil yang meniru aturan Vercel (clean URLs, trailing slash, rewrite
`/search/<term>/`, dan `404.html` dengan status 404):

```powershell
node scripts\serve-dist.mjs 8099
# buka http://localhost:8099
```

## Deploy ke Vercel

`vercel.json` sudah disiapkan: `outputDirectory: "dist"`, `buildCommand` kosong,
`cleanUrls`, `trailingSlash`, rewrite search, dan header cache untuk theme dan
uploads. `dist/` **di-commit** karena Vercel menyajikannya apa adanya.

1. Set origin produksi di `.env` lalu ekspor ulang:

   ```powershell
   # LM_PUBLIC_URL=https://lazy-media-five.vercel.app
   .\scripts\export.ps1
   ```

   `LM_PUBLIC_URL` dipakai untuk canonical, Open Graph, JSON-LD, dan sitemap.
   Dikosongkan agar URL jadi root-relative.

   > Domain asli `lazy-media.vercel.app` sudah dipakai akun lain, jadi project
   > ini memperoleh alias `lazy-media-five.vercel.app`. Kalau alias berubah
   > (misal setelah ganti nama project), **wajib** ganti `LM_PUBLIC_URL` dan
   > ekspor ulang — kalau tidak, canonical/OG/sitemap menunjuk domain lama.

2. Login dan deploy (interaktif, sekali saja):

   ```powershell
   npx vercel login
   npx vercel link --yes
   npx vercel deploy --prod --yes
   ```

   Login tersimpan di `~/.vercel/auth.json`. Setelah itu deploy cukup
   `npx vercel deploy --prod --yes`.

3. Validasi setelah deploy:

   ```powershell
   foreach ($p in '/','/about/','/category/games/','/search/gpu/','/sitemap.xml') {
     "$p -> " + (Invoke-WebRequest -Uri "https://lazy-media-five.vercel.app$p" -UseBasicParsing).StatusCode
   }
   ```

   Yang harus terbaca: `200`, `200`, `200`, `200`, `200`. `/search/gpu/`
   lolos berkat rewrite di `vercel.json` — tanpa itu ia 404.

## Foto dan lisensi

Foto diambil dari **Openverse** (`seed/seed-photos.php`), bukan Unsplash.
Lisensi yang dipakai hanya CC0, Public Domain Mark, CC BY, dan CC BY-SA —
semua yang butuh atribusi kredit sudah menempel di halaman artikel melalui
`inc/photo-credit.php`, berupa nama kreator, nama lisensi, dan tautan sumber.

Attribution ditampilkan otomatis; jangan menghapus `lm-photo-credit`.

## Struktur proyek

```
public/                  # document root WordPress (di-ignore, inti WP dari image)
theme/lazymedia/         # tema — semua file ini di-commit
  functions.php          # setup, ukuran gambar, rewrite /search/, enqueue
  front-page.php         # komposisi homepage
  index.php single.php archive.php search.php searchform.php
  header.php footer.php page.php 404.php
  inc/design.php         # token desain, helper contact page, read time
  inc/query.php          # query blok
  inc/blocks.php         # registrasi + renderer blok homepage
  inc/cards.php          # markup kartu reusable
  inc/nav-walker.php     # dropdown menu navigasi
  inc/photo-credit.php   # kredit lisensi foto
  inc/assets.php         # favicon, cleanup head, matikan speculation rules
  template-parts/        # home/, archive/, cards/
  assets/css/            # fonts, components, home, single, archive, page
  assets/js/             # lazymedia.js, search.js
  assets/fonts/          # Barlow 400/500/600/700 (woff2 lokal)
  assets/img/            # logo, icon, og-default
seed/                    # seed, ekspor, QA (mount ke /seed)
scripts/                 # up, wp, export, serve-dist (PowerShell + Node)
dist/                    # hasil ekspor static — di-commit untuk Vercel
vercel.json              # konfigurasi hosting Vercel
```

`public/wp-content/themes/lazymedia` adalah mount dari `theme/lazymedia`, jadi
edit di mana pun, keduanya selalu sama.

## Blok homepage

Dirender dari `front-page.php` lewat query di `inc/query.php`:

1. **Trending** — 10 artikel
2. **Today's Video** — artikel terbaru bertanda video
3. **New Articles** — 6 terbaru, dengan filter tag
4. **Games** / **Hardware** / **Entertainment** — 3 artikel per kategori
5. **Latest Articles** — 8 terbaru

## Cari artikel

Static export tidak punya PHP, jadi pencarian berjalan di browser:
`assets/js/search.js` membaca `search-index.json` yang ditulis saat ekspor.
Rute `/search/<term>/` sama-sama 200 lewat rewrite di `vercel.json`.

Catatan rewrite: `destination` harus berupa direktori (`/search/`), **bukan**
`/search/index.html`. `cleanUrls: true` mengarahkan permintaan yang berisi
`.html` dengan status 308, sehingga rewrite yang menunjuk `.html` berujung 404.

## Catatan

- Password admin di `.env` hanya untuk pengembangan lokal. Ganti sebelum dipublikasikan.
- Backup: `docker compose exec db mysqldump -u<user> -p<password> <db> > backup.sql`
