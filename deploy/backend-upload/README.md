# Backend Upload Package

Struktur ini disiapkan agar bisa di-upload ke server backend (app node) lalu dijalankan sebagai release Laravel dan disinkronkan antar multi-VPS.

## Struktur

- `config/nginx/backend-app.conf` : Virtual host Nginx untuk Laravel (public/index.php)
- `config/php-fpm/www.conf` : Template pool PHP-FPM untuk beban ujian
- `config/supervisor/laravel-worker.conf` : Worker queue Laravel (opsional)
- `shared/.env.backend.example` : Template environment backend
- `shared/cluster.env.example` : Template variabel cluster (single source of truth)
- `scripts/01_prepare_server.sh` : Setup awal server backend
- `scripts/00_one_click_install.sh` : Installer 1 perintah (recommended)
- `scripts/02_deploy_release.sh` : Deploy release baru dari paket upload
- `scripts/03_post_deploy_checks.sh` : Validasi setelah deploy
- `scripts/04_apply_cluster_env.sh` : Generate `.env` node dari `cluster.env`
- `scripts/05_enable_node_vhost.sh` : Standarisasi vhost node (root ke `public`)
- `scripts/06_cluster_probe.sh` : Cek konsistensi output antar domain/node
- `config/nginx/lb-token-proxy.conf.example` : Template vhost LB utama (proxy penuh ke red1-red4)
- `releases/` : Tempat ekstrak paket source per rilis

## Alur pakai 1 kali install (Node App - Recommended)

1. Upload paket ini ke node, misalnya di `/opt/exam-deploy`.
2. Edit `shared/cluster.env.example`, simpan sebagai `shared/cluster.env`.
3. Siapkan source release dalam format `.tar.gz` atau `.zip`.
4. Jalankan satu perintah berikut:

```bash
cd /opt/exam-deploy/scripts
RELEASE_ARCHIVE=/opt/exam-deploy/releases/token-app.tar.gz \
./00_one_click_install.sh red3.sman1pontang.biz.id r20260407 token.sman1pontang.biz.id
```

Script ini otomatis menjalankan:
- prepare folder app
- ekstrak release
- deploy release
- generate `.env` node dari `cluster.env`
- standar vhost (root ke `public`, open_basedir aman)
- health check post-deploy

## Alur pakai manual (Node App)

1. Upload folder `deploy/backend-upload` ke server, misalnya ke `/opt/exam-deploy`.
2. Edit `shared/cluster.env.example` lalu simpan sebagai `shared/cluster.env`.
3. Jalankan `scripts/01_prepare_server.sh` sebagai root.
4. Upload source project (zip/tar) ke `releases/<release_name>/` lalu ekstrak.
5. Jalankan `scripts/02_deploy_release.sh <release_name>`.
6. Jalankan `scripts/04_apply_cluster_env.sh <node_domain> [main_domain]`.
7. Jalankan `scripts/05_enable_node_vhost.sh <node_domain> [main_domain]`.
8. Jalankan `scripts/03_post_deploy_checks.sh https://<node_domain>`.

Contoh:
```bash
./scripts/04_apply_cluster_env.sh red3.sman1pontang.biz.id token.sman1pontang.biz.id
./scripts/05_enable_node_vhost.sh red3.sman1pontang.biz.id token.sman1pontang.biz.id
./scripts/03_post_deploy_checks.sh https://red3.sman1pontang.biz.id
```

## Alur pakai singkat (LB Utama)

1. Pakai `config/nginx/lb-token-proxy.conf.example` sebagai template.
2. Isi IP `red1..red4` pada block `upstream`.
3. Reload Nginx.
4. Verifikasi output sama dengan `scripts/06_cluster_probe.sh` dari server admin.

## Catatan penting untuk multi-node

- `APP_KEY` harus sama di semua backend node.
- `SESSION_DRIVER=redis` dan `CACHE_STORE=redis` untuk shared state.
- Database dan Redis harus sama antar node.
- Pastikan endpoint health `/up` bisa diakses oleh load balancer.
- Jika extension Redis belum tersedia di node, sementara gunakan `SESSION_DRIVER=file` dan `CACHE_STORE=file` sampai extension siap.
