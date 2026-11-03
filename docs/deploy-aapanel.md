# Triển khai RMA trên aaPanel (thư mục con `/rma`)

App chạy ở **https://app.vnsytek.com/rma**, chung một site nginx với Petty Cash (`/petty_cash`).
Mã nguồn nằm ở `/www/wwwroot/app.vnsytek.com/rma`, PHP 8.5, MySQL.

Tài liệu này ghi lại cấu hình đúng, để khi dựng lại server hoặc chuyển host không gặp lại 2 lỗi đã từng xảy ra (11/2026):

- **Chuyển hướng vô hạn** (`ERR_TOO_MANY_REDIRECTS`) với người đã đăng nhập hoặc có tick "Ghi nhớ đăng nhập".
- **Lộ mã nguồn và database** ra Internet (tải được `routes/web.php`, `composer.json`, `database/database.sqlite`…).

---

## 1. Cập nhật code (mỗi lần deploy)

```bash
cd /www/wwwroot/app.vnsytek.com/rma
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force        # nên backup DB trước khi chạy
npm ci && npm run build
php artisan optimize:clear
```

Không sửa code trực tiếp trên server. Sửa trong repo, push lên, rồi `git pull`, nếu không lần pull sau sẽ ghi đè mất phần đã sửa.

## 2. Các dòng `.env` bắt buộc khi chạy dưới `/rma`

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.vnsytek.com/rma

SESSION_PATH=/rma
SESSION_SECURE_COOKIE=true
```

- `APP_URL` phải có `/rma`, nếu thiếu thì link trong email, file xuất và lệnh artisan sẽ trỏ sai.
- `SESSION_PATH=/rma` giúp cookie đăng nhập của RMA và Petty Cash không đè lên nhau.
- `APP_KEY` của RMA và Petty Cash **phải khác nhau**. Không copy nguyên file `.env` từ app này sang app kia.

## 3. Cấu hình nginx

aaPanel → **Website** → `app.vnsytek.com` → **Conf**. Phần dành cho RMA trong khối `server { … }`:

```nginx
    # http -> https (đặt ở đầu khối server)
    if ($scheme = http) {
        return 301 https://$host$request_uri;
    }

    # ===== Laravel RMA: https://app.vnsytek.com/rma/ =====

    location = /rma {
        return 301 /rma/;
    }

    # File tĩnh chỉ lấy từ thư mục public
    location ^~ /rma/build/ {
        alias /www/wwwroot/app.vnsytek.com/rma/public/build/;
        access_log off;
        expires 30d;
    }

    location ^~ /rma/images/ {
        alias /www/wwwroot/app.vnsytek.com/rma/public/images/;
        access_log off;
        expires 7d;
    }

    location ^~ /rma/storage/ {
        alias /www/wwwroot/app.vnsytek.com/rma/storage/app/public/;
        access_log off;
        expires 30d;
    }

    # Mọi request còn lại đi vào Laravel
    location ^~ /rma/ {
        rewrite ^ /rma/index.php last;
    }

    location = /rma/index.php {
        include fastcgi_params;

        fastcgi_pass unix:/tmp/php-cgi-85.sock;
        fastcgi_index index.php;

        fastcgi_param SCRIPT_FILENAME /www/wwwroot/app.vnsytek.com/rma/public/index.php;
        fastcgi_param DOCUMENT_ROOT /www/wwwroot/app.vnsytek.com/rma/public;

        fastcgi_param REQUEST_URI $request_uri;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_param REQUEST_METHOD $request_method;
        fastcgi_param CONTENT_TYPE $content_type;
        fastcgi_param CONTENT_LENGTH $content_length;
    }

    # Chặn file ẩn (.env, .git…)
    location ~ /\.(?!well-known).* {
        deny all;
    }
```

Bấm **Save**. aaPanel tự kiểm tra cú pháp và nạp lại nginx, không cần chạy lệnh gì thêm.

### Tuyệt đối không thêm lại

| Đừng viết | Vì sao |
|---|---|
| `location = /rma/ { return 302 /rma/login; }` | Người đã đăng nhập bị đá qua lại `/rma/` ↔ `/rma/login` → chuyển hướng vô hạn. Laravel tự biết đưa ai về đâu. |
| `location ^~ /rma/ { try_files $uri $uri/ @rma; }` với `root /www/wwwroot/app.vnsytek.com` | nginx phục vụ thẳng mọi file trong thư mục dự án → ai cũng tải được mã nguồn, `composer.json`, file database. |
| Hai khối `location ^~ /rma/` cùng lúc | aaPanel báo `duplicate location` và không lưu. |

Petty Cash dùng đúng mẫu trên, chỉ thay `rma` bằng `petty_cash`.

## 4. Kiểm tra sau khi deploy

| Mở | Kết quả đúng |
|---|---|
| `https://app.vnsytek.com/rma` (chưa đăng nhập) | Về `/rma/login` |
| `https://app.vnsytek.com/rma` (đã đăng nhập, có tick "Ghi nhớ") | Vào thẳng `/rma/tickets` |
| `https://app.vnsytek.com/rma/login` (đã đăng nhập) | Vào thẳng `/rma/tickets` |
| `http://app.vnsytek.com/rma` | Tự chuyển sang `https://` |
| `https://app.vnsytek.com/rma/composer.json`, `/rma/routes/web.php`, `/rma/.env` | **404 hoặc 403**, không được tải về |
| CSS, JS, logo | Hiển thị bình thường |

Trong code, mọi chuyển hướng đều đi theo tên route (`route('tickets.index')`), không viết cứng `/tickets`, để luôn giữ phần `/rma`.
Test `tests/Feature/SubfolderDeploymentTest.php` giả lập đúng môi trường `/rma` và sẽ báo đỏ nếu có ai viết lại kiểu cũ.
