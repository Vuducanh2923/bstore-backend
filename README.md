# BStore Backend Microservices

Repo nay da duoc tach thanh monorepo microservices Laravel. Root repo chi dung de dieu phoi script/tai lieu; toan bo code chay thuc te nam trong `services/`.

## Cau truc

| Service | Port | Trach nhiem |
| --- | ---: | --- |
| `services/api-gateway` | `8000` | Mot cua ngo API cho frontend, proxy request sang service phu hop |
| `services/auth-service` | `8001` | Dang ky, dang nhap, user, role |
| `services/catalog-service` | `8002` | Product, brand, category, variant, image, inventory, warranty policy |
| `services/order-service` | `8003` | Cart, order, discount, warranty request |
| `services/payment-service` | `8004` | Payment, payment transaction, invoice |

Gateway giu format endpoint cu, vi du:

- `POST /api/auth/login` -> auth-service
- `GET /api/products` -> catalog-service
- `POST /api/orders` -> order-service
- `POST /api/payments` -> payment-service

## Cai dat lan dau

Chay script setup de copy `.env`, cai dependency va tao app key cho tung service:

```powershell
.\scripts\setup-microservices.ps1
```

Neu muon lam thu cong, vao tung thu muc `services/*` va chay:

```powershell
copy .env.example .env
composer install
php artisan key:generate
```

## Database

Moi service chi cau hinh database cua minh:

- `auth-service`: `bstore_auth_db`
- `catalog-service`: `bstore_catalog_db`
- `order-service`: `bstore_order_db`
- `payment-service`: `bstore_payment_db`

Sau khi tao database trong MySQL, chay migrate theo tung service:

```powershell
cd services\auth-service; php artisan migrate --seed
cd ..\catalog-service; php artisan migrate
cd ..\order-service; php artisan migrate
cd ..\payment-service; php artisan migrate
```

## Chay local

Chay tat ca service bang script:

```powershell
.\scripts\start-microservices.ps1
```

Hoac mo 5 terminal rieng:

```powershell
cd services\auth-service; php artisan serve --host=127.0.0.1 --port=8001
cd services\catalog-service; php artisan serve --host=127.0.0.1 --port=8002
cd services\order-service; php artisan serve --host=127.0.0.1 --port=8003
cd services\payment-service; php artisan serve --host=127.0.0.1 --port=8004
cd services\api-gateway; php artisan serve --host=127.0.0.1 --port=8000
```

Frontend nen goi gateway:

```text
http://127.0.0.1:8000/api
```

## Chay bang Docker Compose

File `docker-compose.yml` nam o thu muc cha, cung cap voi `bstore-backend` va `bstore-frontend`.

Build va chay toan bo stack tu thu muc cha:

```powershell
cd ..
docker compose up --build
```

Hoac chay tu trong thu muc backend:

```powershell
docker compose -f ..\docker-compose.yml up --build
```

Compose se tao MySQL container, tu dong tao 4 database domain, chay migrate cho cac service co database va seed role mac dinh cho `auth-service`. Cac cong expose ra host:

- Gateway: `http://localhost:8000/api`
- Auth service: `http://localhost:8001`
- Catalog service: `http://localhost:8002`
- Order service: `http://localhost:8003`
- Payment service: `http://localhost:8004`
- MySQL: `localhost:3308`

Thong tin database mac dinh trong Docker:

```text
host: database
port: 3306
username: bstore_user
password: bstore_password
```

Neu can tao lai database tu dau:

```powershell
docker compose -f ..\docker-compose.yml down -v
docker compose -f ..\docker-compose.yml up --build
```

## Push image len Docker Hub

Dang nhap Docker Hub truoc:

```powershell
docker login
```

Sau do build va push toan bo image:

```powershell
.\scripts\push-dockerhub.ps1 -Namespace ten-dockerhub-cua-ban -Tag latest
```

Neu chi muon push mot vai service:

```powershell
.\scripts\push-dockerhub.ps1 -Namespace ten-dockerhub-cua-ban -Tag latest -Services api-gateway,frontend
```

Cac image da push co the dung truc tiep:

```text
vuducanh2923/api-gateway:latest
vuducanh2923/auth-service:latest
vuducanh2923/catalog-service:latest
vuducanh2923/order-service:latest
vuducanh2923/payment-service:latest
vuducanh2923/database:latest
vuducanh2923/frontend:latest
```

Nguoi khac co the chay stack bang file image-only o thu muc cha:

```powershell
docker compose -f docker-compose.hub.yml up -d
```

## Ghi chu kien truc

- Khong tao foreign key xuyen service. Cac cot nhu `user_id`, `order_id`, `product_variant_id` la external reference.
- CRUD tong quat da duoc tach theo domain, nen moi service chi expose resource minh so huu.
- Khi can giao tiep nghiep vu phuc tap hon, uu tien goi qua gateway hoac them event/message broker thay vi truy cap database cua service khac.
- Root repo khong con la mot Laravel app doc lap; khong chay `php artisan` o root nua.
