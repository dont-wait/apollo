# Thiết kế cơ sở dữ liệu đề xuất cho Blog cá nhân

## 1. Phạm vi hệ thống

Đây là blog cá nhân với các vai trò và chức năng chính:

- **Admin** là chủ blog, có quyền tạo, sửa, duyệt, lên lịch và xuất bản bài viết.
- **User** chỉ đọc bài viết; user phải đăng nhập để like, bình luận và bookmark bài viết.
- Hệ thống định kỳ dùng AI để thu thập tin tức/công nghệ từ Internet.
- AI chỉ tạo dữ liệu nháp hoặc weekly review. Admin phải kiểm tra trước khi tạo bài public.
- Chỉ bài viết có `status = 'PUBLISHED'` và chưa bị soft-delete mới hiển thị công khai.

Database chính: **MySQL** qua Laravel Eloquent.

## 2. Nguyên tắc thiết kế

- Dùng foreign key và unique index để bảo vệ các quan hệ quan trọng ở database level.
- Dùng soft delete cho user và post khi cần giữ lịch sử; query public luôn lọc `deleted_at IS NULL`.
- Không tạo hệ thống auth/token riêng nếu blog dùng Laravel session authentication.
- Không dùng tên `jobs` cho job nghiệp vụ vì Laravel database queue đã sử dụng bảng `jobs`.
- Counter như `like_count` chỉ là dữ liệu denormalized. Source of truth vẫn là bảng mapping.
- Không tự động publish nội dung do AI tạo.
- Các thao tác convert weekly review thành post phải chạy trong transaction.

## 3. Các bảng

### 3.1. Bảng Laravel có sẵn

Giữ các bảng framework do Laravel tạo và không thay đổi mục đích của chúng:

- `sessions`
- `password_reset_tokens`
- `jobs`
- `job_batches`
- `failed_jobs`
- `cache`
- `cache_locks`

`jobs` là bảng queue của Laravel, không dùng làm bảng theo dõi pipeline AI.

### 3.2. `users`

Tài khoản Admin và User.

| Cột | Kiểu | Ràng buộc | Mô tả |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment | Định danh user |
| `name` | VARCHAR(255) | NOT NULL | Tên hiển thị |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE | Email đăng nhập |
| `password` | VARCHAR(255) | NOT NULL | Mật khẩu đã hash |
| `role` | ENUM('ADMIN','USER') | NOT NULL, default `USER` | Phân quyền server-side |
| `status` | ENUM('ACTIVE','INACTIVE') | NOT NULL, default `ACTIVE` | Trạng thái tài khoản |
| `avatar_url` | VARCHAR(1000) | NULL | URL avatar nếu cần |
| `last_login_at` | DATETIME | NULL | Lần đăng nhập gần nhất |
| `created_at` | DATETIME | NOT NULL | Thời điểm tạo |
| `updated_at` | DATETIME | NOT NULL | Thời điểm cập nhật |
| `deleted_at` | DATETIME | NULL | Soft delete |

Index:

- `UNIQUE(email)`
- `INDEX(role, status)`

Quy tắc:

- Không có public endpoint để user tự gán role Admin.
- Admin được seed hoặc tạo bởi Admin hiện tại.
- Luôn loại trừ `password` khỏi response/API resource.

### 3.3. `categories`

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `parent_id` | BIGINT UNSIGNED | NULL, FK → `categories.id` |
| `name` | VARCHAR(100) | NOT NULL |
| `slug` | VARCHAR(120) | NOT NULL, UNIQUE |
| `description` | VARCHAR(500) | NULL |
| `status` | ENUM('ACTIVE','INACTIVE') | NOT NULL, default `ACTIVE` |
| `created_at` | DATETIME | NOT NULL |
| `updated_at` | DATETIME | NOT NULL |

Index:

- `UNIQUE(slug)`
- `INDEX(parent_id)`

Ở MVP, giao diện chỉ cần danh mục một cấp dù schema đã hỗ trợ `parent_id`.

### 3.4. `tags`

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `name` | VARCHAR(60) | NOT NULL, UNIQUE |
| `slug` | VARCHAR(80) | NOT NULL, UNIQUE |
| `created_at` | DATETIME | NOT NULL |

### 3.5. `media`

Metadata của file; binary lưu ở local storage, S3, MinIO hoặc R2.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `uploaded_by` | BIGINT UNSIGNED | NOT NULL, FK → `users.id` |
| `file_name` | VARCHAR(255) | NOT NULL |
| `mime_type` | VARCHAR(100) | NOT NULL |
| `size_bytes` | BIGINT UNSIGNED | NOT NULL |
| `storage_key` | VARCHAR(500) | NOT NULL, UNIQUE |
| `url` | VARCHAR(1000) | NOT NULL |
| `width` | INT UNSIGNED | NULL |
| `height` | INT UNSIGNED | NULL |
| `created_at` | DATETIME | NOT NULL |

Không dùng unique index prefix 255 ký tự cho `storage_key`; nếu giới hạn index của môi trường không cho phép, thêm một cột hash đầy đủ để unique.

Index:

- `UNIQUE(storage_key)`
- `INDEX(uploaded_by)`

### 3.6. `posts`

Bảng trung tâm của CMS.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `author_id` | BIGINT UNSIGNED | NOT NULL, FK → `users.id` |
| `category_id` | BIGINT UNSIGNED | NULL, FK → `categories.id` |
| `weekly_review_id` | BIGINT UNSIGNED | NULL, UNIQUE, FK → `weekly_reviews.id` |
| `thumbnail_media_id` | BIGINT UNSIGNED | NULL, FK → `media.id`, SET NULL khi xóa |
| `title` | VARCHAR(255) | NOT NULL |
| `slug` | VARCHAR(255) | NOT NULL, UNIQUE |
| `excerpt` | VARCHAR(500) | NULL |
| `markdown_content` | MEDIUMTEXT | NOT NULL |
| `status` | ENUM('DRAFT','REVIEW','SCHEDULED','PUBLISHED','ARCHIVED') | NOT NULL, default `DRAFT` |
| `source_type` | ENUM('MANUAL','AI_WEEKLY') | NOT NULL, default `MANUAL` |
| `seo_title` | VARCHAR(255) | NULL |
| `seo_description` | VARCHAR(320) | NULL |
| `canonical_url` | VARCHAR(500) | NULL |
| `published_at` | DATETIME | NULL |
| `scheduled_at` | DATETIME | NULL |
| `view_count` | BIGINT UNSIGNED | NOT NULL, default 0 |
| `like_count` | INT UNSIGNED | NOT NULL, default 0 |
| `created_at` | DATETIME | NOT NULL |
| `updated_at` | DATETIME | NOT NULL |
| `deleted_at` | DATETIME | NULL |

Index:

- `UNIQUE(slug)`
- `INDEX(status, deleted_at, published_at)`
- `INDEX(category_id, status, deleted_at, published_at)`
- `INDEX(status, scheduled_at)`
- `INDEX(author_id)`
- `FULLTEXT(title, excerpt, markdown_content)`

Invariant:

- `PUBLISHED` phải có `published_at`.
- `SCHEDULED` phải có `scheduled_at`.
- Public query bắt buộc `status = 'PUBLISHED'` và `deleted_at IS NULL`.
- Chỉ Admin được thay đổi status hoặc publish bài.
- `weekly_review_id` là nullable unique để một weekly review chỉ tạo tối đa một post.

### 3.7. `post_tags`

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `post_id` | BIGINT UNSIGNED | PK, FK → `posts.id`, CASCADE |
| `tag_id` | BIGINT UNSIGNED | PK, FK → `tags.id`, CASCADE |
| `created_at` | DATETIME | NOT NULL |

Khóa chính kép `PRIMARY KEY(post_id, tag_id)` chống duplicate mapping.

### 3.8. `comments`

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `post_id` | BIGINT UNSIGNED | NOT NULL, FK → `posts.id` |
| `user_id` | BIGINT UNSIGNED | NOT NULL, FK → `users.id` |
| `parent_id` | BIGINT UNSIGNED | NULL, FK → `comments.id` |
| `content` | TEXT | NOT NULL |
| `status` | ENUM('VISIBLE','HIDDEN','DELETED') | NOT NULL, default `VISIBLE` |
| `moderated_by` | BIGINT UNSIGNED | NULL, FK → `users.id` |
| `moderated_at` | DATETIME | NULL |
| `created_at` | DATETIME | NOT NULL |
| `updated_at` | DATETIME | NOT NULL |

Index:

- `INDEX(post_id, status, created_at)`
- `INDEX(parent_id)`
- `INDEX(user_id)`
- `INDEX(status, created_at)`

Khi tạo reply, application phải kiểm tra `parent_id` thuộc cùng `post_id` và giới hạn số cấp reply.

### 3.9. `post_likes`

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `user_id` | BIGINT UNSIGNED | PK, FK → `users.id`, CASCADE |
| `post_id` | BIGINT UNSIGNED | PK, FK → `posts.id`, CASCADE |
| `created_at` | DATETIME | NOT NULL |

Index thêm:

- `INDEX(post_id)`

Like/unlike yêu cầu đăng nhập. Khóa chính kép đảm bảo mỗi user chỉ like một bài một lần.

### 3.10. `bookmarks`

Cho phép user lưu các bài viết muốn đọc lại.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `user_id` | BIGINT UNSIGNED | PK, FK → `users.id`, CASCADE |
| `post_id` | BIGINT UNSIGNED | PK, FK → `posts.id`, CASCADE |
| `created_at` | DATETIME | NOT NULL |

Index thêm:

- `INDEX(post_id, user_id)`

Quy tắc:

- Chỉ user đã đăng nhập mới được bookmark.
- Một user không thể bookmark trùng một bài.
- MVP không cần `bookmark_count` trên `posts` vì bookmark là dữ liệu riêng của từng user.
- Có thể chỉ cho bookmark bài `PUBLISHED`.

Endpoint dự kiến:

```text
POST   /posts/{post}/bookmark
DELETE /posts/{post}/bookmark
GET    /me/bookmarks
```

## 4. Pipeline AI

### 4.1. `ai_sources`

Nguồn RSS, API hoặc website được phép crawl.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `name` | VARCHAR(150) | NOT NULL, UNIQUE |
| `source_type` | ENUM('RSS','API','WEBSITE') | NOT NULL, default `RSS` |
| `site_url` | VARCHAR(500) | NOT NULL |
| `feed_url` | VARCHAR(1000) | NULL |
| `trust_score` | TINYINT UNSIGNED | NOT NULL, default 5, CHECK 1–10 |
| `priority` | TINYINT UNSIGNED | NOT NULL, default 5 |
| `status` | ENUM('ACTIVE','INACTIVE') | NOT NULL, default `ACTIVE` |
| `last_fetched_at` | DATETIME | NULL |
| `created_at` | DATETIME | NOT NULL |
| `updated_at` | DATETIME | NOT NULL |

Index:

- `UNIQUE(name)`
- `INDEX(status, priority)`

### 4.2. `ai_articles`

Bài viết thu thập được từ Internet. Đây không phải nội dung public của blog.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `source_id` | BIGINT UNSIGNED | NOT NULL, FK → `ai_sources.id` |
| `collection_run_id` | BIGINT UNSIGNED | NULL, FK → `automation_runs.id`, SET NULL |
| `title` | VARCHAR(500) | NOT NULL |
| `url` | VARCHAR(2048) | NOT NULL |
| `canonical_url` | VARCHAR(2048) | NOT NULL |
| `canonical_url_hash` | CHAR(64) | NOT NULL, UNIQUE |
| `title_fingerprint` | CHAR(64) | NULL |
| `author` | VARCHAR(255) | NULL |
| `published_at` | DATETIME | NULL |
| `raw_summary` | TEXT | NULL |
| `raw_content` | MEDIUMTEXT | NULL |
| `status` | ENUM('COLLECTED','ANALYZED','FAILED') | NOT NULL, default `COLLECTED` |
| `collected_at` | DATETIME | NOT NULL |

Index:

- `UNIQUE(canonical_url_hash)`
- `INDEX(source_id, published_at)`
- `INDEX(published_at, status)`
- `INDEX(title_fingerprint)`
- `INDEX(collection_run_id)`

URL phải được normalize trước khi tính hash. Khi gặp URL trùng, bỏ qua record mới và ghi kết quả vào log của pipeline; không tạo record duplicate thứ hai.

### 4.3. `ai_article_analysis`

Lưu nhiều phiên bản phân tích cho một bài AI.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `article_id` | BIGINT UNSIGNED | NOT NULL, FK → `ai_articles.id`, CASCADE |
| `version` | INT UNSIGNED | NOT NULL |
| `is_current` | BOOLEAN | NOT NULL, default false |
| `status` | ENUM('SUCCESS','FAILED') | NOT NULL |
| `model_name` | VARCHAR(100) | NULL |
| `prompt_version` | VARCHAR(50) | NULL |
| `summary` | TEXT | NULL |
| `key_points` | JSON | NULL |
| `technologies` | JSON | NULL |
| `companies` | JSON | NULL |
| `opportunities` | JSON | NULL |
| `risks` | JSON | NULL |
| `raw_output` | JSON | NULL |
| `error_message` | TEXT | NULL |
| `input_tokens` | INT UNSIGNED | NULL |
| `output_tokens` | INT UNSIGNED | NULL |
| `created_at` | DATETIME | NOT NULL |

Index:

- `UNIQUE(article_id, version)`
- `INDEX(article_id, is_current)`

Application phải đảm bảo chỉ một analysis current cho mỗi article bằng transaction/lock.

### 4.4. `ai_topics` và `ai_article_topics`

Hai bảng này chỉ cần khi hệ thống có tính năng phân loại topic hoặc hiển thị trend.

`ai_topics`:

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `name` | VARCHAR(100) | NOT NULL, UNIQUE |
| `slug` | VARCHAR(120) | NOT NULL, UNIQUE |
| `status` | ENUM('ACTIVE','INACTIVE') | NOT NULL, default `ACTIVE` |

`ai_article_topics`:

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `article_id` | BIGINT UNSIGNED | PK, FK → `ai_articles.id`, CASCADE |
| `topic_id` | BIGINT UNSIGNED | PK, FK → `ai_topics.id` |
| `confidence` | DECIMAL(4,3) | NULL, CHECK 0.000–1.000 |

### 4.5. `weekly_reviews`

Bản tổng hợp theo tuần do AI tạo và Admin kiểm tra.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `week_start` | DATE | NOT NULL, UNIQUE |
| `week_end` | DATE | NOT NULL |
| `title` | VARCHAR(255) | NOT NULL |
| `excerpt` | VARCHAR(500) | NULL |
| `markdown_content` | MEDIUMTEXT | NOT NULL |
| `status` | ENUM('DRAFT','REVIEW','CONVERTED','DISCARDED') | NOT NULL, default `DRAFT` |
| `quality_warning` | TEXT | NULL |
| `suggested_category_id` | BIGINT UNSIGNED | NULL, FK → `categories.id`, SET NULL |
| `suggested_tags` | JSON | NULL |
| `generation_run_id` | BIGINT UNSIGNED | NULL, FK → `automation_runs.id`, SET NULL |
| `model_name` | VARCHAR(100) | NULL |
| `generated_at` | DATETIME | NULL |
| `reviewed_by` | BIGINT UNSIGNED | NULL, FK → `users.id`, SET NULL |
| `reviewed_at` | DATETIME | NULL |
| `created_at` | DATETIME | NOT NULL |
| `updated_at` | DATETIME | NOT NULL |

Index:

- `UNIQUE(week_start)`
- `INDEX(status, week_start)`

Một tuần chỉ có một weekly review. `week_start` phải được chuẩn hóa theo timezone thống nhất, tốt nhất là UTC.

### 4.6. `weekly_review_sources`

Mapping các bài AI được dùng làm citation cho weekly review.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `review_id` | BIGINT UNSIGNED | PK, FK → `weekly_reviews.id`, CASCADE |
| `article_id` | BIGINT UNSIGNED | PK, FK → `ai_articles.id` |
| `topic_id` | BIGINT UNSIGNED | NULL, FK → `ai_topics.id`, SET NULL |
| `is_representative` | BOOLEAN | NOT NULL, default false |
| `citation_order` | SMALLINT UNSIGNED | NULL |

Khóa chính kép `PRIMARY KEY(review_id, article_id)` chống trùng citation.

### 4.7. `weekly_review_revisions` tùy chọn

Chỉ tạo bảng này nếu Admin cần khôi phục lịch sử chỉnh sửa. Nếu cần khôi phục toàn bộ bản nháp, revision nên lưu cả title, excerpt và markdown content, không chỉ markdown.

## 5. Theo dõi pipeline AI

### 5.1. `automation_runs`

Không dùng tên `jobs` để tránh xung đột Laravel queue.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `run_type` | ENUM('COLLECT','ANALYZE','GENERATE_REVIEW','PUBLISH_SCHEDULED') | NOT NULL |
| `status` | ENUM('PENDING','RUNNING','SUCCESS','PARTIAL','FAILED') | NOT NULL, default `PENDING` |
| `trigger_type` | ENUM('SCHEDULED','MANUAL') | NOT NULL, default `SCHEDULED` |
| `triggered_by` | BIGINT UNSIGNED | NULL, FK → `users.id`, SET NULL |
| `idempotency_key` | VARCHAR(128) | NULL, UNIQUE |
| `payload` | JSON | NULL |
| `attempt` | TINYINT UNSIGNED | NOT NULL, default 0 |
| `max_attempts` | TINYINT UNSIGNED | NOT NULL, default 3 |
| `locked_at` | DATETIME | NULL |
| `started_at` | DATETIME | NULL |
| `finished_at` | DATETIME | NULL |
| `error_message` | TEXT | NULL |
| `created_at` | DATETIME | NOT NULL |

Index:

- `UNIQUE(idempotency_key)`
- `INDEX(run_type, status, created_at)`
- `INDEX(status, locked_at)`

Worker phải claim run bằng một thao tác atomic. Run bị kẹt ở `RUNNING` phải có thể được thu hồi sau thời gian lease.

### 5.2. `automation_run_logs` tùy chọn

Chỉ cần khi cần log chi tiết theo từng source hoặc article.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, auto increment |
| `run_id` | BIGINT UNSIGNED | NOT NULL, FK → `automation_runs.id`, CASCADE |
| `level` | ENUM('INFO','WARN','ERROR') | NOT NULL, default `INFO` |
| `ref_type` | VARCHAR(32) | NULL |
| `ref_id` | BIGINT UNSIGNED | NULL |
| `message` | TEXT | NOT NULL |
| `created_at` | DATETIME | NOT NULL |

Index:

- `INDEX(run_id, created_at)`
- `INDEX(ref_type, ref_id, created_at)`

## 6. Luồng nghiệp vụ chính

### 6.1. Publish bài viết

1. Admin tạo bài ở `DRAFT`.
2. Admin chỉnh sửa và chuyển sang `REVIEW` nếu cần.
3. Admin publish ngay hoặc đặt `SCHEDULED`.
4. Scheduler atomically chuyển bài đủ điều kiện sang `PUBLISHED` và set `published_at`.
5. Public chỉ query `PUBLISHED` và `deleted_at IS NULL`.

### 6.2. Like và bookmark

1. User đăng nhập.
2. Kiểm tra bài đang `PUBLISHED`.
3. Insert vào `post_likes` hoặc `bookmarks`.
4. Duplicate mapping được xem là thao tác idempotent.
5. Unlike/unbookmark xóa đúng mapping tương ứng.

### 6.3. Comment

1. User đăng nhập và gửi comment.
2. Validate độ dài, nội dung và parent comment.
3. Comment mới có thể ở `VISIBLE` hoặc chờ moderation tùy chính sách.
4. Admin có thể chuyển sang `HIDDEN` hoặc `DELETED`.
5. Không hard-delete comment trong luồng moderation thông thường.

### 6.4. AI weekly review

1. Scheduler dispatch Laravel queue job.
2. Collector chỉ lấy `ai_sources` có `status = 'ACTIVE'`.
3. Normalize URL và upsert theo `canonical_url_hash`.
4. AI phân tích tạo version mới trong `ai_article_analysis`.
5. Generator tạo hoặc cập nhật `weekly_reviews` ở trạng thái `DRAFT`.
6. Admin kiểm tra citation, nội dung và cảnh báo chất lượng.
7. Khi Admin đồng ý, transaction tạo một `posts` với `weekly_review_id`.
8. Admin vẫn là người quyết định publish bài viết.

## 7. Bảo mật và vận hành

- Hash password bằng cơ chế Laravel mặc định, không tự lưu plaintext.
- Dùng Policy/Gate để bảo vệ CMS và moderation.
- Rate limit login, comment, like và bookmark nếu endpoint bị lạm dụng.
- Sanitize Markdown trước khi render HTML.
- Không trả password hoặc dữ liệu private của user ra API.
- Lưu URL nguồn và attribution cho nội dung do AI tổng hợp.
- Tôn trọng robots.txt, điều khoản sử dụng và bản quyền của nguồn tin.
- Chạy queue AI qua Laravel `jobs`; không tự xây queue thay thế nếu chưa cần.

## 8. Những bảng chưa cần trong MVP

Chưa cần tạo nếu chưa có yêu cầu cụ thể:

- `refresh_tokens`: blog dùng Laravel session.
- `conversations`, `messages`: chưa có chức năng chat/contact.
- `weekly_review_topics`: chỉ thêm khi UI cần hiển thị trend theo topic.
- `weekly_review_revisions`: chỉ thêm khi cần khôi phục lịch sử chỉnh sửa.
- `automation_run_logs`: có thể dùng Laravel logs trước, sau đó thêm khi cần dashboard pipeline.
