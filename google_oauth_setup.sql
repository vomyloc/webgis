-- Bổ sung cột phục vụ liên kết tài khoản Google. Chạy trong database Baitap_21092026.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email VARCHAR(255),
    ADD COLUMN IF NOT EXISTS google_id VARCHAR(255);

-- Nếu kết quả kiểm tra dưới đây có dòng nào, xử lý email trùng trước khi tạo index.
-- SELECT LOWER(email), COUNT(*) FROM users
-- WHERE email IS NOT NULL AND BTRIM(email) <> ''
-- GROUP BY LOWER(email) HAVING COUNT(*) > 1;
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_email_lower
    ON users (LOWER(email))
    WHERE email IS NOT NULL AND BTRIM(email) <> '';
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_google_id
    ON users (google_id)
    WHERE google_id IS NOT NULL AND BTRIM(google_id) <> '';
