-- ==========================================================
-- SCRIPT KHỞI TẠO & NÂNG CẤP CSDL WEBGIS ĐỊA CHÍNH (POSTGIS)
-- Chạy bằng tài khoản có quyền tạo bảng/index và extension.
-- Bảng thua_dat_dong_thanh cần được nạp dữ liệu trước khi chạy.
-- ==========================================================

-- 1. Bảng quản lý tài khoản và phân quyền (admin / user)
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    google_id VARCHAR(255),
    role VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (role IN ('admin', 'user')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Nâng cấp bảng users đã tồn tại từ phiên bản cũ.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email VARCHAR(255),
    ADD COLUMN IF NOT EXISTS google_id VARCHAR(255);

-- Email và Google ID duy nhất (bỏ qua giá trị rỗng/null).
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_email_lower
    ON users (LOWER(email))
    WHERE email IS NOT NULL AND BTRIM(email) <> '';
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_google_id
    ON users (google_id)
    WHERE google_id IS NOT NULL AND BTRIM(google_id) <> '';

-- 2. Bổ sung các cột Chủ sử dụng đất & Giấy chứng nhận vào bảng thua_dat_dong_thanh
ALTER TABLE thua_dat_dong_thanh
    ADD COLUMN IF NOT EXISTS so_to INTEGER,
    ADD COLUMN IF NOT EXISTS so_thua INTEGER,
    ADD COLUMN IF NOT EXISTS dien_tich NUMERIC(12,2),
    ADD COLUMN IF NOT EXISTS ma_loai_dat VARCHAR(50),
    ADD COLUMN IF NOT EXISTS ten_chu VARCHAR(150),
    ADD COLUMN IF NOT EXISTS so_cccd VARCHAR(20),
    ADD COLUMN IF NOT EXISTS dia_chi_thuong_tru TEXT,
    ADD COLUMN IF NOT EXISTS so_dien_thoai VARCHAR(15),
    ADD COLUMN IF NOT EXISTS so_phat_hanh VARCHAR(50),
    ADD COLUMN IF NOT EXISTS so_vao_so VARCHAR(50),
    ADD COLUMN IF NOT EXISTS ngay_cap DATE,
    ADD COLUMN IF NOT EXISTS trang_thai_gcn VARCHAR(50) DEFAULT 'Đã cấp GCN';

-- 3. Index hỗ trợ truy vấn không gian và các bộ lọc thường dùng.
CREATE INDEX IF NOT EXISTS idx_thua_dat_geom
    ON thua_dat_dong_thanh USING GIST (geom);
CREATE INDEX IF NOT EXISTS idx_thua_dat_to_thua
    ON thua_dat_dong_thanh (so_to, so_thua);
CREATE INDEX IF NOT EXISTS idx_thua_dat_ten_chu
    ON thua_dat_dong_thanh (ten_chu);
CREATE INDEX IF NOT EXISTS idx_thua_dat_cccd
    ON thua_dat_dong_thanh (so_cccd);
CREATE INDEX IF NOT EXISTS idx_thua_dat_gcn
    ON thua_dat_dong_thanh (so_phat_hanh, so_vao_so);
CREATE INDEX IF NOT EXISTS idx_thua_dat_trang_thai_gcn
    ON thua_dat_dong_thanh (trang_thai_gcn);

-- ILIKE '%từ khóa%' không tận dụng tốt B-tree; trigram hỗ trợ tìm gần đúng trên dữ liệu lớn.
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX IF NOT EXISTS idx_thua_dat_ten_chu_trgm
    ON thua_dat_dong_thanh USING GIN (ten_chu gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_thua_dat_dia_chi_trgm
    ON thua_dat_dong_thanh USING GIN (dia_chi_thuong_tru gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_thua_dat_sdt_trgm
    ON thua_dat_dong_thanh USING GIN (so_dien_thoai gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_thua_dat_loai_dat_trgm
    ON thua_dat_dong_thanh USING GIN (ma_loai_dat gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_thua_dat_so_phat_hanh_trgm
    ON thua_dat_dong_thanh USING GIN (so_phat_hanh gin_trgm_ops);
CREATE INDEX IF NOT EXISTS idx_thua_dat_so_vao_so_trgm
    ON thua_dat_dong_thanh USING GIN (so_vao_so gin_trgm_ops);
