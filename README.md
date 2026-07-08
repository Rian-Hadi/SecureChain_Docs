# 🔒 SecureChain-Docs

Sistem manajemen dokumen berbasis blockchain dengan mekanisme **konsensus mayoritas 2/3** (Three-Database Byzantine Fault Tolerance) untuk menjaga integritas dokumen. Dibangun menggunakan **CodeIgniter 4** dengan arsitektur multi-database yang memungkinkan deteksi manipulasi data secara real-time dan pemulihan otomatis (auto-recovery).

## 📋 Daftar Isi

- [Fitur Utama](#-fitur-utama)
- [Arsitektur Sistem](#-arsitektur-sistem)
- [Teknologi yang Digunakan](#-teknologi-yang-digunakan)
- [Persyaratan Sistem](#-persyaratan-sistem)
- [Instalasi](#-instalasi)
- [Konfigurasi Database](#-konfigurasi-database)
- [Struktur Direktori](#-struktur-direktori)
- [CLI Commands](#-cli-commands)
- [API Endpoints](#-api-endpoints)
- [Keamanan](#-keamanan)
- [Testing](#-testing)
- [License](#-license)

## 🚀 Fitur Utama

### Blockchain Document Management
- Upload dokumen (PDF, DOCX, JPG, PNG) dengan hashing **SHA-256**
- Blockchain chain: setiap blok memiliki `block_hash` dan `previous_hash` yang saling terhubung
- Penyimpanan dokumen dalam format **Base64** pada blockchain
- Validasi file multi-layer: ekstensi, MIME type, magic bytes, dan ukuran (maks 5MB)

### Konsensus Mayoritas 2/3 (Three-Database BFT)
- **3 Node Database**: `userdb` (Node A), `admindb` (Node B), `konsensus` (Node C)
- Validasi Dua Lapis:
  - **Lapis 1**: Self-Integrity Check (Re-Hash Validation) — re-hash 7 field data dan bandingkan dengan stored hash
  - **Lapis 2**: Consensus 2/3 Majority Voting — voting mayoritas dari hash yang lolos Lapis 1
- Deteksi anomali: minority corrupt, no consensus (split brain), missing records
- Auto-recovery dari Source Node ke Target Node berdasarkan mayoritas

### Recovery System
- **Countdown Recovery Service** — state machine (idle → counting → recovering) sebelum batch recovery
- **Majority Recovery** — pemulihan data berdasarkan voting 2/3 database
- **Recovery Daemon** — background service untuk deteksi dan recovery otomatis
- **Manual Recovery** — admin dapat trigger recovery manual via dashboard
- **Rollback** — kemampuan rollback operasi recovery

### Notifikasi Telegram
- Real-time alerts untuk deteksi manipulasi dan recovery events
- Bot interaktif dengan perintah CLI
- Summary & monitoring via Telegram channel

### Admin Panel
- Dashboard monitoring kesehatan sistem
- Blockchain Explorer (read-only)
- Upload history dengan filter tanggal & kategori
- Manajemen user (CRUD + toggle status)
- Manajemen IP Whitelist
- Backup management
- Integrity check manual

### RESTful API
- Endpoint untuk blocks, chain validation, backups, whitelist, recovery, consensus, dan statistik
- Rate limiting (60 requests/menit per IP)

## 🏗️ Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                      SecureChain-Docs                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   ┌──────────┐    ┌──────────┐    ┌──────────────┐          │
│   │  Node A   │    │  Node B   │    │    Node C     │          │
│   │  userdb   │    │  admindb  │    │   konsensus   │          │
│   │ blockchain│    │blockchain │    │   konsensus   │          │
│   │           │    │  _backup  │    │              │          │
│   └─────┬─────┘    └─────┬─────┘    └──────┬───────┘          │
│         │                │                  │                │
│         └────────────────┼──────────────────┘                │
│                          │                                    │
│                  ┌───────▼────────┐                          │
│                  │  2/3 Majority  │                          │
│                  │   Consensus    │                          │
│                  │   Engine       │                          │
│                  └───────┬────────┘                          │
│                          │                                    │
│              ┌───────────┼───────────┐                       │
│              ▼           ▼           ▼                       │
│         Integrity    Auto-Recovery  Telegram                 │
│           Check      + Countdown     Alerts                  │
│              │           │           │                       │
│              └───────────┼───────────┘                       │
│                          ▼                                    │
│                   Admin Dashboard                            │
└─────────────────────────────────────────────────────────────┘
```

## 💻 Teknologi yang Digunakan

| Kategori | Teknologi |
|----------|-----------|
| **Framework** | CodeIgniter 4 (`^4.0`) |
| **Bahasa** | PHP `^8.1` |
| **Database** | MySQL / MariaDB (3 database terpisah) |
| **Hashing** | SHA-256 (block hash computation) |
| **Autentikasi** | JWT (HS256) + Session-based auth |
| **Testing** | PHPUnit `^10.5.16` |
| **Notifikasi** | Telegram Bot API |
| **Keamanan** | IP Whitelist, Rate Limiting, Security Headers, CSRF, CORS |
| **Frontend** | HTML, CSS, JavaScript (Server-side rendering) |

## 📦 Persyaratan Sistem

- PHP >= 8.1
- MySQL >= 5.7 / MariaDB >= 10.3
- Composer
- Extension PHP: `mysqli`, `json`, `mbstring`, `openssl`, `curl`

## ⚙️ Instalasi

```bash
# Clone repository
git clone https://github.com/Rian-Hadi/SecureChain_Docs.git
cd SecureChain_Docs

# Install dependency
composer install

# Copy environment file
cp env .env

# Edit .env sesuai konfigurasi database Anda
# (lihat bagian Konfigurasi Database)

# Jalankan development server
php spark serve
```

## 🗄️ Konfigurasi Database

Sistem ini menggunakan **3 database terpisah** yang perlu dibuat terlebih dahulu:

```sql
CREATE DATABASE poa_user_db;
CREATE DATABASE poa_admin_db;
CREATE DATABASE poa_konsensus_db;
```

Konfigurasi koneksi database dilakukan di `app/Config/Database.php`:

| Database Group | Database Name | Tabel Utama | Fungsi |
|----------------|---------------|-------------|--------|
| `userdb` | `poa_user_db` | `blockchain` | Database utama pengguna (Node A) |
| `admindb` | `poa_admin_db` | `blockchain_backup`, `users`, `ip_whitelist`, `activity_logs`, `upload_history`, `recovery_history`, `alerts` | Database backup & admin (Node B) |
| `konsensus` | `poa_konsensus_db` | `konsensus` | Database konsensus/ledger (Node C) |

### Menjalankan Migrasi

```bash
php spark migrate --all
```

### Environment Variables (opsional, konfigurasi via `.env`)

```
# Telegram
telegram.enabled = true
telegram.botToken = YOUR_BOT_TOKEN
telegram.channelId = YOUR_CHANNEL_ID

# JWT
JWT_SECRET_KEY = your-secret-key

# Recovery
recovery.auto_recovery_enabled = true
```

## 📁 Struktur Direktori

```
SecureChain-Docs/
├── app/
│   ├── Commands/              # CLI Commands
│   │   ├── AutoRecover.php           # Auto recovery berbasis konsensus
│   │   ├── ConsensusRecoveryCommand.php  # CLI consensus management
│   │   ├── FixBlockchainChain.php    # Perbaiki chain hash
│   │   ├── RecoveryDaemon.php        # Background recovery daemon
│   │   ├── TelegramDaemon.php        # Telegram bot interaktif
│   │   └── TelegramSummary.php       # Ringkasan via Telegram
│   ├── Config/                # Konfigurasi aplikasi
│   │   ├── Database.php              # 3-database configuration
│   │   ├── Filters.php               # Filter registration
│   │   ├── Recovery.php              # Recovery system config
│   │   ├── Routes.php                # Routing definitions
│   │   ├── Telegram.php              # Telegram bot config
│   │   └── Validation.php            # Validation rules
│   ├── Controllers/           # Controller
│   │   ├── Admin.php                 # Admin panel controller
│   │   ├── Api.php                   # RESTful API controller
│   │   ├── Auth.php                  # Authentication controller
│   │   ├── Document.php              # Document upload & management
│   │   └── Home.php                  # Landing page
│   ├── Database/
│   │   └── Migrations/               # Database migrations
│   ├── Filters/               # Custom filters
│   │   ├── RateLimitFilter.php       # Rate limiting (60 req/min)
│   │   └── SecurityHeadersFilter.php # Security headers (HSTS, CSP, etc.)
│   ├── Libraries/             # Core business logic
│   │   ├── BlockHash.php             # SHA-256 hash computation
│   │   ├── ConsensusMonitoring.php   # Monitoring & alerting system
│   │   ├── CountdownRecoveryService.php  # Recovery state machine
│   │   ├── IntegrityCheckService.php # 2-layer integrity validation
│   │   ├── JWTLibrary.php            # JWT token generation & verification
│   │   ├── MajorityRecovery.php      # 2/3 majority consensus engine
│   │   ├── RecoveryNotificationService.php  # Recovery alert orchestration
│   │   └── TelegramService.php       # Telegram API integration
│   ├── Models/                # Database models
│   │   ├── ActivityLogModel.php
│   │   ├── AlertModel.php
│   │   ├── BackupModel.php
│   │   ├── BlockModel.php
│   │   ├── RecoveryHistoryModel.php
│   │   ├── UploadHistoryModel.php
│   │   ├── UserModel.php
│   │   └── WhitelistModel.php
│   ├── Validation/
│   │   └── AppRules.php              # Custom validation rules
│   └── Views/                 # Template views
│       ├── admin/                    # Admin panel views
│       ├── auth/                     # Auth pages
│       ├── errors/                   # Error pages
│       ├── layouts/                  # Layout templates
│       ├── partials/                 # Partial templates (sidebar, dll)
│       └── user/                     # User panel views
├── mockup/                    # Mockup HTML desain
├── tests/
│   ├── integration/           # Integration tests
│   │   ├── AutoRecoverySynchronizationTest.php
│   │   ├── ByzantineAntiManipulationTest.php
│   │   ├── ConsensusIntegrityTest.php
│   │   ├── ConsensusValidationTest.php
│   │   ├── CountdownRecoveryStateMatnTest.php
│   │   └── FailureMatrixTest.php
│   └── unit/                  # Unit tests
│       ├── ConsensusRecoveryTest.php
│       ├── CountdownRecoveryServiceTest.php
│       ├── HealthTest.php
│       └── ManipulationDetectionTest.php
├── writable/                  # Writable directory (logs, cache, uploads)
├── composer.json
├── spark                      # CodeIgniter CLI entry point
└── LICENSE
```

## ⌨️ CLI Commands

```bash
# Consensus Management
php spark consensus:check          # Cek status konsensus
php spark consensus:recover        # Recovery dari mayoritas
php spark consensus:recover-purge  # Recovery + purge minoritas
php spark consensus:monitor        # Real-time monitoring
php spark consensus:health         # Laporan kesehatan sistem
php spark consensus:alerts         # Tampilkan alert aktif
php spark consensus:rollback       # Rollback operasi recovery

# Auto Recovery
php spark auto:recover             # Jalankan auto recovery

# Recovery Daemon
php spark recovery:daemon              # Foreground daemon
php spark recovery:daemon --interval=30 # Custom interval (detik)
php spark recovery:daemon --once        # Jalankan sekali lalu exit

# Blockchain Maintenance
php spark blockchain:fix-chain     # Perbaiki previous_hash chain

# Telegram
php spark telegram:daemon          # Jalankan Telegram bot interaktif
```

## 🔌 API Endpoints

### Blocks
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/blocks` | Ambil semua blok |
| GET | `/api/blocks/{id}` | Ambil blok berdasarkan ID |
| GET | `/api/blocks/hash/{hash}` | Ambil blok berdasarkan hash |

### Chain Validation
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/chain/validate` | Validasi integritas chain |

### Consensus
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/consensus/check` | Cek konsensus |
| POST | `/api/consensus/recover` | Recovery dari mayoritas |
| GET | `/api/consensus/health` | Health check konsensus |
| GET | `/api/consensus/dashboard` | Data dashboard monitoring |
| GET | `/api/consensus/alerts` | Alert aktif |
| POST | `/api/consensus/monitor` | Trigger monitoring |
| POST | `/api/consensus/rollback/{id}` | Rollback recovery |

### Integrity Check
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/integrity/check` | Hasil integrity check |
| POST | `/api/integrity/run` | Jalankan integrity check |

### Recovery
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/recovery/{id}` | Manual recovery blok |
| POST | `/api/check-integrity` | Cek integritas |
| POST | `/api/auto-recovery` | Trigger auto recovery |
| GET | `/api/recovery/status` | Status recovery |

### Backups & Whitelist
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/backups` | Daftar backup |
| GET | `/api/whitelist` | Daftar IP whitelist |
| POST | `/api/whitelist` | Tambah IP |
| PUT | `/api/whitelist/{id}/activate` | Aktifkan IP |
| PUT | `/api/whitelist/{id}/deactivate` | Nonaktifkan IP |
| DELETE | `/api/whitelist/{id}` | Hapus IP |

### Statistics & Logs
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/stats` | Statistik sistem |
| GET | `/api/activity-logs` | Log aktivitas |

## 🔐 Keamanan

Sistem ini mengimplementasikan multiple layer keamanan:

- **JWT Authentication** — Token-based auth dengan HS256, expiry 24 jam
- **Role-Based Access Control** — Dua role: `admin` dan `user`
- **IP Whitelist** — Akses admin panel dibatasi berdasarkan IP
- **Rate Limiting** — 60 requests per menit per IP (mencegah brute force/DDoS)
- **Security Headers** — X-Frame-Options DENY, X-Content-Type-Options nosniff, HSTS, CSP, Referrer-Policy
- **File Validation** — Whitelist ekstensi, MIME type, magic bytes verification, ukuran maks 5MB
- **CSRF Protection** — Built-in CodeIgniter 4 CSRF
- **Input Validation** — Server-side validation dengan custom rules
- **Activity Logging** — Semua aktivitas tercatat di `activity_logs`

## 🧪 Testing

```bash
# Jalankan semua test
php spark test

# Jalankan unit test saja
phpunit tests/unit/

# Jalankan integration test saja
phpunit tests/integration/
```

### Test Coverage

| Test Suite | Deskripsi |
|-----------|-----------|
| `ConsensusRecoveryTest` | Unit test mekanisme recovery konsensus |
| `CountdownRecoveryServiceTest` | Unit test state machine countdown |
| `ManipulationDetectionTest` | Unit test deteksi manipulasi data |
| `HealthTest` | Unit test health check system |
| `AutoRecoverySynchronizationTest` | Integration test sinkronisasi auto-recovery |
| `ByzantineAntiManipulationTest` | Integration test anti-manipulasi Byzantine |
| `ConsensusIntegrityTest` | Integration test integritas konsensus |
| `ConsensusValidationTest` | Integration test validasi konsensus |
| `CountdownRecoveryStateMatnTest` | Integration test state machine recovery |
| `FailureMatrixTest` | Integration test matriks kegagalan |

## 📄 License

MIT License — lihat file [LICENSE](LICENSE) untuk detail.

---

**SecureChain-Docs** — Sistem manajemen dokumen dengan integritas blockchain yang terjamin melalui konsensus mayoritas 2/3.
