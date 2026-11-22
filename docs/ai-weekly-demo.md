# Demo AI Weekly: thêm nguồn → thu thập → phân tích → tạo bài viết

Tài liệu hướng dẫn demo luồng tự động tạo bài **AI Weekly** cho admin: thêm nguồn tin, chạy pipeline, duyệt bản tổng hợp tuần và chuyển thành bài viết nháp.

## Luồng tổng quan

```
Admin thêm nguồn (RSS / API)          /admin/ai-sources
        │
        ▼
COLLECT   lấy bài từ mọi nguồn ACTIVE, chuẩn hoá URL, loại trùng  → ai_articles
        │
        ▼
ANALYZE   LLM tóm tắt từng bài + gán topic (tiếng Việt)           → ai_article_analyses
        │
        ▼
GENERATE_REVIEW   chọn top story theo trust/priority, LLM viết bản tuần
        │         có trích dẫn [1], [2]… + mục "Nguồn tham khảo"   → weekly_reviews (REVIEW)
        ▼
Admin duyệt / sửa / regenerate theo section   /admin/weekly-reviews/{id}
        │
        ▼
"Chuyển thành bài viết" → Post (DRAFT, source_type = AI_WEEKLY)
```

Mỗi bước chạy qua **queue** và được ghi vào bảng `automation_runs` (xem ở tab **Pipeline**).

## 1. Chuẩn bị môi trường

### 1.1. Biến môi trường (`.env`)

```env
AI_WEEKLY_MODEL=qwen2.5:7b
AI_WEEKLY_MODEL_URL=http://127.0.0.1:11435/v1/chat/completions   # endpoint kiểu OpenAI chat completions
AI_WEEKLY_MODEL_KEY=                                              # để trống nếu model local
AI_WEEKLY_MODEL_TIMEOUT=600
AI_WEEKLY_AUTHOR_ID=                                              # id admin làm tác giả bài nháp khi chạy tự động
AI_WEEKLY_GENERIC_API_TOKEN=                                      # chỉ cần cho nguồn API (adapter generic_json)
QUEUE_CONNECTION=database
```

Các tham số khác (mặc định trong `config/ai_weekly.php`):

| Biến | Mặc định | Ý nghĩa |
|---|---|---|
| `AI_WEEKLY_TIMEZONE` | `Asia/Ho_Chi_Minh` | Múi giờ tính "tuần" (thứ Hai → Chủ nhật) |
| `AI_WEEKLY_COLLECT_SCHEDULE` | `0 */6 * * *` | Thu thập mỗi 6 giờ |
| `AI_WEEKLY_ANALYZE_SCHEDULE` | `30 */6 * * *` | Phân tích mỗi 6 giờ (lệch 30 phút) |
| `AI_WEEKLY_REVIEW_SCHEDULE` | `0 8 * * 1` | 8h sáng thứ Hai tạo bản tuần **trước** |
| `AI_WEEKLY_MIN_ARTICLES` / `MIN_SOURCES` | `5` / `2` | Dưới ngưỡng → review có `quality_warning` |
| `AI_WEEKLY_MAX_STORIES` | `10` | Số tin nổi bật đưa vào snapshot |
| `AI_WEEKLY_MAX_ITEMS_PER_SOURCE` | `200` | Số item tối đa đọc mỗi lần/nguồn |
| `AI_WEEKLY_MAX_FEED_BYTES` | `5000000` | Giới hạn dung lượng feed |

### 1.2. Bật model Qwen (Ollama qua Docker)

Model chạy local bằng **Ollama** trong Docker (service `ollama` trong `docker-compose.yml`, profile `ai`), expose tại `127.0.0.1:11435` – khớp với `AI_WEEKLY_MODEL_URL` ở trên (Ollama có endpoint tương thích OpenAI tại `/v1/chat/completions`).

```bash
make model-up      # = docker compose up -d --wait ollama  +  docker compose run --rm ollama-pull
make model-down    # dừng container, giữ nguyên model đã tải (volume ollama_data)
```

- Lần đầu sẽ tải `qwen2.5:7b` (~4–5 GB), cần chờ; các lần sau chạy ngay. Tên model lấy từ `AI_WEEKLY_MODEL` (mặc định `qwen2.5:7b`).
- Tinh chỉnh (tuỳ chọn, đặt trong `.env`): `OLLAMA_CPUS` (mặc định 6), `OLLAMA_PORT` (11435), `OLLAMA_CONTEXT_LENGTH` (8192), `OLLAMA_KEEP_ALIVE` (1m – sau 1 phút không dùng model bị gỡ khỏi RAM nên request đầu sau đó chậm hơn).
- Chạy trên CPU nên mỗi bài phân tích có thể mất vài chục giây; vì vậy `AI_WEEKLY_MODEL_TIMEOUT=600`. Khi demo nên giới hạn số bài (vài nguồn, ít bài) hoặc chạy Analyze trước buổi demo.

Kiểm tra model đã sẵn sàng:

```bash
docker compose exec ollama ollama list                 # phải thấy qwen2.5:7b
curl -s http://127.0.0.1:11435/v1/models               # trả JSON danh sách model
curl -s http://127.0.0.1:11435/v1/chat/completions \
  -H 'Content-Type: application/json' \
  -d '{"model":"qwen2.5:7b","messages":[{"role":"user","content":"Xin chào"}]}'
```

> Nếu bỏ qua bước này, Analyze/Generate sẽ lỗi *"AI model is not configured."* (thiếu biến `.env`) hoặc lỗi kết nối (container chưa chạy).

### 1.3. Dữ liệu & tiến trình

```bash
php artisan migrate
php artisan db:seed --class=AiTopicSeeder   # 6 topic: LLM, Agents, Robotics, AI Coding, Regulation, Research
php artisan queue:work                      # bắt buộc, nếu không job sẽ đứng ở PENDING
php artisan schedule:work                   # chỉ cần khi muốn demo chạy theo lịch
```

> Muốn demo nhanh nên chạy tay các bước bằng nút trên giao diện (mục 3–5) thay vì chờ lịch.

Đăng nhập tài khoản **ADMIN** đang `ACTIVE`; menu AI Weekly gồm 4 tab: *Weekly Reviews · Nguồn · Bài thu thập · Pipeline*.

## 2. Thêm nguồn

Vào **Admin → AI Weekly → Nguồn** (`/admin/ai-sources`).

| Trường | Ghi chú |
|---|---|
| Tên nguồn | Duy nhất, tối đa 150 ký tự |
| Loại nguồn | `RSS`, `API`, `WEBSITE` |
| Trạng thái | `ACTIVE` mới được thu thập; `INACTIVE` bị bỏ qua |
| Website | URL trang chủ, bắt buộc |
| Feed hoặc API URL | **Bắt buộc** với RSS và API |
| Adapter API | **Bắt buộc** với API (hiện có `generic_json`) |
| Độ tin cậy (1–10) | Ưu tiên chọn làm tin nổi bật |
| Ưu tiên (1–10) | Tiêu chí phụ khi xếp hạng |

Ví dụ nguồn RSS để demo:

| Tên | Loại | Feed URL |
|---|---|---|
| Hugging Face Blog | RSS | `https://huggingface.co/blog/feed.xml` |
| OpenAI News | RSS | `https://openai.com/news/rss.xml` |
| Google AI Blog | RSS | `https://blog.google/technology/ai/rss/` |

> Nên thêm **≥ 2 nguồn** và **≥ 5 bài** trong tuần, nếu không bản review sẽ hiện cảnh báo *"Nguồn tuần này còn hạn chế."*

Thêm qua API (cùng endpoint mà giao diện dùng, cần session admin + CSRF):

```http
POST /admin/ai-sources
Content-Type: application/json

{
  "name": "Hugging Face Blog",
  "source_type": "RSS",
  "site_url": "https://huggingface.co/blog",
  "feed_url": "https://huggingface.co/blog/feed.xml",
  "trust_score": 8,
  "priority": 6,
  "status": "ACTIVE"
}
```

Sửa nguồn: `PUT /admin/ai-sources/{id}` (cùng payload; tên vẫn phải duy nhất).

### Loại nguồn

- **RSS**: đọc RSS 2.0 / Atom (`title`, `link`, `description`/`summary`, `content:encoded`, `author`, `pubDate`/`published`/`updated`).
- **API**: gọi `feed_url` kèm header theo adapter (`generic_json` gửi `Authorization: Bearer <AI_WEEKLY_GENERIC_API_TOKEN>`). JSON trả về phải có mảng `items`, mỗi item gồm `title`, `url`, và tuỳ chọn `summary`, `content`, `author`, `published_at`:
  ```json
  { "items": [ { "title": "…", "url": "https://…", "summary": "…", "published_at": "2026-11-18T09:00:00Z" } ] }
  ```
- **WEBSITE**: chỉ lưu để tham chiếu, **không** có adapter tự động nên pipeline không thu thập.

### Ràng buộc an toàn (SSRF)

- Chỉ chấp nhận `http`/`https`, không có user/password trong URL, không hỗ trợ host IPv6.
- Host phải phân giải ra IP **công khai** (chặn `127.0.0.1`, `10.x`, `192.168.x`…) → lỗi *"URL phải trỏ tới địa chỉ công khai."*
- Không follow redirect; giới hạn dung lượng phản hồi (`MAX_FEED_BYTES`).
- Vì vậy **không thể demo bằng feed `localhost`**; hãy dùng feed công khai.

## 3. Thu thập bài (COLLECT)

1. Vào **Pipeline** (`/admin/automation-runs`) → bấm chạy **Collect** (`POST /admin/automation-runs/collect`).
2. Hệ thống tạo 1 run `COLLECT`, mỗi nguồn `ACTIVE` loại RSS/API là một job `CollectSource`.
3. Xem tiến độ từng nguồn trong trang chi tiết run: `new` / `duplicate` / `invalid`.
4. Kết quả xem ở tab **Bài thu thập** (`/admin/ai-articles`).

Cách khử trùng:
- URL được chuẩn hoá (lowercase host, bỏ port mặc định, bỏ tham số `utm_*`, sắp xếp query) rồi hash → cùng URL chỉ lưu một lần.
- Cùng nguồn + cùng tiêu đề + cùng ngày + cùng nội dung/tóm tắt → đánh dấu `duplicate_of`, không đưa vào tổng hợp.

Trạng thái run: `PENDING → RUNNING → SUCCESS | PARTIAL | FAILED`. Nguồn lỗi 429/5xx được retry (tối đa `AI_WEEKLY_MAX_ATTEMPTS = 3`, tôn trọng `Retry-After`); run bị treo > 20 phút sẽ được `ai-weekly:reconcile` (mỗi 10 phút) xử lý.

## 4. Phân tích bài (ANALYZE)

1. Bấm **Analyze** (`POST /admin/automation-runs/analyze`), hoặc chạy lại cho một bài từ trang chi tiết bài.
2. Job `AnalyzeArticles` xử lý lần lượt từng bài chưa phân tích (có `raw_summary` hoặc `raw_content`), gọi LLM với tối đa 6000 ký tự nội dung.
3. Mỗi bài nhận: `summary` (2 câu), `key_points`, `technologies`, `companies`, `opportunities`, `risks` và tối đa 2 `topics` (kèm `confidence`).
4. Bài đủ điều kiện vào tổng hợp khi có analysis `SUCCESS` ở phiên bản hiện tại (`is_current`).

## 5. Tạo bản tổng hợp tuần (GENERATE_REVIEW)

### Tạo thủ công

```http
POST /admin/weekly-reviews/generate
{ "week_start": "2026-11-16" }      # bắt buộc là thứ Hai, định dạng Y-m-d
```

Trả `202 { "run_id": … }`. Nếu `week_start` không phải thứ Hai → `422`.

### Tạo tự động

Thứ Hai 08:00 (giờ VN) hệ thống tự tạo review cho **tuần trước**.

### Quy trình bên trong

1. Lấy các bài đã phân tích trong tuần đó (`published_at`, nếu thiếu thì dùng `collected_at`), bỏ bài trùng.
2. Tính số bài theo topic và so với tuần trước (chỉ kết luận tăng/giảm khi có đủ dữ liệu baseline).
3. Xếp hạng bài theo `trust_score` → `priority` → ngày đăng; lấy `MAX_STORIES` bài nổi bật + 3 đại diện mỗi topic → **snapshot** lưu trong `automation_runs.payload`.
4. LLM viết bản tuần theo snapshot, mỗi dữ kiện gắn token `[[ID]]`; hệ thống kiểm tra:
   - đủ 6 heading, đúng thứ tự (xem dưới);
   - mọi ID trích dẫn nằm trong snapshot và khớp `citation_ids`;
   - loại bỏ link/URL do LLM tự chèn.
5. Token `[[ID]]` được đánh lại thành `[1]`, `[2]`… và hệ thống **tự sinh** mục `## Nguồn tham khảo` từ tiêu đề + URL bài gốc.
6. Lưu `weekly_reviews` (status `REVIEW`) cho một tuần chỉ **một** review; đồng thời tạo luôn Post nháp (khi chạy tự động) và gửi thông báo `WeeklyReviewReady` cho các admin.

Cấu trúc bài (cố định):

```
## Tổng quan tuần
## Tin nổi bật
## Nghiên cứu đáng chú ý
## Chủ đề và xu hướng
## Cơ hội
## Rủi ro và giới hạn
## Nguồn tham khảo      ← hệ thống tự tạo
```

## 6. Duyệt, sửa và chuyển thành bài viết

Vào **Weekly Reviews** (`/admin/weekly-reviews`) → mở review của tuần.

| Hành động | Endpoint | Ghi chú |
|---|---|---|
| Sửa tiêu đề / tóm tắt / nội dung | `PUT /admin/weekly-reviews/{id}` | Gửi kèm `revision`; lệch revision → `409` (tránh ghi đè). Quét từ ngữ độc hại như bài viết thường. Citation tự đồng bộ theo nội dung |
| Regenerate toàn bài hoặc 1 section | `POST /admin/weekly-reviews/{id}/regenerate` | `section`: `full`, `overview`, `top_stories`, `research`, `topics_trends`, `opportunities`, `risks_limits`. Tạo bản **candidate**, chưa ghi đè |
| Áp dụng candidate | `POST /admin/weekly-reviews/{id}/apply-candidate` | Cần `run_id` + `revision` còn khớp; thành công thì `revision + 1` |
| Chuyển thành bài viết | `POST /admin/weekly-reviews/{id}/convert-to-post` | Tạo `Post` trạng thái `DRAFT`, `source_type = AI_WEEKLY`, slug `…-weekly-{id}`; review chuyển `CONVERTED`. Gọi lại sẽ trả bài đã có (`200`) |

Sau khi chuyển, mở bài nháp ở **Admin → Posts** để chỉnh ảnh/category/tag rồi xuất bản như bài thường. Bài nháp đã gợi ý sẵn tag nếu tag đó tồn tại.

## 7. Kịch bản demo gợi ý (~5 phút)

0. Trước buổi demo: `make model-up`, `php artisan queue:work`, kiểm tra model bằng `ollama list` (mục 1.2).
1. Giới thiệu sơ đồ ở đầu tài liệu.
2. Tab **Nguồn**: thêm 2–3 nguồn RSS công khai (mục 2). Thử nhập `http://127.0.0.1/feed` để cho thấy lỗi chặn địa chỉ nội bộ.
3. Tab **Pipeline**: bấm **Collect**, mở trang chi tiết run xem tiến độ từng nguồn; chạy lại Collect để thấy số `duplicate` tăng, `new = 0`.
4. Tab **Bài thu thập**: xem danh sách bài và nguồn gốc.
5. Bấm **Analyze**, mở một bài xem tóm tắt + topic do LLM sinh.
6. Tạo review cho tuần hiện tại (hoặc tuần gần nhất có dữ liệu) bằng `week_start` là thứ Hai.
7. Mở **Weekly Reviews**: đọc bài, chỉ ra trích dẫn `[n]` và mục *Nguồn tham khảo*; regenerate riêng section *Rủi ro và giới hạn* → áp dụng candidate.
8. **Chuyển thành bài viết** → mở bài nháp trong Posts.

## 8. Xử lý sự cố

| Triệu chứng | Nguyên nhân thường gặp |
|---|---|
| Run đứng ở `PENDING` | Chưa chạy `php artisan queue:work` |
| *Không có nguồn đang hoạt động.* | Chưa có nguồn RSS/API `ACTIVE` (WEBSITE không tính) |
| *AI model is not configured.* | Thiếu `AI_WEEKLY_MODEL` hoặc `AI_WEEKLY_MODEL_URL` |
| Analyze/Generate lỗi kết nối hoặc timeout | Container Ollama chưa chạy (`make model-up`), model chưa tải xong, hoặc CPU chậm → tăng `AI_WEEKLY_MODEL_TIMEOUT` |
| Nguồn `FAILED` với *Source URL resolves to a restricted address* | Feed trỏ về IP nội bộ |
| *No analyzed articles for this week.* | Chưa chạy Analyze, hoặc `week_start` không có bài nào |
| *Draft citations do not match the approved snapshot.* | LLM trích dẫn sai ID → bấm tạo lại (tự retry tối đa 3 lần) |
| *Không có admin đang hoạt động để sở hữu bài…* | Không có admin `ACTIVE` (hoặc `AI_WEEKLY_AUTHOR_ID` không hợp lệ) |
| Review hiện *Nguồn tuần này còn hạn chế.* | Dưới 5 bài hoặc dưới 2 nguồn trong tuần |

## 9. Tham chiếu code

| Phần | File |
|---|---|
| Cấu hình | `config/ai_weekly.php` |
| Lịch chạy | `routes/console.php` |
| Route admin | `routes/web.php` (nhóm `ai-sources`, `ai-articles`, `automation-runs`, `weekly-reviews`) |
| Quản lý nguồn | `AiSourceController`, `StoreAiSourceRequest`, `App\AiWeekly\SourceFetcher` |
| Thu thập / phân tích | `App\Jobs\AiWeekly\CollectSource`, `AnalyzeArticles`, `ArticleNormalizer` |
| Điều phối run | `App\AiWeekly\RunCoordinator`, `AutomationRunController` |
| Tạo bản tuần | `GenerateWeeklyReview`, `WeeklyDraftBuilder`, `Prompts`, `SectionEditor`, `WeeklyWindow` |
| Duyệt & chuyển bài | `WeeklyReviewController`, `DraftPostCreator` |
| Giao diện | `resources/views/admin/ai-weekly/*`, `resources/js/admin/ai-weekly.js` |
