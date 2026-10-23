# Plan triển khai worker thu thập nguồn Tech & AI

**Ngày khảo sát:** 17/10/2026  
**Nhánh:** `feat/ai-weekly`  
**Trạng thái:** chuẩn bị triển khai; các checkbox bên dưới chưa được thực hiện.

## 1. Mục tiêu và phạm vi

Vận hành worker lấy tin công nghệ, AI và metadata nghiên cứu từ nguồn có thể truy nguyên, lưu vào `ai_articles`, sẵn sàng cho bước phân tích và tạo AI Weekly.

- Ưu tiên RSS/Atom do nhà xuất bản hoặc tổ chức nguồn cung cấp.
- Lưu title, URL gốc, tác giả, thời gian xuất bản và summary; chỉ lưu nội dung đầy đủ khi chính sách nguồn cho phép.
- Theo dõi riêng từng nguồn, chịu được lỗi mạng, giới hạn request và worker bị dừng.
- Chạy lại an toàn, không tạo article trùng URL, giữ nguồn trích dẫn.
- Kết quả nghiên cứu trên arXiv phải được ghi nhận là preprint; thông báo của doanh nghiệp phải được phân biệt với đánh giá độc lập.

MVP dùng Laravel queue và database hiện có. Crawl HTML tổng quát, tải PDF, nguồn mạng xã hội, model phân tích và xuất bản tự động nằm ngoài đợt triển khai collector này. Luồng downstream tiếp tục kết thúc ở review của Admin và Post draft theo [spec AI Weekly](ai-weekly.md).

## 2. Hiện trạng đã đối chiếu từ source

| Thành phần | Đã có | Phần cần hoàn thiện |
|---|---|---|
| [RunCoordinator](../app/AiWeekly/RunCoordinator.php) | Tạo run COLLECT, chụp source ID, dispatch từng nguồn, retry nguồn lỗi | Chọn nguồn đến hạn, chặn hai run thu thập cùng nguồn đồng thời |
| [CollectSource](../app/Jobs/AiWeekly/CollectSource.php) | Lưu article, thống kê new/duplicate/invalid, tổng hợp SUCCESS/PARTIAL/FAILED | Claim công việc, giới hạn item, phân loại lỗi, cập nhật trạng thái an toàn khi chạy lặp |
| [SourceFetcher](../app/AiWeekly/SourceFetcher.php) | RSS/Atom cơ bản, API có mảng items, timeout, kiểm tra IP, chặn redirect | Fixture từng feed, conditional GET, streaming giới hạn byte, xử lý namespace/link/author |
| [ArticleNormalizer](../app/AiWeekly/ArticleNormalizer.php) | Bỏ tracking cấu hình, giữ query có ý nghĩa, hash URL/title | Kiểm tra link tương đối, URL/tựa đề dài, encoding, ngày không hợp lệ |
| [ai_sources](../app/Models/AiSource.php) | Loại nguồn, URL, adapter_key, trust_score, priority, status, last_fetched_at | Danh mục nguồn thật và trạng thái sức khỏe/due time bền vững |
| [Scheduler](../routes/console.php) | Collect mỗi 6 giờ; analyze riêng; reconcile mỗi 10 phút | Lịch theo nguồn, ngăn overlap suốt vòng đời job nền |
| [Queue config](../config/queue.php) | Database queue; retry_after mặc định 180 giây | Queue riêng cho collection và cấu hình tiến trình vận hành |
| [BlogContentSeeder](../database/seeders/BlogContentSeeder.php) | Có nguồn demo example.com đang ACTIVE | Không đưa nguồn demo vào danh sách nguồn chạy thật |

Hiện chưa có `AiSourceSeeder` chuyên cho nguồn tin thật. API hiện tại chỉ đọc response có `items`; không coi đó là adapter dùng được ngay cho mọi API bên ngoài. `WEBSITE` chưa có collector tự động.

## 3. Danh mục nguồn khởi đầu

Các URL dưới đây đã được GET trực tiếp và parse XML bằng công cụ khảo sát ngày 17/10/2026. Đây là kiểm tra khả dụng của feed, **chưa phải bằng chứng worker PHP hiện tại ingest đúng feed đó**, cũng chưa xác nhận quyền tái sử dụng nội dung.

| Nguồn | Site / Feed chính thức | Vai trò | XML / số entry lúc khảo sát | Lịch đề xuất | Đợt |
|---|---|---|---|---|---|
| MIT News — AI | [Site](https://news.mit.edu/topic/artificial-intelligence2) · [Feed](https://news.mit.edu/rss/topic/artificial-intelligence2) | Tin nghiên cứu, ứng dụng AI từ MIT | RSS; 50 | 6 giờ | A |
| Google Research | [Site](https://research.google/blog/) · [Feed](https://research.google/blog/rss/) | Nghiên cứu, phương pháp và công bố từ Google | RSS; 100 | 6 giờ | A |
| Hugging Face Blog | [Site](https://huggingface.co/blog) · [Feed](https://huggingface.co/blog/feed.xml) | Model, thư viện, công cụ và hệ sinh thái mở | RSS; 880 | 6 giờ | A |
| NVIDIA Technical Blog | [Site](https://developer.nvidia.com/blog/) · [Feed](https://developer.nvidia.com/blog/feed/) | GPU, inference, hệ thống và kỹ thuật AI | Atom; 100 | 6 giờ | A |
| Ars Technica — Technology Lab | [Site](https://arstechnica.com/information-technology/) · [Feed](https://feeds.arstechnica.com/arstechnica/technology-lab) | Bối cảnh công nghệ và báo chí chuyên ngành | RSS; 20 | 2 giờ | B |
| TechCrunch — AI | [Site](https://techcrunch.com/category/artificial-intelligence/) · [Feed](https://techcrunch.com/category/artificial-intelligence/feed/) | Tin AI, sản phẩm và doanh nghiệp | RSS; 20 | 2 giờ | B |
| arXiv — cs.AI, cs.LG, cs.CL | [Site](https://arxiv.org/) · [Feed](https://rss.arxiv.org/atom/cs.AI+cs.LG+cs.CL) | Metadata/abstract preprint AI, ML, NLP | Atom; 0 | 24 giờ | C |

Đợt A triển khai trước để kiểm chứng collector. Đợt B bổ sung góc nhìn báo chí sau khi xác nhận điều kiện sử dụng feed. Đợt C cần fixture Atom có bài thật và kiểm soát số lượng để nghiên cứu không lấn át tin tức.

MIT có [danh mục RSS](https://news.mit.edu/rss); Ars cung cấp [danh mục feed chính thức](https://arstechnica.com/rss-feeds/). arXiv mô tả cách ghép category trong [RSS documentation](https://info.arxiv.org/help/rss.html). Feed arXiv rỗng là một kết quả hợp lệ tại thời điểm khảo sát, không suy ra rằng nguồn hỏng hoặc đã ingest thành công.

### Tiêu chí duyệt nguồn

- Có chủ thể chịu trách nhiệm, domain và URL nguồn rõ ràng; có tác giả/ngày hoặc quy trình biên tập có thể kiểm tra.
- Tách ba nhóm: nghiên cứu/viện trường, thông báo từ doanh nghiệp, báo chí độc lập.
- Bài cộng đồng trên cùng domain vẫn cần được đánh giá theo tác giả; không mặc định mọi bài có cùng mức tin cậy.
- `trust_score` 1–10 là đánh giá biên tập nội bộ; `priority` là ưu tiên xử lý. Hai số này không phải xác suất thông tin đúng.
- Pilot: review thủ công 10 item đầu mỗi nguồn; xác nhận đúng chủ đề và nguồn gốc rồi mới bật ACTIVE.
- RSS công khai không tự động cấp quyền lưu/repost toàn văn. Với Ars và nguồn báo chí, xác nhận chính sách sử dụng trước khi bật; không vượt paywall hoặc dùng subscriber feed.

## 4. Thiết kế tích hợp

```text
Scheduler / Admin
  -> chọn nguồn ACTIVE đã đến hạn
  -> claim source + tạo automation_runs COLLECT
  -> dispatch CollectSource(run_id, source_id, attempt)
  -> kiểm tra lại trạng thái/claim + giới hạn domain
  -> fetch RSS/Atom -> chuẩn hóa item -> kiểm tra -> deduplicate -> lưu
  -> ghi kết quả source -> tổng hợp run -> giải phóng claim
  -> article đủ nội dung được pipeline analyze hiện có tiếp nhận
```

Giữ `CollectSource`, `SourceFetcher`, `ArticleNormalizer` làm điểm tích hợp. Chỉ tách parser RSS/Atom thành lớp riêng khi fixture cho thấy cần thiết; không tạo framework crawler hay thêm dependency trong MVP.

### Danh mục và trạng thái nguồn

- Đề xuất thêm `config/ai_weekly_sources.php` chứa seed catalog và chính sách theo nguồn. Mỗi mục có key ổn định, site_url, feed_url, nhóm nội dung, poll interval và host hợp lệ.
- `ai_sources` tiếp tục là dữ liệu runtime do Admin quản lý. Catalog chỉ cung cấp mặc định khi tạo mới; seeder chạy lại không bật lại nguồn Admin đã tắt hoặc ghi đè URL/score Admin đã chỉnh.
- Cần identity ổn định cho đồng bộ catalog: đề xuất `catalog_key` nullable, unique trong `ai_sources`; không dùng name hiển thị làm khóa đồng bộ.
- Đề xuất bổ sung `next_fetch_at`, `last_attempted_at`, `consecutive_failures`, `last_error_code`, `etag`, `last_modified`. Giữ nguyên nghĩa `last_fetched_at`: lần fetch/parse thành công gần nhất.
- Lưu lease theo source bằng `collection_run_id` và `lease_expires_at` hoặc cơ chế claim tương đương; claim phải atomic và sống suốt job, không chỉ trong thời gian scheduler dispatch.
- Mọi schema mới chỉ thực hiện ở bước triển khai sau khi kiểm tra schema thực tế. Bản plan này không tạo migration.

### Contract item

Parser tiếp tục trả các key mà `CollectSource` đang dùng:

```json
{
  "title": "Tên bài từ nguồn",
  "url": "https://publisher.example/article",
  "summary": "Summary hoặc abstract được nguồn cung cấp",
  "content": null,
  "author": null,
  "published_at": "2026-10-17T08:00:00Z"
}
```

- RSS: hỗ trợ pubDate, description, content:encoded, dc:creator, guid khi guid thực sự là permalink.
- Atom: namespace mặc định, link rel=alternate kiểu HTML, author/name; published ưu tiên trước updated.
- Chuẩn hóa URL tương đối theo base URL của feed; chỉ nhận HTTP(S); không chọn link self/enclosure làm URL bài.
- Thiếu title hoặc URL hợp lệ: tăng invalid và tiếp tục. Lỗi DB/hạ tầng phải được báo riêng, không gộp tất cả thành item invalid.
- Kiểm tra độ dài theo schema; không cắt URL thành một URL khác. Thiếu ngày giữ null; ngày có giá trị nhưng parse lỗi phải ghi mã lỗi rõ ràng.
- Feed hợp lệ có 0 entry là SUCCESS; HTML challenge hoặc XML không đúng cấu trúc feed là lỗi, không được coi là feed rỗng.

## 5. Chính sách thu thập

### Lần đầu và lượt cập nhật

- Lần đầu chỉ nhập bài trong 7 ngày gần nhất, tối đa 100 bài/nguồn/lượt. Item không có ngày thuộc bootstrap quota riêng tối đa 20 để tránh nhập toàn bộ kho cũ.
- Giới hạn bootstrap là chính sách đề xuất, đặt trong config; không áp dụng nhầm cho recovery sau downtime.
- Parse/filter ngày trước khi áp quota bài mới. Hugging Face đang có 880 entry: không giả định mọi feed chỉ có 20–100 bài hoặc luôn được sắp mới nhất trước.
- Khi chạy lại, quét metadata trong giới hạn byte/entry cấu hình, bỏ URL đã có và chọn item mới theo thời gian ổn định.
- MVP giữ snapshot article đầu tiên khi URL đã tồn tại. Cập nhật nội dung, article version và invalidation analysis là giai đoạn sau.
- RSS thường chỉ giữ một cửa sổ gần nhất: không hứa backfill đủ 7 ngày nếu feed không còn bài; ghi rõ khoảng dữ liệu thu được. Downtime vượt cửa sổ feed tạo cảnh báo coverage.

### HTTP, giới hạn tải và an toàn

- Connection timeout 5 giây, response timeout 30 giây, body tối đa 5 MB; enforce khi đọc stream, không chỉ sau khi tải toàn bộ body.
- User-Agent nhận diện Apollo và địa chỉ liên hệ lấy từ cấu hình; gửi Accept phù hợp RSS/Atom.
- Lưu ETag/Last-Modified theo nguồn, gửi conditional GET. HTTP 304 tính là kiểm tra thành công, 0 bài mới; không xóa metadata/article cũ.
- Kiểm tra schema/host/IP public, giữ DNS pinning; không truy cập link bài để tải full text trong MVP.
- Tiếp tục chặn redirect tự động. HTTP 3xx là lỗi endpoint cần cập nhật feed URL đã kiểm tra; không âm thầm đi theo redirect sang host khác.
- Parser không mở external entity/DTD hoặc thực hiện request phụ khi đọc XML.
- Domain throttle dùng chung giữa các worker. Đối với arXiv, tối đa một request mỗi 3 giây và một connection tại một thời điểm trên toàn hệ thống theo [điều kiện API](https://info.arxiv.org/help/api/tou.html).
- Domain khác khởi đầu một request mỗi 5 giây, concurrency 1/domain; đây là mặc định nội bộ, điều kiện nguồn nghiêm ngặt hơn phải được ưu tiên.

### Chống trùng và chất lượng

- Dùng canonical_url_hash unique hiện có làm chốt chống insert trùng khi chạy đồng thời.
- Bỏ tracking theo danh sách config, giữ query nghiệp vụ. Không tự gộp HTTP/HTTPS, đổi path hoặc bỏ tham số tùy tiện.
- Không gộp hai bài khác nguồn chỉ vì title gần giống; giữ attribution và dùng nhóm sự kiện ở bước phân tích sau.
- Phát hiện bài trùng cùng nguồn qua fingerprint và bằng chứng nội dung như logic hiện tại; bổ sung test cho trường hợp thiếu ngày.
- Kiểm tra topic tech/AI qua scope của feed và taxonomy. Giai đoạn đầu giữ bài đã thu thập để kiểm tra; lọc relevance cho downstream riêng, không tự xóa bài bằng một từ khóa chung như “AI”.

## 6. Lịch chạy, retry và vận hành

| Hạng mục | Mặc định đề xuất |
|---|---|
| Scheduler điều phối | Mỗi 5 phút, chỉ dispatch nguồn đến hạn |
| News feed | Mỗi 2 giờ |
| Blog nghiên cứu/kỹ thuật | Mỗi 6 giờ |
| arXiv | Mỗi 24 giờ; cache kết quả ngày; không suy luận múi giờ nguồn từ timezone ứng dụng |
| Jitter | 0–5 phút giữa các nguồn |
| Collection queue | ai-collect, tách khỏi tác vụ model |
| Concurrent collector ban đầu | 2 tiến trình, vẫn phải tuân thủ throttle domain chung |
| Max attempts | 3 lần tổng cộng, tính cả lần đầu |
| Backoff | Sau lỗi lần 1: 5 phút; sau lỗi lần 2: 30 phút; ưu tiên Retry-After dài hơn |
| Reconcile | Mỗi 10 phút; kiểm tra lease/job còn chạy trước khi phục hồi |

- Retry network/timeout, 429 và 5xx. 401/403/404, XML hỏng, sai host hoặc feed quá lớn cần Admin xử lý; không retry liên tục.
- Retry nguồn lỗi không tạo lại công việc cho nguồn đã SUCCESS. Worker delivery lặp phải kiểm tra kết quả/claim của source trước khi fetch.
- Rate-limit release không tăng domain attempt; attempt chỉ tăng khi thực sự thực hiện lại request sau lỗi. Tránh phối hợp retry Laravel và retry domain thành hai vòng tăng lượt độc lập.
- `retry_delay_minutes` hiện có chưa đủ cho lịch retry đa mức; bổ sung cấu hình backoff và due timestamp khi triển khai.
- Scheduler phải có cache dùng chung cho onOneServer; khóa scheduler không thay thế source lease vì callback kết thúc trước job nền.
- Không dựa vào “collect xong lúc 00:00, analyze lúc 00:30” để bảo đảm thứ tự. Analysis có thể chạy định kỳ để vét bài mới; bước generate cần kiểm tra coverage/completion trước khi kết luận tuần đã đủ dữ liệu.

Sau khi code đã route job vào queue riêng, chạy hai tiến trình collector dưới process manager; lệnh mẫu:

```bash
php artisan queue:work database --queue=ai-collect --sleep=3 --tries=1 --timeout=60 --max-time=3600 --no-interaction
php artisan schedule:work --no-interaction
```

`--queue=ai-collect` chỉ có hiệu lực khi dispatch cũng dùng queue này. Job hiện tại chưa được cấu hình queue riêng. Runtime hiện gán timeout collector bằng HTTP timeout + 15 giây, nên cần cộng cả parse/DB budget trước khi chọn lease và queue retry_after. retry_after phải lớn hơn timeout thực tế; deadline của process manager cần đủ để job đang chạy kết thúc.

Production dùng process manager cho worker và cron gọi `schedule:run` mỗi phút, chọn một cơ chế scheduler. Giữ worker default/model đang có; thu thập feed không cần API key của model.

## 7. Quan sát và điều kiện nghiệm thu

Mỗi source result trong payload cần có: source_id, attempt, HTTP status, error_code, retryable, retry_after_at, duration_ms, response_bytes, parsed, new, duplicate, invalid, skipped_old, truncated và next_fetch_at. Bổ sung trường theo contract payload hiện có, không đưa secret hoặc toàn bộ body vào log.

- Admin nhìn được lần thành công gần nhất, lỗi gần nhất, số bài mới và thời điểm thử lại.
- Nguồn lỗi 3 lần liên tiếp hoặc không fetch thành công quá 2 chu kỳ cần cảnh báo. Không tự INACTIVE chỉ vì feed ít bài.
- Tách “feed rỗng”, “không có bài mới”, “chạm quota” và “fetch thất bại”.
- Không yêu cầu một số lượng bài cố định cho mọi nguồn; cần phân biệt ít bài tự nhiên với thiếu coverage.

## 8. Các bước triển khai

- [ ] **P1 — Catalog:** thêm catalog + AiSourceSeeder idempotent; nguồn khởi tạo INACTIVE; loại demo khỏi nguồn vận hành; test seeder không ghi đè Admin.
- [ ] **P2 — Feed compatibility:** fixture từ từng nguồn đợt A, đủ RSS/Atom và edge case; hoàn thiện parser, conditional GET, streaming body limit.
- [ ] **P3 — Worker scheduling:** schema due/health/lease, chọn nguồn đến hạn, atomic claim, throttle domain, queue riêng và retry có delay.
- [ ] **P4 — Data quality:** bootstrap/lookback/quota, validation item, phân biệt lỗi DB với item lỗi, cập nhật counters và cảnh báo coverage.
- [ ] **P5 — Pilot:** bật lần lượt nguồn đợt A, chạy 72 giờ; xem tay item và log; bật đợt B sau bước kiểm tra điều kiện nguồn; arXiv sau khi có fixture không rỗng.
- [ ] **P6 — Vận hành:** cấu hình process manager, scheduler, Make targets tái lập setup/worker, smoke test và hướng dẫn khôi phục.

### Ma trận kiểm thử

| Nhóm | Kết quả cần chứng minh |
|---|---|
| RSS/Atom | Title/link/author/date đúng; namespace và nhiều Atom link không làm chọn sai URL |
| HTTP | 200/304/rỗng hợp lệ; 429 Retry-After giây và HTTP-date; 5xx/timeout; HTML giả feed; body vượt giới hạn |
| Dữ liệu | Ngày thiếu/sai; UTF-8; URL tương đối; title/URL quá dài; summary-only; một item lỗi không mất item tốt |
| Chống trùng | Chạy lại feed không tăng article; hai job đồng thời cùng URL chỉ một row; bài khác nguồn giữ attribution |
| Lịch/claim | Chỉ nguồn ACTIVE và đến hạn được chọn; hai scheduler không claim cùng nguồn; delivery lặp không request lại nguồn đã xong |
| Retry/crash | Backoff có hiệu lực; giữ Retry-After; chỉ retry source lỗi; không vượt 3 attempt; stale lease được phục hồi |
| Quy mô | Feed 880 entry không nhập kho cũ; quota không cắt bỏ bài mới do thứ tự feed; báo truncated khi không xử lý hết |
| Regression | API source/collector, analysis và AI Weekly hiện có tiếp tục pass |

Test tự động dùng HTTP fake và fixture đã lưu; không phụ thuộc feed live. Live smoke là bước riêng dùng database không phải production, có ghi lại thời điểm và kết quả.

**Hoàn thành khi:** nguồn đợt A đã qua duyệt, ingest bằng worker PHP thật; chạy lặp không trùng bài; một nguồn lỗi không chặn nguồn khác; retry/lease hoạt động; Admin truy được URL gốc và biết coverage. Có bằng chứng pilot 72 giờ, không chỉ build hoặc feature test.

## 9. Hạn chế và tài liệu tham chiếu

- Danh sách là đề xuất biên tập ban đầu; không đại diện mọi nguồn tin tech/AI. Nguồn tiếng Việt là đợt bổ sung sau khi kiểm tra feed chính thức và điều kiện sử dụng.
- RSS không bảo đảm toàn văn, lịch sử đầy đủ hoặc tính đúng đắn của nội dung. Kết quả từ vendor/preprint cần ngữ cảnh khi đưa vào Weekly Review.
- [Spec AI Weekly hiện có](ai-weekly.md).
- [MIT RSS](https://news.mit.edu/rss), [Google Research RSS](https://research.google/blog/rss/), [Hugging Face RSS](https://huggingface.co/blog/feed.xml), [NVIDIA Atom](https://developer.nvidia.com/blog/feed/).
- [Ars feed directory](https://arstechnica.com/rss-feeds/), [TechCrunch AI RSS](https://techcrunch.com/category/artificial-intelligence/feed/).
- [arXiv RSS/Atom](https://info.arxiv.org/help/rss.html), [arXiv API terms và rate limit](https://info.arxiv.org/help/api/tou.html).
