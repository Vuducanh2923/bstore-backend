# BStore Backend Microservices

BStore Backend là hệ thống backend Laravel được tách theo kiến trúc microservices. Repository này có sẵn Dockerfile cho từng service và file Docker Compose ở root để người khác có thể clone về chạy nhanh bằng các image đã public trên Docker Hub.

## Chạy Nhanh Bằng Docker

Yêu cầu:

- Docker Desktop hoặc Docker Engine
- Docker Compose v2

Clone repository và chạy toàn bộ hệ thống:

```powershell
git clone <repository-url>
cd bstore-backend
docker compose up -d
```

Sau khi chạy xong, truy cập:

- Frontend: `http://localhost:5173`
- API Gateway: `http://localhost:8000/api`
- Auth Service: `http://localhost:8001`
- Catalog Service: `http://localhost:8002`
- Order Service: `http://localhost:8003`
- Payment Service: `http://localhost:8004`
- MySQL: `localhost:3308`

Xem log container:

```powershell
docker compose logs -f
```

Dừng hệ thống:

```powershell
docker compose down
```

Xóa toàn bộ container và dữ liệu database để chạy lại từ đầu:

```powershell
docker compose down -v
docker compose up -d
```

## Image Docker Hub

File `docker-compose.yml` trong repository dùng trực tiếp các image public sau:

```text
vuducanh2923/frontend:latest
vuducanh2923/api-gateway:latest
vuducanh2923/auth-service:latest
vuducanh2923/catalog-service:latest
vuducanh2923/order-service:latest
vuducanh2923/payment-service:latest
vuducanh2923/database:latest
```

Người dùng chỉ cần `docker compose up -d`, Docker sẽ tự pull các image này nếu máy chưa có.

## Cấu Trúc Service

| Service | Port | Vai trò |
| --- | ---: | --- |
| `frontend` | `5173` | Giao diện người dùng đã build sẵn và chạy bằng Nginx |
| `api-gateway` | `8000` | Cổng API chính cho frontend, proxy request sang service phù hợp |
| `auth-service` | `8001` | Đăng ký, đăng nhập, người dùng, vai trò |
| `catalog-service` | `8002` | Sản phẩm, thương hiệu, danh mục, biến thể, hình ảnh, tồn kho |
| `order-service` | `8003` | Giỏ hàng, đơn hàng, mã giảm giá, yêu cầu bảo hành |
| `payment-service` | `8004` | Thanh toán, giao dịch thanh toán, hóa đơn |
| `database` | `3308` | MySQL dùng chung container, mỗi service dùng database riêng |

Các database mặc định:

- `auth-service`: `bstore_auth_db`
- `catalog-service`: `bstore_catalog_db`
- `order-service`: `bstore_order_db`
- `payment-service`: `bstore_payment_db`

Thông tin database trong Docker:

```text
host: database
port: 3306
username: bstore_user
password: bstore_password
root password: bstore_root_password
```

## Endpoint Chính

Frontend nên gọi API qua Gateway:

```text
http://localhost:8000/api
```

Một số endpoint được giữ theo format cũ:

- `POST /api/auth/login` -> `auth-service`
- `GET /api/products` -> `catalog-service`
- `POST /api/orders` -> `order-service`
- `POST /api/payments` -> `payment-service`

## Chạy Backend Từ Source

Nếu muốn phát triển backend trực tiếp không qua image Docker Hub, cài dependency cho từng service:

```powershell
.\scripts\setup-microservices.ps1
```

Chạy tất cả service Laravel ở local:

```powershell
.\scripts\start-microservices.ps1
```

Hoặc chạy từng service thủ công:

```powershell
cd services\auth-service; php artisan serve --host=127.0.0.1 --port=8001
cd services\catalog-service; php artisan serve --host=127.0.0.1 --port=8002
cd services\order-service; php artisan serve --host=127.0.0.1 --port=8003
cd services\payment-service; php artisan serve --host=127.0.0.1 --port=8004
cd services\api-gateway; php artisan serve --host=127.0.0.1 --port=8000
```

## Build Và Push Image

Dành cho maintainer khi cần build lại image mới và push lên Docker Hub. Cách này cần workspace local có cả `bstore-backend` và `bstore-frontend` cùng cấp, vì script sẽ dùng file compose build ở thư mục cha nếu có.

Đăng nhập Docker Hub:

```powershell
docker login
```

Build và push toàn bộ image:

```powershell
.\scripts\push-dockerhub.ps1 -Namespace vuducanh2923 -Tag latest
```

Chỉ build và push một vài service:

```powershell
.\scripts\push-dockerhub.ps1 -Namespace vuducanh2923 -Tag latest -Services api-gateway,frontend
```

Dockerfile của từng service nằm tại:

- `services/api-gateway/Dockerfile`
- `services/auth-service/Dockerfile`
- `services/catalog-service/Dockerfile`
- `services/order-service/Dockerfile`
- `services/payment-service/Dockerfile`
- `services/database/Dockerfile`

## Ghi Chú Kiến Trúc

- Root repository không còn là một Laravel app độc lập, vì vậy không chạy `php artisan` ở root.
- Mỗi service sở hữu database riêng và không tạo foreign key xuyên service.
- Các cột như `user_id`, `order_id`, `product_variant_id` là external reference giữa các service.
- Các luồng nghiệp vụ phức tạp nên đi qua API Gateway hoặc event/message broker thay vì truy cập trực tiếp database của service khác.
