# Cài đặt WebGIS Địa chính Đông Thạnh

## Chạy ứng dụng

1. Sao lưu ứng dụng và database hiện tại, sau đó chép thư mục `30_9_2026` vào thư mục web.
2. Đảm bảo PHP có extension `pgsql`, `openssl` và `curl`; bật PostgreSQL/PostGIS.
3. Đăng nhập Google dùng PHP cURL (hoặc `allow_url_fopen`) và OpenSSL; không cần Composer hay thư viện ngoài. Trong XAMPP, đảm bảo extension `openssl` và `curl` được bật trong `php.ini`, rồi khởi động lại Apache.
4. Chạy `google_oauth_setup.sql` một lần trong database `Baitap_21092026`, trừ khi đã chạy `database_setup.sql` sau khi bổ sung cột `email` và `google_id`.
5. Bảng `thua_dat_dong_thanh` phải có `geom`. Ứng dụng tự nhận tên cột gốc `to_so`, `thua_so`, `loai_dat`, `thua_id` hoặc tên chuẩn `so_to`, `so_thua`, `ma_loai_dat`, `gid`.
6. Mặc định kết nối PostgreSQL là local cũ. Có thể cấu hình `WEBGIS_DB_HOST`, `WEBGIS_DB_PORT`, `WEBGIS_DB_NAME`, `WEBGIS_DB_USER`, `WEBGIS_DB_PASSWORD` bằng môi trường máy chủ.

## Bật đăng nhập Google thật

1. Trong Google Cloud Console, cấu hình màn hình đồng ý OAuth và tạo OAuth client ID loại **Web application**.
2. Thêm URI callback chính xác vào **Authorized redirect URIs**. Với đường dẫn localhost trong ảnh, dùng `http://localhost/30_9_2026_WebGIS_Pro/30_9_2026/google_callback.php`. Nếu dùng cổng, host hoặc thư mục khác, hãy dùng URL thật của `google_callback.php`. Dùng HTTPS khi triển khai công khai.
3. Nếu màn hình đồng ý đang ở chế độ kiểm thử, thêm tài khoản Google cần đăng nhập vào danh sách **Test users**.
4. Khai báo các biến môi trường trong cấu hình Apache (`httpd-xampp.conf` hoặc cấu hình virtual host), không ghi client secret vào mã nguồn:

```apache
SetEnv WEBGIS_GOOGLE_CLIENT_ID "CLIENT_ID.apps.googleusercontent.com"
SetEnv WEBGIS_GOOGLE_CLIENT_SECRET "CLIENT_SECRET"
SetEnv WEBGIS_GOOGLE_REDIRECT_URI "http://localhost/30_9_2026_WebGIS_Pro/30_9_2026/google_callback.php"
```

Khởi động lại Apache. Khi chạy trên máy chủ thật, dùng URI HTTPS và đăng ký chính xác URI đó trong Google Cloud Console.

Luồng đăng nhập sử dụng OAuth 2.0/OpenID Connect, `state`, `nonce`, PKCE và OpenSSL để kiểm tra chữ ký RS256 bằng JWKS chính thức của Google, cùng các claim ID token. Ứng dụng chỉ yêu cầu thông tin định danh cơ bản; không lưu access token. Google account mới tự tạo WebGIS user role `user`. Tài khoản cũ được liên kết theo Google ID; chỉ tự liên kết email cũ với Gmail đã xác minh hoặc email Workspace có claim miền phù hợp. Nếu email không đủ cơ sở xác minh, đăng nhập Google sẽ không tự chiếm/liên kết tài khoản đó. Không có luồng Google giả lập nữa.

## Các phần đã sửa

- Đồng bộ tên cột địa chính thực tế để tra cứu, bấm bản đồ, xem chi tiết, cập nhật và xóa làm việc với dữ liệu hiện có.
- Sửa workspace WMS thành `dong_thanh`, tâm bản đồ về khu vực dữ liệu Đông Thạnh.
- Thay hộp thoại Google mô phỏng bằng luồng OAuth thật gồm chuyển hướng, callback, PKCE, state, nonce, xác minh ID token và tạo/liên kết tài khoản.
- Tài khoản mới qua đăng ký thường hoặc Google luôn nhận role `user`. Cán bộ quản trị cấp role admin trực tiếp qua database:

```sql
UPDATE users SET role = 'admin' WHERE username = 'TEN_DANG_NHAP_CAN_BO';
```

## Giới hạn triển khai

Mã nguồn không chứa Google Client ID/Secret. Đăng nhập Google chỉ hoạt động sau khi bật extension cần thiết, khai báo đủ ba biến môi trường và đăng ký callback URI trong Google Cloud Console. Cần xác nhận tích hợp trên máy có PostgreSQL và GeoServer thật.
