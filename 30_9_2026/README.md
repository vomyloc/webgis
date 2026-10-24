# WebGIS Dia Chinh Dong Thanh

He thong WebGIS quan ly dat dai va ho so dia chinh cho phuong Dong Thanh.

## Yeu cau he thong
- XAMPP (Apache + PHP 8.1+)
- PostgreSQL 14+ voi extension PostGIS 3+
- GeoServer 2.23+ (cho WMS layer)
- PHP extensions: pgsql, openssl, curl, mbstring

## Cai dat nhanh

### 1. Copy source code
Copy thu muc 30_9_2026 vao E:/xampp/htdocs/

### 2. Database
Tao database va chay schema:

CREATE DATABASE \
Baitap_21092026\;
\\c \
Baitap_21092026\
CREATE EXTENSION postgis;

Chay 2 file SQL:
- database_setup.sql
- google_oauth_setup.sql

### 3. GeoServer
- Tao workspace: dong_thanh
- Publish layer: thua_dat_dong_thanh tu PostGIS
- Enable WMS

### 4. Cau hinh Apache (httpd-xampp.conf)
SetEnv WEBGIS_DB_HOST localhost
SetEnv WEBGIS_DB_PORT 5432
SetEnv WEBGIS_DB_NAME Baitap_21092026
SetEnv WEBGIS_DB_USER postgres
SetEnv WEBGIS_DB_PASSWORD 1234

SetEnv WEBGIS_GEOSERVER_URL http://localhost:8181/geoserver
SetEnv WEBGIS_GEOSERVER_WORKSPACE dong_thanh
SetEnv WEBGIS_GEOSERVER_LAYER thua_dat_dong_thanh

SetEnv WEBGIS_GOOGLE_CLIENT_ID \
\
SetEnv WEBGIS_GOOGLE_CLIENT_SECRET \
\
SetEnv WEBGIS_GOOGLE_REDIRECT_URI \
http://localhost/30_9_2026_WebGIS_Pro/30_9_2026/google_callback.php\

Restart Apache.

### 5. Google OAuth (tu y chon)
Google Cloud Console -> OAuth 2.0 Client ID (Web application)
Redirect URI: http://localhost/30_9_2026_WebGIS_Pro/30_9_2026/google_callback.php
Cap nhat 3 env var tren, restart Apache.

### 6. Chay
http://localhost/30_9_2026_WebGIS_Pro/30_9_2026/

## Cau truc thu muc
30_9_2026/
config/
  config.php (Main loader)
  database.php (PostgreSQL/PostGIS)
  geoserver.php (GeoServer WMS)
  google_oauth.php (Google OAuth 2.0/OIDC)
  app.php (App settings)
  config.example.php (Template)
  README.md (Config docs)
index.php (Trang chinh: Ban do + Tra cuu)
ketnoi.php (DB connection + Auth)
xu_ly_click.php (API: click, search, CRUD)
accounts.php (UI Quan ly tai khoan Admin)
account_api.php (API tai khoan)
login.php (Dang nhap)
register.php (Dang ky)
logout.php (Dang xuat)
google_login.php (Initiate OAuth)
google_callback.php (OAuth callback)
google_oauth.php (OAuth logic)
google_oauth_native.php (HTTP + JWT verify)
database_setup.sql (Schema DB)
google_oauth_setup.sql (Schema OAuth)
demo.css (Styles)
.htaccess (Bao ve config)
.gitignore

## Config (folder config/)
Tat ca config dung getenv() ?: default, override bang env vars:
- WEBGIS_DB_* (host, port, name, user, password)
- WEBGIS_GEOSERVER_* (url, workspace, layer)
- WEBGIS_GOOGLE_* (client_id, client_secret, redirect_uri)
- WEBGIS_APP_* (env, debug)
Usage: require config/config.php; config(\
database.host\)

## Chuc nang chinh
1. Ban do Leaflet + GeoServer WMS
2. Tra cuu 3 tab: Thua dat / So GCN / Chu dat
3. Click ban do -> chi tiet + highlight + lan can
4. CRUD thua dat (Admin)
5. Quan ly tai khoan (Admin)
6. Dang nhap: password + Google OAuth 2.0 (PKCE, state, nonce, RS256 JWT verify)

## Database
Bang chinh: thua_dat_dong_thanh (gid, so_to, so_thua, dien_tich, ma_loai_dat, ten_chu, so_cccd, dia_chi_thuong_tru, so_dien_thoai, so_phat_hanh, so_vao_so, ngay_cap, trang_thai_gcn, geom)
Bang users: id, username, password_hash, full_name, email, google_id, role (admin/user)
Index: GIST geom, B-tree, GIN trigram (pg_trgm)

## Bao mat
- pg_query_params (no SQL injection)
- htmlspecialchars (XSS)
- CSRF token 32-byte
- Secure session cookies
- bcrypt password hash
- OAuth PKCE + RS256 JWT verify
- .htaccess deny sensitive files

## Tao admin dau tien
Dang ky user qua /register.php, roi chay SQL:
UPDATE users SET role = \
admin\ WHERE username = \your_username\;

## Troubleshooting
- PGSQL not loaded: bat extension=pgsql,openssl,curl trong php.ini
- DB connect fail: check env vars, PostgreSQL running
- WMS not load: check GeoServer workspace/layer, CORS
- OAuth fail: check redirect URI exact match in Google Console
- Session lost: local dev uses HTTP so secure=false auto

---
Internal use - WebGIS Dia Chinh Dong Thanh
