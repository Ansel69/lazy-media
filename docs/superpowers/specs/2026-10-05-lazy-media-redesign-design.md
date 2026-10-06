# Lazy Media — Redesign & Static Deploy

- **Tanggal:** 2026-10-05
- **Status:** Disetujui
- **Referensi visual:** `https://thelazy.media/` (Elementor + Hello theme)

---

## 1. Tujuan

Redesign situs Lazy Media agar tampilannya sedekat mungkin dengan The Lazy
Monday, lalu export sebagai situs statis dan deploy ke Vercel lewat GitHub.

**Batas yang disepakati:** layout, ritme, kepadatan, dan design token diopsi.
Nama, logo, foto, dan teks tetap milik Lazy Media. Tidak menyalin aset milik
The Lazy Monday.

## 2. Kenapa static export

Stack sekarang (Apache + PHP 8.3 + MariaDB + volume persisten) tidak bisa
berjalan di Vercel: Vercel serverless, tanpa MySQL, tanpa persistent disk.
Jalur yang disepakati: WordPress tetap lokal untuk menulis konten, di-export
jadi HTML statis, GitHub menyimpan hasilnya, Vercel menyajikannya sebagai CDN.

```
public/  ──wp2static──►  dist/  ──git──►  GitHub  ──auto-deploy──►  Vercel
```

**Konsekuensi yang diterima:** setelah deploy tidak ada `/wp-admin/`. Konten
diedit lokal, lalu jalankan `.\scripts\export.ps1`, lalu push. Search berjalan
client-side di atas JSON index.

## 3. Design token

Diambil dari inspeksi CSS langsung milik The Lazy Monday.

| Token | Nilai |
| --- | --- |
| `--lm-bg` | `#0F0F0F` |
| `--lm-bg-elevated` | `#151515` |
| `--lm-accent` | `#FBAD18` |
| `--lm-accent-soft` | `rgba(251, 173, 24, 0.12)` |
| `--lm-text` | `#FFFFFF` |
| `--lm-text-muted` | `#9A9A9A` |
| `--lm-faint` | `#54595F` |
| `--lm-border` | `rgba(255, 255, 255, 0.10)` |
| Font | Barlow — 600 nav/heading, 500 accent, 400 body |
| Container | `1230px` |
| Radius | `8px` seragam |
| Card shadow | `3px 3px 10px #000000` |
| Widget gap | `20px` |
| Grid gap | `10px` × `16–18px` |
| Breakpoint | `1024px` → 2 kolom, `767px` → 1–2 kolom |

### Aksen per seksi

Tanda tangan visual The Lazy Monday adalah divider **40px × 4px** dengan warna
neon berbeda untuk tiap seksi. Pola ini ditiru:

| Seksi | Warna |
| --- | --- |
| Hero | `#FF7E36` |
| New Articles | `#FF7E36` |
| Games | `#C203C6` |
| Hardware | `#36FFF3` |
| Entertainment | `#BFFA17` |
| Latest Articles | `#FF36EB` |

## 4. Cakupan halaman

| Halaman | Status | Perubahan |
| --- | --- | --- |
| Homepage | redesign penuh | Hero slider, sub-nav + filter, grid 3×3 kategori, latest |
| Single post | tulis ulang | Hero image, meta row, body 800px, related 3 kolom, breadcrumb |
| Archive (kategori/tag) | tulis ulang | Judul + deskripsi, featured post, grid sisa |
| Search | tulis ulang | Input sesuai design system, hasil sebagai kartu |
| Page (About/Contact) | tulis ulang | Dua kolom |
| 404 | tulis ulang | |
| Header | redesign | Nav underline accent, dropdown, search transparan |
| Footer | redesign | Social row, nav footer, credit |

## 5. Aset gambar

- 21 foto artikel dari Unsplash (gratis, tanpa API key), dipetakan per topik:
  game → controller/console, hardware → GPU/laptop/PCB, entertainment →
  komik/film, hobby → kolektor.
- Semua foto diproses lokal: 1200×675 (16:9), thumbnail 300×200, dan srcset.
- 1 logo Lazy Media dalam SVG buatan sendiri.
- 1 favicon SVG.
- Barlow (400/500/600/700) sebagai file `.woff2` lokal — tidak ada request ke
  Google Fonts saat runtime, repo tetap self-contained.

## 6. Struktur repo setelah perubahan

```
E:\PROYEK\Projects\lazy-media\
  theme/lazymedia/       # sumber tema yang di-version-control
  dist/                  # output statis yang di-deploy
  seed/                  # skrip seed + verifikasi
  scripts/               # up.ps1, wp.ps1, export.ps1
  public/                # WordPress core + .gitignore (TIDAK ikut ke GitHub)
  .gitignore
  .env
  vercel.json
  README.md
```

Yang **tidak** masuk GitHub: `public/` (WordPress core 3.495 file, 75 MB),
`wp-config.php`, `.env`, `public/wp-content/uploads/` asli, plugin.

## 7. Risiko & mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Export statis menghasilkan 404 di URL pretty | `dist/<path>/index.html`; Vercel sudah melayani ini tanpa rewrite |
| Gambar membengkakkan repo | Kompres ke WebP/JPEG kualitas 82, maksimal 3 ukuran per foto |
| `.env` atau password bocor ke GitHub | `.gitignore` tegas + `git log --all -p` dicek sebelum push pertama |
| Search tidak jalan di situs statis | JSON index dibangun saat export, dicari client-side dengan JS kecil |
| Instalasi `gh`/`vercel` gagal | Ada fallback: `git remote` + Vercel dashboard import repo secara manual |

## 8. Yang butuh tindakan manusia

Dua langkah tidak bisa diselesaikan tanpa interaksi:

1. Login GitHub (`gh auth login`) dan Vercel (`vercel login`) — keduanya
   berbasis OAuth di browser.
2. Keputusan nama repo dan visibility (publik/privat).

Semua pekerjaan lain dikerjakan penuh tanpa hambatan.:hambatan.

## 9. Kriteria selesai

- [ ] Semua halaman HTTP 200 lokal, tanpa PHP notice/warning
- [ ] `debug.log` kosong
- [ ] `php -l` bersih untuk seluruh file PHP
- [ ] Export menghasilkan `dist/` dengan semua URL yang diperlukan
- [ ] Tidak ada kredensial di riwayat git
- [ ] Situs live di domain Vercel dan bisa diakses publik