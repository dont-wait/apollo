## 1. Phạm vi và đầu ra

**Owner:** Sang

Module chịu trách nhiệm:

- Quản lý nguồn AI.
- Thu thập bài báo và nghiên cứu theo lịch hoặc thủ công.
- Chuẩn hóa, chống trùng và phân tích bằng AI.
- Phân nhóm chủ đề, tính xu hướng trong tuần.
- Sinh Weekly Review có nguồn trích dẫn.
- Cho Admin kiểm duyệt, chỉnh sửa và chuyển thành Post draft.
- Theo dõi trạng thái, lỗi và retry pipeline.

**Đầu ra tích hợp Member 1:**

```
{
  "weekly_review_id": 12,
  "title": "AI Weekly Review — Tuần ...",
  "excerpt": "Các diễn biến AI nổi bật trong tuần.",
  "markdown_content": "...",
  "sources": [
    {
      "article_id": 101,
      "title": "Tên bài nguồn",
      "url": "https://example.org/article",
      "source_name": "Tên nguồn",
      "citation_order": 1
    }
  ],
  "suggested_category_id": null,
  "suggested_tags": ["LLM", "Agents"]
}
```

Member 1 tiếp nhận dữ liệu thành `Post` có `status=DRAFT`, `source_type=AI_WEEKLY`. Việc publish hoặc đặt lịch publish thuộc Content module.

## 2\. Kiến trúc xử lý

Luồng nghiệp vụ:

```
Nguồn ACTIVE
    → Collector RSS/API
    → Chuẩn hóa và Deduplicate
    → AI Summarization + Topic Classification
    → Topic Aggregation + Trend Analysis
    → Generate Weekly Draft + Citation Validation
    → Admin Review/Edit
    → Post DRAFT
    → Admin publish qua Content module
```

Các thành phần dự kiến:

| Thành phần | Trách nhiệm |
|---|---|
| Source management | CRUD nguồn và trạng thái hoạt động |
| Collector adapters | Đọc RSS hoặc API, trả dữ liệu chuẩn hóa |
| Article normalizer | Chuẩn hóa URL, title, ngày và nội dung |
| Deduplication service | Phát hiện trùng URL/title |
| AI analysis service | Gọi model và validate structured output |
| Topic/trend service | Tổng hợp topic và so sánh tuần trước |
| Weekly draft generator | Sinh draft, gắn nguồn và cảnh báo chất lượng |
| Review conversion service | Chuyển review được Admin chọn thành Post draft |
| Automation jobs | Chạy nền, ghi trạng thái và retry |

HTTP chỉ kiểm tra yêu cầu, tạo run và dispatch job. Không gọi collector hoặc model trực tiếp trong request.

## 3\. Dữ liệu và thay đổi schema

Apollo đã có phần lớn bảng cần thiết. Spec sử dụng tên hiện có thay vì tạo bảng tương đương.

| Bảng hiện có | Vai trò |
|---|---|
| `ai_sources` | Nguồn RSS/API/website |
| `automation_runs` | Log collector, analysis và generation; thay cho `collection_jobs` |
| `ai_articles` | Bài nguồn, canonical URL và fingerprint |
| `ai_article_analysis` | Kết quả phân tích có version |
| `ai_topics` | Taxonomy chủ đề |
| `ai_article_topics` | Topic của từng bài |
| `weekly_reviews` | Nội dung tổng hợp chờ duyệt |
| `weekly_review_sources` | Bài nguồn được sử dụng trong review |
| `posts` | Nội dung chuyển sang Content module |

**Bổ sung đề xuất khi triển khai:**

| Vị trí | Bổ sung | Mục đích |
|---|---|---|
| `ai_articles` | `duplicate_of`, nullable FK đến chính bảng | Giữ bài trùng story và truy ngược bài đại diện |
| `ai_sources` | `adapter_key`, nullable | Chọn adapter API theo nguồn |
| `weekly_reviews` | `revision`, integer, mặc định 1 | Chặn lưu đè khi Admin đang chỉnh sửa |
| `automation_runs.payload` | Kết quả từng nguồn, snapshot đầu vào và candidate draft | Theo dõi lỗi, retry và regenerate an toàn |

Không lưu API key trong payload hoặc cấu hình nguồn trả về UI. Secret lấy từ cấu hình môi trường.

MVP lưu trend snapshot trong payload của generation run; chưa cần thêm bảng trend riêng. Snapshot gồm topic, số bài, baseline, chênh lệch và danh sách article hỗ trợ.

## 4\. Spec theo use case

### UC22 — Quản lý nguồn AI

Admin xem danh sách, thêm, sửa và bật/tắt nguồn.

**Trường nhập:**

- `name`: bắt buộc, tối đa 150 ký tự, unique.
- `source_type`: `RSS`, `API`, `WEBSITE`.
- `site_url`: bắt buộc, URL attribution.
- `feed_url`: bắt buộc với RSS/API.
- `trust_score`, `priority`: từ 1 đến 10; số lớn được ưu tiên hơn.
- `status`: `ACTIVE`, `INACTIVE`.
- `adapter_key`: bắt buộc với API cần adapter chuyên biệt.

**Quy tắc:**

- MVP hỗ trợ RSS và API có adapter; `WEBSITE` chưa được collector xử lý tự động.
- Collector kiểm tra nguồn còn active trước khi thực hiện request.
- Nguồn đã có article được vô hiệu hóa thay vì xóa.
- Chặn URL nội bộ, loopback, private network và redirect đến các địa chỉ này.

**Nghiệm thu:** nguồn inactive không phát sinh request thu thập; article luôn liên kết được với nguồn gốc.

### UC23 — Thu thập bài viết tự động

Admin hoặc scheduler tạo run `COLLECT`.

**Xử lý:**

1. Chụp danh sách nguồn cần chạy vào payload.
2. Dispatch job riêng cho từng nguồn.
3. Gọi adapter, chuẩn hóa và kiểm tra từng item.
4. Lưu bài mới sau bước deduplicate.
5. Ghi số bài mới, bài trùng, item lỗi và lỗi request theo nguồn.
6. Tổng hợp trạng thái run.

**Dữ liệu tối thiểu hợp lệ:** title, URL nguồn và `source_id`.

- Không có author hoặc ngày xuất bản: giữ `null`.
- Không có full content: dùng summary có sẵn và ghi nhận giới hạn khi phân tích.
- `last_fetched_at` chỉ cập nhật khi fetch và parse thành công, kể cả không có bài mới.

**Chính sách retry đề xuất:**

- Tối đa 3 lần cho mỗi đơn vị công việc.
- Retry network error, timeout, HTTP 429 và 5xx.
- Ưu tiên `Retry-After`; nếu không có, dùng backoff cấu hình.
- Lỗi cấu hình hoặc 4xx cố định: kết thúc và hiển thị lỗi.

**Trạng thái batch:**

- `SUCCESS`: mọi nguồn hoàn tất.
- `PARTIAL`: có nguồn thành công và nguồn thất bại.
- `FAILED`: mọi nguồn thất bại.

**Nghiệm thu:** một nguồn lỗi không ngăn nguồn khác lưu bài; chạy lại không tạo article trùng URL.

### UC24 — Deduplicate

**Chuẩn hóa URL:**

- Chuẩn hóa scheme/host, bỏ fragment.
- Bỏ tracking parameters trong danh sách cấu hình như `utm_*`.
- Giữ query có ý nghĩa nghiệp vụ.
- Không tùy tiện gộp HTTP/HTTPS hoặc thay đổi path.
- Tính SHA-256 thành `canonical_url_hash`.

Unique constraint hiện có trên hash là lớp bảo vệ khi nhiều worker cùng xử lý.

**Chuẩn hóa title:**

- Decode HTML entities.
- Chuẩn hóa Unicode, chữ thường và khoảng trắng.
- Tính `title_fingerprint`.

**Quyết định trùng:**

| Điều kiện | Kết quả |
|---|---|
| Cùng canonical URL | Dùng article hiện có |
| Cùng title và có bằng chứng cùng story | Lưu bài với `duplicate_of` |
| Chỉ giống title, chưa đủ chắc chắn | Giữ riêng |

Bài có `duplicate_of` không được phân tích hoặc tính trend lần nữa. MVP không dùng vector DB.

**Nghiệm thu:** hai request đồng thời cùng URL chỉ tạo một article; bài có title tương tự nhưng khác nội dung vẫn được giữ.

### UC25 — AI Summarization

Worker lấy article chưa phân tích, không phải duplicate và có nội dung sử dụng được.

**Structured output:**

```
{
  "summary": "Tóm tắt bằng tiếng Việt",
  "key_points": ["Thông tin chính từ nguồn"],
  "technologies": ["LLM"],
  "companies": ["Tên tổ chức"],
  "opportunities": ["Nhận định có điều kiện"],
  "risks": ["Rủi ro hoặc giới hạn"],
  "topics": [
    {
      "slug": "llm",
      "confidence": 0.9
    }
  ]
}
```

**Validation:**

- JSON đúng schema và kiểu dữ liệu.
- Các trường array chỉ chứa phần tử hợp lệ.
- Topic thuộc taxonomy active; confidence từ 0 đến 1.
- Có giới hạn độ dài cho summary và số phần tử.
- Không cho model tự bổ sung nguồn ngoài đầu vào.

**Lưu kết quả:**

- Lưu model identifier, prompt version, token usage nếu provider cung cấp.
- Mỗi lần phân tích lại tạo version mới.
- Khóa article khi cấp version và đổi `is_current`.
- Chỉ một analysis `SUCCESS` được chọn làm current.
- Analysis thất bại không thay thế kết quả thành công trước đó.

Nội dung quá dài được xử lý theo giới hạn cấu hình: chọn phần phù hợp hoặc chunk có giới hạn; lưu cách xử lý trong metadata đầu ra.

**Nghiệm thu:** invalid JSON không được đánh `ANALYZED`; retry thất bại được ghi lỗi; luôn giữ liên kết source article.

### UC26 — Topic Clustering \& Trend Analysis

MVP dùng taxonomy cố định trong `ai_topics`: LLM, Agents, Robotics, AI Coding, Regulation, Research… Danh sách có thể cấu hình và bổ sung.

**Weekly window đề xuất:**

- Tuần bắt đầu thứ Hai, timezone `Asia/Ho_Chi_Minh`.
- Truy vấn theo khoảng `[week_start, next_week_start)`, chuyển boundary sang UTC.
- `week_end` hiển thị ngày Chủ nhật.
- Dùng `published_at`; khi thiếu, dùng `collected_at` và ghi nhận fallback.

Chỉ lấy article không duplicate, có current analysis thành công.

**Tính trend:**

- `count`: số article khác nhau thuộc topic.
- `previous_count`: số bài của tuần trước cùng quy tắc.
- `delta_count = count - previous_count`.
- Chỉ tính tỷ lệ phần trăm khi `previous_count > 0`.

Tuần trước thu thập không đầy đủ hoặc không có dữ liệu thì `baseline_available=false`. Không diễn giải “tăng/giảm”; chỉ mô tả mức độ nổi bật hiện tại.

Mỗi topic chọn tối đa 3 bài đại diện theo trust score, priority và thời điểm xuất bản. Một article thuộc nhiều topic được tính trong từng topic nhưng chỉ xuất hiện một lần khi chọn top stories.

**Nghiệm thu:** mỗi trend có article hỗ trợ; thiếu baseline không tạo kết luận tăng/giảm.

### UC27 — Generate Weekly Draft

Admin hoặc scheduler tạo run `GENERATE_REVIEW` cho một tuần.

**Đầu vào được đóng băng trong run:**

- Weekly window.
- Article ID và analysis version.
- Topic/trend snapshot.
- Model và prompt version.
- Danh sách citation ID hợp lệ.

**Cấu trúc draft:**

1. Tổng quan tuần.
2. Tin nổi bật.
3. Nghiên cứu đáng chú ý.
4. Chủ đề và xu hướng.
5. Cơ hội.
6. Rủi ro và giới hạn.
7. Nguồn tham khảo.

Model trả title, excerpt, Markdown và citation ID. Backend ánh xạ ID sang URL thật và tạo `weekly_review_sources`.

**Validation:**

- Không chấp nhận citation ID ngoài snapshot.
- Các thông tin và story được trình bày phải có nguồn hỗ trợ.
- Phân biệt thông tin từ nguồn với phần nhận định của AI.
- Không đủ bằng chứng thì bỏ đoạn hoặc ghi rõ giới hạn.
- Không có article hợp lệ: run thất bại, không tạo nội dung giả.
- Ít hơn ngưỡng cấu hình: tạo review kèm `quality_warning`.

Review mới có `status=REVIEW`. Unique `week_start` hiện có bảo đảm một review chính thức cho mỗi tuần.

**Nghiệm thu:** generation không tạo Post published; mỗi citation mở được article gốc; retry không tạo review thứ hai cùng tuần.

### UC28 — Admin Review \& Edit

Trang chi tiết hiển thị:

- Title, excerpt và Markdown editor.
- Preview.
- Nguồn trích dẫn và summary tương ứng.
- Trend snapshot, cảnh báo chất lượng.
- Model, thời gian sinh và generation run.

**Chỉnh sửa:**

- Chỉ cho sửa review `DRAFT` hoặc `REVIEW`.
- Request gửi revision đang xem.
- Revision đã thay đổi: trả conflict, không ghi đè.
- Lưu thành công tăng revision.

**Regenerate an toàn:**

- Cho phép chọn toàn bài hoặc section theo key cố định.
- Job lưu kết quả mới thành candidate trong payload.
- Admin xem candidate và xác nhận áp dụng.
- Kiểm tra revision lần nữa trước khi thay nội dung.
- Candidate không tự thay bản Admin đang chỉnh.
- Candidate được áp dụng đồng thời với cập nhật nguồn liên quan.

**Chuyển sang Post draft:**

Trong một transaction:

1. Kiểm tra review hợp lệ.
2. Tạo Post `DRAFT`, `AI_WEEKLY`.
3. Gán `author_id` là Admin thực hiện chuyển.
4. Gắn `weekly_review_id`.
5. Chuyển review sang `CONVERTED`, lưu reviewer và thời gian.

Category/tag là đề xuất; Content module kiểm tra và ánh xạ theo contract của Member 1. Nguồn được giữ qua liên kết review và phần tham khảo trong Markdown.

Nếu đã có Post, trả lại Post đó. Bao gồm kiểm tra Post soft-deleted để tránh vi phạm unique constraint hoặc tạo bản sao.

**Nghiệm thu:** regenerate không mất bản chỉnh; bấm chuyển hai lần chỉ có một Post draft; chỉ Content module được publish.

### UC29 — Theo dõi Automation Jobs

Admin xem danh sách run, lọc theo loại, trạng thái và thời gian.

**Thông tin hiển thị:**

- Run ID, loại và trigger.
- `PENDING`, `RUNNING`, `SUCCESS`, `PARTIAL`, `FAILED`.
- Attempt/max attempts.
- Started/finished time.
- Số bài xử lý.
- Kết quả từng nguồn hoặc article.
- Lỗi đã loại bỏ secret.

**Retry:**

- Chỉ retry run `FAILED`/`PARTIAL` khi còn ngân sách attempt.
- Batch collect chỉ chạy lại nguồn thất bại.
- Analysis bỏ qua kết quả đã thành công với cùng đầu vào.
- Generation dùng lại snapshot và idempotency key.
- Không reset attempt để vượt giới hạn.
- Run hết lượt cần Admin tạo lần chạy mới, có liên kết đến run cũ.

Worker timeout/crash phải được reconciliation phát hiện qua lock/heartbeat hết hạn, sau đó retry trong giới hạn hoặc đánh failed. Không để run `RUNNING` vô thời hạn.

**Nghiệm thu:** lỗi nguồn hiển thị riêng; retry không tạo trùng dữ liệu; run bị worker crash được kết thúc hoặc phục hồi.

## 5\. Routes đề xuất

Các routes dùng prefix `/admin`, named routes và middleware Admin hiện có.

| Method | Path | Chức năng |
|---|---|---|
| GET | `/admin/ai-sources` | Danh sách nguồn |
| POST | `/admin/ai-sources` | Tạo nguồn |
| PUT | `/admin/ai-sources/{source}` | Sửa/bật/tắt nguồn |
| GET | `/admin/ai-articles` | Danh sách bài đã thu thập |
| GET | `/admin/ai-articles/{article}` | Bài và analysis |
| POST | `/admin/automation-runs/collect` | Trigger collector |
| POST | `/admin/automation-runs/analyze` | Trigger analysis |
| GET | `/admin/automation-runs` | Danh sách run |
| GET | `/admin/automation-runs/{run}` | Chi tiết tiến độ/lỗi |
| POST | `/admin/automation-runs/{run}/retry` | Retry |
| GET | `/admin/weekly-reviews` | Danh sách review |
| POST | `/admin/weekly-reviews/generate` | Generate theo tuần |
| GET | `/admin/weekly-reviews/{review}` | Editor và nguồn |
| PUT | `/admin/weekly-reviews/{review}` | Lưu chỉnh sửa |
| POST | `/admin/weekly-reviews/{review}/regenerate` | Tạo candidate |
| POST | `/admin/weekly-reviews/{review}/apply-candidate` | Xác nhận thay thế |
| POST | `/admin/weekly-reviews/{review}/convert-to-post` | Tạo Post draft |

Trigger nền trả `202` và run ID cho JSON client. Validation trả `422`; xung đột revision/trạng thái trả `409`. UI Blade có thể redirect đến trang theo dõi tương ứng.

## 6\. Cấu hình vận hành

Các giá trị sau là **mặc định đề xuất**, cần đặt trong cấu hình thay vì hard-code:

| Cấu hình | Mặc định |
|---|---|
| Timezone tuần | `Asia/Ho_Chi_Minh` |
| Collector | Mỗi 6 giờ |
| Generate review | Thứ Hai, 08:00, cho tuần trước |
| Max attempts | 3 |
| HTTP timeout nguồn | 30 giây |
| Minimum articles | 5 |
| Minimum distinct sources | 2 |
| Max selected stories | 10 |
| Representative articles/topic | 3 |

Queue worker và scheduler phải chạy độc lập với HTTP. Các thời hạn HTTP/model, job timeout, lock lease và queue visibility cần được cấu hình nhất quán để tránh hai worker cùng xử lý.

Nội dung nguồn được coi là dữ liệu không tin cậy: prompt không làm theo chỉ dẫn nhúng trong article; Markdown preview cần lọc HTML nguy hiểm.

## 7\. Thứ tự triển khai và kiểm thử

| Giai đoạn | Công việc | Kiểm thử bắt buộc |
|---|---|---|
| 1 | Source management, RSS adapter, collection log | Admin authorization; inactive source; một nguồn lỗi; retry |
| 2 | URL/title normalization, deduplicate | URL tracking; query có ý nghĩa; concurrent insert; title mơ hồ |
| 3 | AI analysis và taxonomy | Valid/invalid JSON; timeout; version/current; duplicate exclusion |
| 4 | Weekly window và trend | Boundary timezone; thiếu ngày; thiếu baseline; representative sources |
| 5 | Draft generation và citations | Thiếu nguồn; citation giả; cùng tuần; retry idempotent |
| 6 | Editor, candidate, Post conversion | Revision conflict; regenerate giữ bản cũ; conversion hai lần; Post soft-delete |
| 7 | Scheduler, retry, crash recovery | Run bị treo; attempt limit; pipeline hoàn chỉnh |

Test dùng HTTP/model fake và fixture, không phụ thuộc live feed hoặc model thật.

**Điều kiện hoàn thành Module D:** Admin cấu hình nguồn, chạy hoặc chờ collector, xem pipeline và lỗi, nhận review có nguồn, chỉnh sửa/regenerate an toàn và chuyển được thành một Post draft. Không có đường xử lý nền nào tự publish Weekly Review.
