**Nội dung chính:**
- **Quick Start**: 6 bước cài đặt (copy source, DB, GeoServer, Apache env, Google OAuth, chạy)
- **Yêu cầu hệ thống**: XAMPP, PostgreSQL+PostGIS, GeoServer, PHP extensions
- **Cấu trúc thư mục**: Chi tiết từng file/thư mục
- **Config folder**: Giải thích 4 file config + env vars override
- **Chức năng chính**: 6 tính năng chính
- **Database schema**: 2 bảng chính + indexes
- **Bảo mật**: 7 biện pháp bảo vệ
- **Tạo admin đầu tiên**: SQL command
- **Troubleshooting**: 5 lỗi thường gặp + khắc phục

Cũng đã tạo sẵn folder `config/` với 7 file:
- `config.php` - Main loader + helper `config()`
- `database.php` - PostgreSQL + column/table mapping
- `geoserver.php` - GeoServer WMS
- `google_oauth.php` - OAuth 2.0/OIDC client
- `app.php` - Session, CSRF, auth, map, pagination
- `config.example.php` - Template
- `README.md` - Config docs
---

## 📋 TỔNG HỢP CHI TIẾT CHỨC NĂNG WEBGIS ĐỊA CHÍNH ĐÔNG THẠNH

### 🗂️ CẤU TRÚC PROJECT
```
30_9_2026/
├── config/                    # ← MỚI: Folder config tập trung
│   ├── config.php             # Loader chính
│   ├── database.php           # PostgreSQL/PostGIS config
│   ├── geoserver.php          # GeoServer WMS config
│   ├── google_oauth.php       # Google OAuth 2.0/OIDC config
│   ├── app.php                # App settings (session, CSRF, auth, map)
│   ├── config.example.php     # Template config
│   └── README.md              # Hướng dẫn
├── index.php                  # Trang chính - Bản đồ + Tra cứu
├── ketnoi.php                 # DB connection + Auth helpers
├── xu_ly_click.php            # API xử lý click/tra cứu/CRUD
├── accounts.php               # Quản lý tài khoản (Admin)
├── account_api.php            # API tài khoản (CRUD, stats)
├── login.php                  # Đăng nhập (password + Google)
├── register.php               # Đăng ký tài khoản
├── logout.php                 # Đăng xuất
├── google_login.php           # Initiate Google OAuth
├── google_callback.php        # Google OAuth callback
├── google_oauth.php           # OAuth logic + User linking
├── google_oauth_native.php    # OAuth HTTP + JWT verification
├── google_oauth_config.local.php # Local OAuth credentials (gitignored)
├── database_setup.sql         # Schema DB chính
├── google_oauth_setup.sql     # Schema OAuth extensions
├── demo.css                   # Styles
├── .htaccess                  # Bảo vệ file config nhạy cảm
└── .gitignore                 # Ignore google_oauth_config.local.php
```

---

### ⚙️ CẤU HÌNH (CONFIG) - ĐÃ TẬP TRUNG VÀO `config/`

| File | Mục đích | Env vars override |
|------|----------|-------------------|
| `database.php` | PostgreSQL connection + table/column mapping | `WEBGIS_DB_HOST`, `PORT`, `NAME`, `USER`, `PASSWORD` |
| `geoserver.php` | GeoServer WMS endpoint + layer info | `WEBGIS_GEOSERVER_URL`, `WORKSPACE`, `LAYER` |
| `google_oauth.php` | OAuth 2.0/OIDC client config | `WEBGIS_GOOGLE_CLIENT_ID`, `CLIENT_SECRET`, `REDIRECT_URI` |
| `app.php` | App-wide: session, CSRF, auth, map defaults, pagination | `WEBGIS_APP_ENV`, `WEBGIS_APP_DEBUG` |

**Usage:**
```php
require_once __DIR__ . "/config/config.php";
$dbHost = config("database.host");
$geoserverUrl = config("geoserver.url");
```

---

### 🔐 HỆ THỐNG XÁC THỰC & PHÂN QUYỀN

| Tính năng | Chi tiết |
|-----------|----------|
| **Đăng nhập mật khẩu** | Username/email + bcrypt password_verify, CSRF protection, session regenerate |
| **Đăng ký tài khoản** | Validation: username 3-50, full_name ≤100, email valid, password ≥8, confirm match |
| **Đăng nhập Google (OAuth 2.0 + OIDC)** | PKCE (S256), state, nonce, RS256 JWT verification via Google JWKS, email_verified required |
| **Liên kết tài khoản Google** | Tự link nếu email Gmail verified hoặc Workspace domain match; từ chối nếu không verify được |
| **Tạo tài khoản từ Google** | Username auto-gen từ email + hash(google_id), random password, role=`user` |
| **Phân quyền** | `admin` (quản trị) / `user` (tra cứu) - set qua DB trực tiếp |
| **Session** | Secure, HttpOnly, SameSite=Lax, auto-regenerate on login |
| **CSRF** | Token 32-byte hex, verify qua `_csrf` POST field |

---

### 🗺️ BẢN ĐỒ & TRA CỨU (index.php + xu_ly_click.php)

#### 1. **Hiển thị bản đồ**
- Leaflet + GeoServer WMS layer `thua_dat_dong_thanh`
- Basemap control (OSM, Satellite, etc.)
- Locate control (GPS)
- Toggle WMS layer on/off

#### 2. **Tra cứu thửa đất (3 tab)**
| Tab | Bộ lọc | API action |
|-----|--------|------------|
| **Thửa đất** | Số tờ, Số thừa | `search_map` (click) / `search_thua` (form) |
| **Số GCN** | Số phát hành, Số vào sổ | `search_gcn` |
| **Chủ đất** | Tên chủ, CCCD, Địa chỉ, SĐT | `search_chu` |

#### 3. **Click vào bản đồ**
- GetFeatureInfo WMS → lấy `gid`/`thua_id`
- Query DB trả về GeoJSON + thuộc tính
- Highlight thửa đất (màu xanh) + lân cận (màu cam)
- Hiển thị chi tiết: chủ, CCCD, địa chỉ, SĐT, GCN, diện tích, loại đất

#### 4. **Kết quả tra cứu**
- List clickable → chọn highlight trên bản đồ
- Auto-fit bounds
- Clear all button

---

### ✏️ CRUD THỬA ĐẤT (Chỉ Admin)

| Action | API | Validation |
|--------|-----|------------|
| **Cập nhật** | `update_parcel` | gid required, so_to/so_thua ≥0, diện tích ≥0, các field text |
| **Xóa** | `delete_parcel` | gid required, confirm dialog |

- Đồng bộ cả cột legacy (`to_so`, `thua_so`, `loai_dat`, `thua_id`) và canonical (`so_to`, `so_thua`, `ma_loai_dat`, `gid`)
- Refresh WMS layer sau khi cập nhật (`_ts` cache buster)

---

### 👥 QUẢN LÝ TÀI KHOẢN (accounts.php + account_api.php) - Chỉ Admin

| Chức năng | Mô tả |
|-----------|-------|
| **Thống kê** | Tổng, Admin, User counts |
| **Tìm kiếm** | Username, full_name, email (debounced 250ms) |
| **Tạo tài khoản** | Username, full_name, email, password≥8, role (admin/user) |
| **Cập nhật** | Không cho tự hạ quyền admin của mình; phải giữ ≥1 admin |
| **Xóa** | Không cho xóa chính mình; không cho xóa admin cuối cùng |
| **Bảo mật** | Row-level lock (`FOR UPDATE`), transaction, duplicate check |

---

### 🗄️ CƠ SỞ DỮ LIỆU (PostgreSQL + PostGIS)

#### Bảng chính: `thua_dat_dong_thanh`
| Cột (canonical) | Legacy aliases | Kiểu | Mô tả |
|-----------------|----------------|------|-------|
| `gid` | `thua_id`, `_gid` | serial/PK | ID thửa đất |
| `so_to` | `to_so` | integer | Số tờ bản đồ |
| `so_thua` | `thua_so` | integer | Số thửa |
| `dien_tich` | - | numeric(12,2) | Diện tích (m²) |
| `ma_loai_dat` | `loai_dat` | varchar(50) | Mã loại đất |
| `ten_chu` | - | varchar(150) | Tên chủ sử dụng |
| `so_cccd` | - | varchar(20) | CCCD/CMND |
| `dia_chi_thuong_tru` | - | text | Địa chỉ thường trú |
| `so_dien_thoai` | - | varchar(15) | SĐT |
| `so_phat_hanh` | - | varchar(50) | Số phát hành GCN |
| `so_vao_so` | - | varchar(50) | Số vào sổ |
| `ngay_cap` | - | date | Ngày cấp GCN |
| `trang_thai_gcn` | - | varchar(50) | Trạng thái (default: 'Đã cấp GCN') |
| `geom` | - | geometry | Hình học (PostGIS) |

#### Bảng users
- `id`, `username`, `password_hash`, `full_name`, `email`, `google_id`, `role` (admin/user), `created_at`
- Unique index trên `LOWER(email)` và `google_id` (partial, ignore empty)

#### Bảng chuẩn hóa (optional)
- `giay_chung_nhan` - GCN riêng (liên kết `thua_id`)
- `ca_nhan` - Người/Chủ đất riêng
- `gcn_ca_nhan` - Mối quan hệ GCN - Người

#### Indexes
- GIST trên `geom`
- B-tree trên `(so_to, so_thua)`, `ten_chu`, `so_cccd`, `so_phat_hanh/so_vao_so`, `trang_thai_gcn`
- GIN trigram (`pg_trgm`) trên text fields cho fuzzy search

---

### 🔧 API ENDPOINTS (xu_ly_click.php)

| Action | Method | Auth | Params | Trả về |
|--------|--------|------|--------|--------|
| `click_map` | POST | User | `x`, `y` (map coords) | GeoJSON + attributes |
| `search_thua` | POST | User | `so_to`, `so_thua` | List parcels |
| `search_gcn` | POST | User | `so_phat_hanh`, `so_vao_so` | List parcels |
| `search_chu` | POST | User | `ten_chu`, `so_cccd`, `dia_chi`, `so_dien_thoai` | List parcels |
| `get_adjacent` | POST | User | `gid` | Adjacent parcels GeoJSON |
| `update_parcel` | POST | Admin | `gid` + fields | Success/error |
| `delete_parcel` | POST | Admin | `gid` | Success/error |

---

### 🌐 GOOGLE OAUTH FLOW

```
google_login.php
    → Generate state, nonce, PKCE code_verifier/challenge
    → Redirect to Google auth endpoint
    
google_callback.php
    → Verify state, nonce, PKCE
    → Exchange code for tokens (curl/stream)
    → Fetch JWKS, verify RS256 signature
    → Validate claims (iss, aud, azp, exp, iat, nonce, email_verified)
    → googleOAuthLoginUser()
        → Find/link/create user
        → Set session
    → Redirect index.php
```

---

### 🛡️ BẢO MẬT

| Biện pháp | Triển khai |
|-----------|------------|
| **SQL Injection** | `pg_query_params` everywhere, no string concat |
| **XSS** | `htmlspecialchars` all output, `ENT_QUOTES`, UTF-8 |
| **CSRF** | Token per session, verify on all POST mutating actions |
| **Session** | Secure cookies, HttpOnly, SameSite=Lax, regenerate on login |
| **Password** | `password_hash` (bcrypt default), min 8 chars |
| **OAuth** | PKCE, state, nonce, RS256 JWT verification, email_verified required |
| **Config protection** | `.htaccess` deny access to `google_oauth_config.local.php` |
| **Env vars** | Production credentials qua Apache `SetEnv`, không hardcode |

---

### 📦 DEPLOYMENT CHECKLIST

1. **PHP extensions**: `pgsql`, `openssl`, `curl` (bắt buộc)
2. **PostgreSQL + PostGIS** running, database `Baitap_21092026` tồn tại
3. **Chạy SQL**: `database_setup.sql` → `google_oauth_setup.sql`
4. **GeoServer** workspace `dong_thanh`, layer `thua_dat_dong_thanh` published WMS
5. **Apache env vars** (httpd-xampp.conf / vhost):
   ```apache
   SetEnv WEBGIS_DB_HOST localhost
   SetEnv WEBGIS_DB_PORT 5432
   SetEnv WEBGIS_DB_NAME Baitap_21092026
   SetEnv WEBGIS_DB_USER postgres
   SetEnv WEBGIS_DB_PASSWORD your_secure_password
   SetEnv WEBGIS_GEOSERVER_URL http://localhost:8181/geoserver
   SetEnv WEBGIS_GEOSERVER_WORKSPACE dong_thanh
   SetEnv WEBGIS_GEOSERVER_LAYER thua_dat_dong_thanh
   SetEnv WEBGIS_GOOGLE_CLIENT_ID "xxx.apps.googleusercontent.com"
   SetEnv WEBGIS_GOOGLE_CLIENT_SECRET "xxx"
   SetEnv WEBGIS_GOOGLE_REDIRECT_URI "https://yourdomain.com/google_callback.php"
   ```
6. **Google Cloud Console**: OAuth consent screen + Web App client, add redirect URI
7. **Restart Apache**

---

### 📝 FILES config (`config/`)

```
config/
├── config.php           # Main loader + helpers
├── database.php         # DB config + column/table mapping
├── geoserver.php        # GeoServer WMS config
├── google_oauth.php     # OAuth client config
├── app.php              # App settings (session, auth, map, pagination)
├── config.example.php   # Template for local config
└── README.md            # Documentation
```

**Cách dùng trong code cũ (ketnoi.php, index.php, v.v.):**
```php
// Thay vì getenv() rải rác, dùng:
require_once __DIR__ . "/config/config.php";
$host = config("database.host");
$geoserverUrl = config("geoserver.url");
$clientId = config("google_oauth.client_id");
```

Tất cả config hiện tại vẫn hoạt động vì `config/*.php` dùng `getenv() ?: default` - tương thích ngược hoàn toàn.
