# SecureChain-Docs

Sistem manajemen dokumen berbasis blockchain dengan mekanisme **konsensus mayoritas 2/3** untuk menjaga integritas dokumen. Dibangun menggunakan **CodeIgniter 4** dengan arsitektur multi-database yang memungkinkan deteksi manipulasi data secara real-time dan pemulihan otomatis.

## Fitur Utama

### Blockchain Document Management
- Upload dokumen (PDF, DOCX, JPG, PNG) dengan hashing SHA-256
- Blockchain chain: setiap blok memiliki `block_hash` dan `previous_hash`
- Validasi file multi-layer: ekstensi, MIME type, magic bytes, ukuran maks 5MB

### Konsensus Mayoritas 2/3
- 3 Node Database: `userdb`, `admindb`, `konsensus`
- Validasi Dua Lapis:
  - **Lapis 1**: Re-Hash Validation — re-hash 7 field data dan bandingkan dengan stored hash
  - **Lapis 2**: Consensus 2/3 Majority Voting — voting mayoritas dari hash yang lolos Lapis 1
- Deteksi anomali: minority corrupt, split brain, missing records
- Auto-recovery dari Source Node ke Target Node berdasarkan mayoritas

### Recovery System
- Countdown Recovery Service — state machine (idle → counting → recovering)
- Recovery Daemon — background service untuk deteksi dan recovery otomatis
- Manual Recovery & Rollback via admin panel

### Notifikasi Telegram
- Real-time alerts untuk deteksi manipulasi dan recovery events
- Bot interaktif dengan perintah CLI

### Admin Panel
- Dashboard monitoring kesehatan sistem
- Blockchain Explorer (read-only)
- Upload history, manajemen user, IP Whitelist, backup management
- Integrity check manual

### RESTful API
- Endpoint untuk blocks, chain validation, consensus, recovery, dan statistik
- Rate limiting (60 requests/menit per IP)

## Arsitektur Sistem

```
┌──────────────────────────────────────────────────┐
│               SecureChain-Docs                    │
│                                                   │
│   ┌─────────┐  ┌─────────┐  ┌───────────┐       │
│   │  Node A  │  │  Node B  │  │   Node C   │       │
│   │  userdb  │  │  admindb │  │  konsensus │       │
│   └────┬─────┘  └────┬─────┘  └─────┬──────┘       │
│        └──────────────┼──────────────┘              │
│                       ▼                             │
│              2/3 Majority Consensus                 │
│                       │                             │
│           ┌───────────┼───────────┐                 │
│           ▼           ▼           ▼                 │
│      Integrity    Auto-Recovery  Telegram           │
│        Check      + Countdown     Alerts            │
└──────────────────────────────────────────────────┘
```

## Teknologi

| Kategori | Teknologi |
|----------|-----------|
| Framework | CodeIgniter 4 |
| Bahasa | PHP 8.1+ |
| Database | MySQL / MariaDB (3 database terpisah) |
| Hashing | SHA-256 |
| Autentikasi | JWT (HS256) + Session |
| Testing | PHPUnit |
| Notifikasi | Telegram Bot API |
| Keamanan | IP Whitelist, Rate Limiting, Security Headers, CSRF |

## Persyaratan

- PHP >= 8.1
- MySQL >= 5.7 / MariaDB >= 10.3
- Composer
- Extension: `mysqli`, `json`, `mbstring`, `openssl`, `curl`

## Instalasi

```bash
git clone https://github.com/Rian-Hadi/SecureChain_Docs.git
cd SecureChain_Docs
composer install
php spark serve
```

Buat 3 database MySQL sebelum menjalankan:

```sql
CREATE DATABASE poa_user_db;
CREATE DATABASE poa_admin_db;
CREATE DATABASE poa_konsensus_db;
php spark migrate --all
```

## CLI Commands

```bash
php spark consensus:check          # Cek status konsensus
php spark consensus:recover        # Recovery dari mayoritas
php spark consensus:monitor        # Real-time monitoring
php spark consensus:health         # Laporan kesehatan sistem
php spark auto:recover             # Jalankan auto recovery
php spark recovery:daemon          # Background recovery daemon
php spark blockchain:fix-chain     # Perbaiki previous_hash chain
php spark telegram:daemon          # Telegram bot interaktif
```

## Struktur Direktori

```
SecureChain-Docs/
├── app/
│   ├── Commands/          # CLI Commands
│   ├── Config/            # Konfigurasi aplikasi
│   ├── Controllers/       # Admin, Api, Auth, Document
│   ├── Filters/           # Rate limiting, Security headers, IP Whitelist
│   ├── Libraries/         # Core: BlockHash, MajorityRecovery,
│   │                      #   IntegrityCheck, ConsensusMonitoring,
│   │                      #   CountdownRecovery, TelegramService, JWT
│   ├── Models/            # Block, Backup, User, Whitelist, ActivityLog
│   └── Views/             # Admin & user panel views
├── mockup/                # Mockup HTML desain
├── tests/
│   ├── integration/       # 6 integration tests
│   └── unit/              # 4 unit tests
└── writable/              # Logs, cache, uploads
```

## Keamanan

- JWT Authentication dengan role-based access (admin & user)
- IP Whitelist untuk akses admin panel
- Rate limiting 60 requests/menit per IP
- Security headers: X-Frame-Options, HSTS, CSP, X-Content-Type-Options
- File validation: ekstensi, MIME type, magic bytes
- Activity logging untuk audit trail

## Testing

```bash
php spark test
```

| Test | Deskripsi |
|------|-----------|
| ConsensusRecoveryTest | Mekanisme recovery konsensus |
| CountdownRecoveryServiceTest | State machine countdown |
| ManipulationDetectionTest | Deteksi manipulasi data |
| AutoRecoverySynchronizationTest | Sinkronisasi auto-recovery |
| ByzantineAntiManipulationTest | Anti-manipulasi Byzantine |
| ConsensusIntegrityTest | Integritas konsensus |
| FailureMatrixTest | Matriks kegagalan |

## License

MIT License
