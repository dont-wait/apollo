# Spec: Từ điển từ độc hại và kiểm soát nội dung

**Issue:** [#31](https://github.com/dont-wait/apollo/issues/31) — Dic từ độc hại, tránh người dùng thêm hoặc dùng khi tạo bài viết, tags, category,...<br>
**Ngày:** 24/10/2026<br>
**Trạng thái:** sẵn sàng triển khai<br>
**Phạm vi:** backend Laravel, admin UI, validation, kiểm tra nội dung AI<br>
**English version:** [toxic-dic.en.md](toxic-dic.en.md)

---

## 1. Mục tiêu và phạm vi

### 1.1. Mục tiêu

- Cung cấp một **từ điển từ độc hại** do Admin quản lý, có thể bật/tắt từng mục.
- Chặn nội dung chứa từ độc hại **trước khi ghi vào database** ở các luồng:
  - Tạo/cập nhật bài viết.
  - Tạo/cập nhật tag.
  - Tạo/cập nhật category.
  - Tạo/cập nhật bình luận.
  - Đăng ký tài khoản và Admin tạo/cập nhật người dùng (tên hiển thị).
- Chặn cả nội dung do AI Weekly sinh ra khi chuyển thành `Post` draft.
- Không làm lộ từ khóa đã khớp trong thông báo lỗi trả về người dùng.
- Sau khi submit thất bại, **highlight vàng** các vị trí vi phạm trên editor, preview bài viết và màn hình review (chỉ Admin).
- Có công cụ quét nội dung cũ để Admin chủ động rà soát.

### 1.2. Trong phạm vi

- Bảng `toxic_words`, model, factory, seeder, config baseline.
- Trang Admin quản lý từ điển.
- Service chuẩn hóa và so khớp từ khóa.
- Custom validation rule dùng chung.
- Tích hợp vào các `FormRequest` hiện có.
- Kiểm tra ở `DraftPostCreator`.
- Command quét nội dung cũ.
- Cache và test.

### 1.3. Ngoài phạm vi

- Không tự động sửa, xóa hoặc ẩn nội dung cũ.
- Không gọi dịch vụ AI/moderation bên thứ ba.
- Không xây dựng hệ thống chống lách nâng cao như homoglyph, OCR ảnh, phân tích ngữ nghĩa.
- Không áp dụng cho tên file, alt text, media, email, mật khẩu.
- Không thay đổi quy trình duyệt bài hiện có.
- Không thêm package/dependency mới.

---

## 2. Hiện trạng đã đối chiếu từ source

| Luồng | File hiện tại | Hiện trạng | Khoảng trống |
|---|---|---|---|
| Bài viết | `app/Http/Requests/Admin/StoreArticleRequest.php`, `UpdateArticleRequest.php` | Validate title, slug, excerpt, markdown, tag mới | Chưa kiểm tra từ độc hại |
| Tag | `app/Http/Requests/Admin/StoreTagRequest.php`, `UpdateTagRequest.php` | Validate name, slug, color | Chưa kiểm tra |
| Category | `app/Http/Requests/Admin/StoreCategoryRequest.php`, `UpdateCategoryRequest.php` | Validate name, slug, description | Chưa kiểm tra |
| Bình luận | `app/Http/Requests/StoreCommentRequest.php`, `UpdateCommentRequest.php` | Validate content, parent, version | Chưa kiểm tra |
| Người dùng | `app/Http/Requests/RegisterRequest.php`, `Admin/StoreUserRequest.php`, `Admin/UpdateUserRequest.php` | Validate name, email, role | Chưa kiểm tra tên hiển thị |
| AI Weekly | `app/AiWeekly/DraftPostCreator.php` | Tạo `Post` draft từ `WeeklyReview` | Chưa kiểm tra nội dung AI |
| Admin UI | `resources/views/admin/categories/index.blade.php` | Có quản lý category/tag | Chưa có màn hình từ điển |
| Điều hướng admin | `resources/views/layouts/admin.blade.php` | Có nhóm Taxonomy | Chưa có mục từ điển |

Apollo chưa có bảng, service hay validation nào liên quan đến từ độc hại.

---

## 3. Chính sách kiểm soát

### 3.1. Định nghĩa

**Từ độc hại** là một chuỗi (từ hoặc cụm từ) do Admin cấu hình, dùng để chặn nội dung xúc phạm, quấy rối, thù địch hoặc không phù hợp với cộng đồng.

Mỗi mục trong từ điển có:

- `term`: chuỗi hiển thị cho Admin.
- `scopes`: các vị trí áp dụng.
- `match_mode`: cách so khớp.
- `fold_diacritics`: có bỏ dấu tiếng Việt khi so khớp hay không.
- `is_active`: bật/tắt.

### 3.2. Hành vi mặc định — BLOCK

MVP chỉ có một hành vi: **chặn request và trả lỗi validation**. Không tự động thay thế ký tự, không lưu nội dung đã bị che, không đưa vào hàng chờ duyệt.

- Request HTTP công khai: trả `422` kèm lỗi ở đúng field; không trả vị trí hoặc từ khóa.
- Form admin: redirect back kèm `errors`, giữ input cũ và `moderation` để highlight vàng các vị trí vi phạm.
- AJAX admin (review): trả `422`/`409` kèm `moderation`; JavaScript highlight ngay, không reload mất trạng thái form.
- Nội dung AI: ném `ToxicContentException` (kế thừa `DomainException`) có thông báo rõ và payload highlight để Admin sửa review trước khi chuyển.

Lý do chọn BLOCK thay vì REVIEW: issue yêu cầu **ngăn** việc thêm/dùng từ độc hại; cơ chế duyệt bài hiện có vẫn giữ nguyên.

### 3.3. Nguyên tắc tránh chặn nhầm

Từ độc hại tiếng Việt rất dễ chặn nhầm nếu bỏ dấu toàn bộ. Ví dụ `cac` và `các` là hai chuỗi khác nghĩa. Vì vậy:

- Mặc định **giữ nguyên dấu** khi so khớp.
- Chỉ bỏ dấu khi Admin bật `fold_diacritics` cho từng mục và đã kiểm tra false positive.
- Mặc định so khớp **theo ranh giới từ**, không so khớp chuỗi con.
- Chế độ `LOOSE` (bỏ dấu câu, khoảng trắng, ký tự leet) chỉ dùng cho từ/cụm từ dài từ **4 ký tự** trở lên.
- Không trả từ khóa khớp về client.
- Có kill switch toàn cục trong config để tắt kiểm tra khi xảy ra sự cố false positive diện rộng.

### 3.4. Scope áp dụng

| Scope | Field được kiểm tra | Luồng |
|---|---|---|
| `post` | `title`, `slug`, `excerpt`, `markdown_content` | Admin tạo/sửa bài; AI Weekly draft |
| `tag` | `name`, `slug` | Admin tạo/sửa tag; tag mới trong form bài viết |
| `category` | `name`, `slug`, `description` | Admin tạo/sửa category |
| `comment` | `content` | Thành viên tạo/sửa bình luận |
| `user_name` | `name` | Đăng ký, Admin tạo/sửa user |

Một mục từ điển có thể áp dụng cho một hoặc nhiều scope. Mặc định khi tạo mới trên UI: chọn tất cả scope.

### 3.5. Hỗ trợ tiếng Anh và tiếng Việt

- Từ điển nhận **cả term tiếng Anh và tiếng Việt**, không có field ngôn ngữ.
- Term tiếng Việt mặc định giữ nguyên dấu.
- Term tiếng Anh so khớp không phân biệt hoa/thường.
- Cụm từ trộn hai ngôn ngữ được lưu như chuỗi Unicode bình thường.
- Chuẩn hóa và ranh giới từ dùng Unicode, không dùng regex chỉ hỗ trợ tiếng Anh.
- Bỏ dấu vẫn là tùy chọn theo từng term vì không an toàn cho tiếng Việt.
- Thông báo lỗi hiển thị theo locale của ứng dụng (`APP_LOCALE`); nội dung tiếng Việt/tiếng Anh trong spec chỉ là ví dụ. Nếu triển khai đa ngôn ngữ, đặt message trong `lang/en/moderation.php` và `lang/vi/moderation.php`, fallback `en`.

### 3.6. Override hợp lệ

- Admin có thể **tắt** một mục từ điển nếu cần cho nội dung đặc biệt (ví dụ bài phân tích về ngôn từ).
- Không có bypass theo role trong code. Mọi request đều đi qua cùng validation.
- Kill switch: `TOXIC_WORDS_ENABLED=false` trong config/env.

---

## 4. Kiến trúc

```text
HTTP request / AI draft
    -> FormRequest / DraftPostCreator
    -> ToxicWordFilter::check(value, scope)
    -> ToxicWordMatcher (normalize + compiled patterns, cached)
    -> ToxicWordRepository (active terms, cached)
    -> có match?
         ├── có  -> ValidationException / DomainException
         └── không -> tiếp tục lưu như hiện tại
```

### 4.1. Thành phần

| Thành phần | Trách nhiệm |
|---|---|
| `ToxicWord` model | Lưu từ điển, cast `scopes` |
| `ToxicWordRepository` | Đọc active terms theo scope, cache, fallback |
| `ToxicWordNormalizer` | Chuẩn hóa Unicode, chữ hoa/thường, ký tự vô hình, leet |
| `ToxicWordMatcher` | Biên dịch pattern và so khớp văn bản |
| `ToxicWordFilter` | API cấp cao: `check()`, `matches()`, `assertClean()` |
| `CleanContent` rule | Adapter cho Laravel Validation |
| `AdminToxicWordController` | CRUD từ điển |
| `ScanToxicWords` command | Quét nội dung cũ, read-only |

Không đặt logic so khớp trong controller, model event hoặc Blade.

---

## 5. Dữ liệu và schema

### 5.1. Bảng `toxic_words`

```text
id                 bigint unsigned, PK
term               varchar(100)        -- chuỗi gốc Admin nhập
normalized_term    varchar(100)        -- dạng chuẩn hóa, unique
scopes             json                -- ["post","tag","category","comment","user_name"]
match_mode         varchar(10)         -- WORD | LOOSE, default WORD
fold_diacritics    boolean             -- default false
is_active          boolean             -- default true
note               varchar(255), null  -- ghi chú nội bộ
created_by         bigint unsigned, null FK users, nullOnDelete
updated_by         bigint unsigned, null FK users, nullOnDelete
created_at         timestamp
updated_at         timestamp
```

Index:

- `unique(normalized_term)`.
- `index(is_active)`.
- Không index JSON `scopes`; lọc scope trong bộ nhớ sau khi cache.

Ràng buộc:

- `normalized_term` được sinh tự động, không nhận từ request.
- `term` tối thiểu 2, tối đa 100 ký tự sau khi trim.
- `scopes` tối thiểu 1 phần tử, mỗi phần tử thuộc danh sách scope.
- `match_mode` chỉ nhận `WORD` hoặc `LOOSE`.
- `LOOSE` chỉ hợp lệ khi `mb_strlen(normalized_term) >= 4`.
- Không cho trùng `normalized_term`; nếu Admin nhập trùng, trả lỗi ở field `term`.

### 5.2. Model

`App\Models\ToxicWord`:

- `$fillable`: `term`, `normalized_term`, `scopes`, `match_mode`, `fold_diacritics`, `is_active`, `note`, `created_by`, `updated_by`.
- Casts: `scopes => array`, `fold_diacritics => boolean`, `is_active => boolean`.
- Boot events `saved`/`deleted`: xóa cache từ điển.
- Scope query `active()`.

### 5.3. Config

`config/toxic_words.php`:

```php
return [
    'enabled' => env('TOXIC_WORDS_ENABLED', true),
    'cache_key' => 'toxic-words:active:v1',
    'cache_ttl' => 600,
    'max_terms' => 5000,
    'scopes' => ['post', 'tag', 'category', 'comment', 'user_name'],
    'baseline' => [
        // Danh sách seed khởi tạo; Admin có thể sửa/tắt từng mục trong DB.
    ],
];
```

- `baseline` chứa danh sách seed khởi tạo và cũng là fallback khi cache/DB lỗi.
- Sau khi seed, Admin quản lý danh sách thực tế trong DB.
- Không hard-code danh sách từ độc hại trong Blade, controller hoặc file JS.

### 5.4. Seeder

`database/seeders/ToxicWordSeeder.php`:

- Đọc `config('toxic_words.baseline')`.
- `updateOrCreate` theo `normalized_term`.
- Không bật lại mục Admin đã tắt; không ghi đè `scopes`/`match_mode` Admin đã sửa.
- Chỉ thêm mục còn thiếu.

---

## 6. Chuẩn hóa và so khớp

### 6.1. Chuẩn hóa văn bản

Thứ tự xử lý:

1. Chuyển về Unicode NFC nếu extension `intl` có sẵn; nếu không, giữ nguyên.
2. Chuyển lowercase bằng `mb_strtolower`.
3. Xóa ký tự vô hình: zero-width space, zero-width joiner, BOM, bidi control.
4. Thay mọi khoảng trắng liên tiếp bằng một space.
5. Trim.
6. Chỉ với `LOOSE`: thay các ký tự leet phổ biến (`0→o`, `1→i`, `3→e`, `4→a`, `5→s`, `7→t`, `@→a`, `$→s`), sau đó tạo thêm dạng compact bằng cách bỏ mọi ký tự không phải chữ/số.
7. Chỉ với mục `fold_diacritics = true`: bỏ dấu bằng `Str::ascii`.

Lưu ý: bước 6 và 7 áp dụng cho **cả term và text**. Không bỏ dấu text nếu mục từ điển không bật `fold_diacritics`.

### 6.2. Chế độ `WORD` (mặc định)

- So khớp nguyên từ/cụm từ trên văn bản đã chuẩn hóa.
- Ranh giới từ dùng Unicode:

```regex
(?<![\p{L}\p{N}])TERM(?![\p{L}\p{N}])
```

- Không phân biệt hoa/thường.
- Dấu câu, gạch nối, khoảng trắng giữa các từ không bị bỏ qua.
- Phù hợp cho từ ngắn và từ tiếng Việt có dấu.

### 6.3. Chế độ `LOOSE`

Dùng để bắt các dạng lách đơn giản:

- `bad-word` → `badword`.
- `b a d w o r d` → `badword`.
- `b4dw0rd` → `badword`.
- `bad---word` → `badword`.
- Ký tự lặp 3 lần trở lên được thu gọn: `baaadword` → `badword`.

Quy tắc an toàn:

- Chỉ áp dụng cho term có độ dài chuẩn hóa ≥ 4.
- So khớp trên dạng compact.
- Vẫn giữ kiểm tra ranh giới trên văn bản gốc nếu có thể; nếu không, đánh dấu đây là chế độ có nguy cơ false positive và Admin phải xác nhận khi bật.
- Không tự động bật `LOOSE` cho mọi term.

### 6.4. Ví dụ kiểm thử với term trung tính `badword`

| Input | WORD | LOOSE | Ghi chú |
|---|---|---|---|
| `badword` | match | match | cơ bản |
| `bad word` | không | không | khoảng trắng không bị bỏ ở WORD |
| `bad-word` | không | match | LOOSE bỏ dấu nối |
| `b a d w o r d` | không | match | LOOSE bỏ khoảng trắng |
| `b4dw0rd` | không | match | leet |
| `badwords` | không | match | LOOSE là substring |
| `notbadwordhere` | không | match | LOOSE là substring, cần cân nhắc khi thêm term |
| `BADWORD` | match | match | không phân biệt hoa/thường |

### 6.5. Giới hạn đã biết

- Không xử lý homoglyph Unicode (ví dụ ký tự Kirin, chữ có dấu kết hợp khác).
- Không xử lý ảnh, PDF, nội dung nhúng ngoài.
- Không phân tích ngữ nghĩa; một từ trong câu trích dẫn vẫn bị chặn.
- Nếu nội dung hợp lệ bị chặn nhầm, Admin tắt mục tương ứng hoặc dùng kill switch.

---

## 7. Spec theo use case

### UC1 — Admin quản lý từ điển

**Route**

| Method | URI | Name |
|---|---|---|
| GET | `/admin/toxic-words` | `admin.toxic-words.index` |
| POST | `/admin/toxic-words` | `admin.toxic-words.store` |
| PUT | `/admin/toxic-words/{toxicWord}` | `admin.toxic-words.update` |
| PATCH | `/admin/toxic-words/{toxicWord}/status` | `admin.toxic-words.update-status` |
| DELETE | `/admin/toxic-words/{toxicWord}` | `admin.toxic-words.destroy` |

Tất cả nằm trong group `admin` hiện có, dùng middleware `auth`, `auth.session`, `EnsureUserIsAdmin`.

**Trang index**

- Bảng: term (có thể che một phần), scopes, mode, trạng thái, người tạo, cập nhật gần nhất, hành động.
- Tìm kiếm theo `term`.
- Lọc theo scope và trạng thái.
- Form thêm/sửa:
  - `term` (bắt buộc).
  - `scopes` (checkbox, bắt buộc ít nhất 1).
  - `match_mode` (`WORD`/`LOOSE`).
  - `fold_diacritics` (checkbox, mặc định tắt).
  - `note` (không bắt buộc).
  - `is_active` (mặc định bật).
- Nút bật/tắt nhanh.
- Xóa cứng được phép; hiển thị confirm modal hiện có.

**Quy tắc**

- Chỉ Admin truy cập.
- `term` được trim trước khi validate.
- `normalized_term` sinh ở server; nếu trùng trả lỗi `Từ này đã có trong từ điển.`
- Không cho lưu `LOOSE` với term < 4 ký tự.
- Sau mỗi thay đổi, cache bị xóa ngay.
- Không hiển thị toàn bộ term nhạy cảm trong log.

**Nghiệm thu**

- Tạo term mới thì request tiếp theo bị chặn đúng scope.
- Tắt term thì nội dung tương ứng được lưu.
- Sửa/xóa term làm cache cập nhật ngay, không cần restart.
- User thường nhận `403` khi truy cập route admin.

### UC2 — Bài viết

**Áp dụng tại**

- `StoreArticleRequest`
- `UpdateArticleRequest`

**Field**

- `title`: scope `post`.
- `slug`: scope `post`.
- `excerpt`: scope `post`.
- `markdown_content`: scope `post`.
- `new_tags.*`: scope `tag`.
- `tags.*` (ID tag có sẵn): không kiểm tra lại trong request này; nội dung cũ được xử lý ở UC7.

**Quy tắc**

- Validation chạy trước khi vào `DB::transaction`.
- Nếu một field có match, trả lỗi ở field đó, không lưu bất kỳ phần nào.
- Thumbnail và các field khác không bị ảnh hưởng.
- Khi update, nội dung cũ có từ độc hại vẫn bị chặn cho đến khi Admin sửa; đây là hành vi có chủ đích.
- Tag mới trong form bài viết được kiểm tra trước khi `resolveTagIds` được gọi.

**Thông báo**

- `post.title`: `Tiêu đề chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.`
- `post.excerpt`: `Tóm tắt chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.`
- `post.markdown_content`: `Nội dung bài viết chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.`
- `post.slug`: `Slug chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.`
- `tag`: `Tên tag chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.`

**Nghiệm thu**

- Tạo bài với title chứa term bị chặn: không có `Post` mới, form giữ input, lỗi hiện dưới title.
- Tạo bài sạch: lưu bình thường.
- Bài có tag mới chứa term: không tạo bài, không tạo tag.
- Cập nhật bài sạch: lưu bình thường.

### UC3 — Tag và Category

**Tag**

- `StoreTagRequest` và `UpdateTagRequest`.
- Kiểm tra `name` và `slug` với scope `tag`.
- Kiểm tra trước logic tái sử dụng tag trong `TagController::store`; không được trả `reused=true` cho tag độc hại.

**Category**

- `StoreCategoryRequest` và `UpdateCategoryRequest`.
- Kiểm tra `name`, `slug`, `description` với scope `category`.
- Kiểm tra trước khi kiểm tra vòng lặp parent và trước khi ghi.

**Nghiệm thu**

- Không tạo được tag/category có term trong bất kỳ field nào.
- Tag/category sạch vẫn hoạt động như cũ.
- Không thay đổi response JSON hiện có ngoài lỗi `422` khi vi phạm.

### UC4 — Bình luận

**Áp dụng tại**

- `StoreCommentRequest`
- `UpdateCommentRequest`

**Field**

- `content`: scope `comment`.

**Quy tắc**

- Chạy sau khi trim content, trước khi vào transaction/idempotency.
- Request vi phạm trả `422`, không tạo comment, không tăng version.
- Update vi phạm trả `422`, không thay đổi comment.
- Giữ nguyên throttling và idempotency hiện có.
- Comment đã tồn tại từ trước không bị tự động ẩn; UC7 hỗ trợ rà soát.

**Nghiệm thu**

- Comment chứa term bị chặn với `422` và lỗi ở `content`.
- Comment sạch vẫn tạo được.
- Update comment sạch vẫn giữ version và optimistic lock như cũ.
- Hai request đồng thời với cùng idempotency key không tạo comment nếu cả hai đều vi phạm.

### UC5 — Tên người dùng

**Áp dụng tại**

- `RegisterRequest`: `name`.
- `Admin\StoreUserRequest`: `name`.
- `Admin\UpdateUserRequest`: `name`.

**Quy tắc**

- Scope `user_name`.
- Không kiểm tra email hay mật khẩu.
- Nếu vi phạm, trả lỗi ở `name`.

**Nghiệm thu**

- Đăng ký với tên chứa term bị chặn: không tạo user.
- Admin tạo/sửa user với tên hợp lệ: hoạt động như cũ.
- User hiện có có tên vi phạm vẫn đăng nhập được; chỉ chặn khi cập nhật tên.

### UC6 — AI Weekly draft

**Áp dụng tại**

- `App\AiWeekly\DraftPostCreator::create()`.

**Quy tắc**

- Trước khi `Post::create`, kiểm tra `title`, `excerpt`, `markdown_content` với scope `post`.
- Nếu vi phạm, ném `ToxicContentException` (kế thừa `DomainException`) với message:
  `Nội dung Weekly Review chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa review trước khi chuyển thành bài viết.`
- Exception mang theo `moderation` để review page highlight vàng đúng vị trí.
- `WeeklyReviewController::convert` đã bắt `DomainException` và trả `409`; giữ nguyên status, bổ sung `moderation` trong JSON.
- Không tạo Post, không đổi trạng thái review.
- Không kiểm tra tên tag gợi ý vì tag được sync theo tên có sẵn; nếu tag đó đã tồn tại thì thuộc diện nội dung cũ.

**Nghiệm thu**

- Review có nội dung sạch: convert thành công như cũ.
- Review vi phạm: nhận `409`, không có Post mới, review giữ nguyên trạng thái.
- Gọi convert lần hai sau khi sửa review: thành công.

### UC7 — Quét nội dung cũ

**Command**

```bash
php artisan toxic-words:scan --scope=post --limit=100
php artisan toxic-words:scan --scope=comment --since=2026-01-01
```

**Hành vi**

- Chỉ đọc, không sửa/xóa.
- Hỗ trợ `--scope=post|tag|category|comment|user_name`, có thể truyền nhiều lần.
- Hỗ trợ `--limit` (mặc định 500) và `--since`.
- In bảng: `id`, `scope`, `field`, `term id`, `term` (rút gọn), `record id`, `title/name`.
- Không in toàn bộ nội dung.
- Kết thúc bình thường dù có match; chỉ trả exit code khác 0 khi command lỗi.
- Không chạy tự động trong scheduler.

**Nghiệm thu**

- Chạy trên dữ liệu mẫu trả đúng danh sách.
- Không thay đổi database.
- Không làm chậm request hiện tại.

---

## 8. API và UI contract

### 8.1. Lỗi validation

Ví dụ comment:

```json
{
  "message": "Bình luận chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa trước khi gửi.",
  "errors": {
    "content": [
      "Bình luận chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa trước khi gửi."
    ]
  }
}
```

- HTTP status: `422`.
- Request công khai (comment, đăng ký): không trả term khớp, vị trí khớp hoặc danh sách từ trong dictionary.
- Request admin: có thể trả thêm `moderation` chứa HTML highlight và danh sách field vi phạm; không trả term gốc.
- Với form admin: redirect back, `@error` hiện dưới field tương ứng; highlight được render từ `moderation`.

### 8.2. Admin index response

Hỗ trợ cả HTML và JSON giống `CategoryController`:

```json
{
  "data": [
    {
      "id": 1,
      "term": "***",
      "scopes": ["comment", "user_name"],
      "match_mode": "WORD",
      "fold_diacritics": false,
      "is_active": true,
      "note": null,
      "created_at": "2026-10-24T08:00:00.000000Z",
      "updated_at": "2026-10-24T08:00:00.000000Z"
    }
  ]
}
```

- Khi trả JSON cho danh sách, có thể trả `term` đầy đủ vì đây là màn hình Admin.
- Không đưa danh sách từ điển vào public page hoặc file JS public.

### 8.3. Điều hướng

- Thêm mục **Toxic Words** vào nhóm Taxonomy trong `resources/views/layouts/admin.blade.php`.
- Dùng icon Material Symbols hiện có, ví dụ `gpp_bad` hoặc `shield`.
- Không thay đổi layout khác.

### 8.4. Highlight từ độc hại trên editor và review

#### Vì sao không thể "tô màu markdown"

Markdown chỉ là plain text. Trình duyệt không cho `<textarea>` hoặc `<input type="text">` style một substring. Màu chỉ tồn tại ở **lớp render HTML**. Vì vậy:

- Không sửa markdown nguồn.
- Không chèn thẻ HTML vào markdown đã lưu.
- Highlight là **lớp trình bày phủ lên** editor và preview, chỉ tồn tại sau khi submit thất bại.

#### Phương án đã cân nhắc

| Phương án | Ưu điểm | Nhược điểm | Kết luận |
|---|---|---|---|
| Chỉ highlight ở preview + danh sách lỗi | Đơn giản, không lệch layout, không cần JS phức tạp | Không thấy vàng khi đang ở tab Write | Fallback |
| **Overlay mirror sau textarea + highlight preview** | Vàng ngay trong editor, không đổi markdown, không thêm dependency | Cần đồng bộ font, padding, line-height, scroll; cần test kỹ | **Chọn** |
| Rich editor / CodeMirror / Monaco | Highlight chuẩn, nhiều tính năng | Thêm dependency, đổi UX editor, tăng bundle và rủi ro markdown | Không dùng trong MVP |

Khuyến nghị: **overlay cho textarea + highlight ở preview/review**. Nếu thời gian gấp, làm preview + danh sách lỗi trước; overlay là lớp tăng cường.

#### Payload `moderation`

Chỉ trả cho route admin. Ví dụ response `422` khi submit bài viết:

```json
{
  "message": "Nội dung bài viết chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.",
  "errors": {
    "markdown_content": [
      "Nội dung bài viết chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa."
    ]
  },
  "moderation": {
    "summary": "Tìm thấy 2 từ ngữ không phù hợp trong 1 trường.",
    "fields": {
      "markdown_content": {
        "count": 2,
        "overlay_html": "&lt;p&gt;Đoạn văn có &lt;mark class=\"toxic-word-hit\" data-term-id=\"12\"&gt;badword&lt;/mark&gt; cần sửa.&lt;/p&gt;",
        "preview_html": "<p>Đoạn văn có <mark class=\"toxic-word-hit\" data-term-id=\"12\">badword</mark> cần sửa.</p>"
      }
    }
  }
}
```

Quy tắc payload:

- `overlay_html`: toàn bộ text **đã escape HTML**, sau đó bọc các đoạn khớp bằng `<mark class="toxic-word-hit" data-term-id="...">`. Giữ nguyên ký tự markdown để khớp với textarea.
- `preview_html`: markdown đã render an toàn (`Str::markdown` với `html_input=strip`, `allow_unsafe_links=false`) rồi highlight trong text node.
- `count`: số vị trí khớp trong field.
- `summary`: câu tổng hợp cho screen reader và banner.
- Không trả term gốc, danh sách từ điển hoặc pattern.
- `preview_html` phải được sanitize; không đưa HTML từ user vào trực tiếp.
- Với field một dòng (`title`, `slug`, `excerpt` dạng input): trả `snippets` thay vì overlay:

```json
{
  "snippets": [
    "…đoạn văn có <mark class=\"toxic-word-hit\">badword</mark> cần sửa…"
  ]
}
```

#### Luồng submit

**Form thường (bài viết, category, tag)**

1. `FormRequest` kiểm tra và ném `ToxicContentException`.
2. Exception render redirect back với `errors`, `old input` và `moderation`.
3. Blade đưa `moderation` vào `data-moderation` của form/editor.
4. `toxic-highlight.js` đọc payload, render overlay và preview highlight.
5. Người dùng sửa nội dung: highlight cũ bị xóa, hiện dòng chữ nhỏ
   `Nội dung đã thay đổi — gửi lại để kiểm tra.`
6. Submit lại: server kiểm tra lại từ đầu.

**AJAX (AI Weekly review, convert to post)**

1. `ai-weekly.js` nhận `422`/`409` kèm `moderation`.
2. Gọi `window.applyToxicHighlights(form, moderation)`.
3. Nếu có `preview_html` cho `markdown_content`, thay nội dung preview hiện tại bằng HTML đã highlight.
4. Hiển thị `summary` trong `[data-ai-feedback]`; không reload trang, không mất nội dung đang sửa.
5. `convert` thất bại trả `409` kèm `moderation`; review vẫn giữ nguyên trạng thái.

**Request công khai (comment, đăng ký)**

- Chỉ trả `422` và message chung.
- Không trả `moderation`, không highlight, không lộ vị trí/từ khóa.

#### Overlay cho textarea

Cấu trúc:

```html
<div class="toxic-editor" data-toxic-editor>
    <div class="toxic-editor__highlight" data-toxic-highlight aria-hidden="true"></div>
    <textarea name="markdown_content" data-toxic-input>...</textarea>
</div>
```

Yêu cầu kỹ thuật:

- Overlay nằm sau textarea, cùng kích thước, padding, border, font-family, font-size, line-height, letter-spacing và `tab-size`.
- Overlay dùng `white-space: pre-wrap; overflow-wrap: break-word;` và `overflow: hidden`.
- Text của textarea chuyển sang `color: transparent; caret-color: #fff;` khi có highlight; text của overlay hiển thị nội dung bình thường.
- Khi không có JS hoặc không có `moderation`: textarea hiển thị bình thường, overlay ẩn.
- Đồng bộ `scrollTop`/`scrollLeft` mỗi khi textarea scroll hoặc resize.
- Trên `input`, xóa overlay và tắt trạng thái transparent để tránh highlight lệch.
- Không dùng `contenteditable`; không thay đổi giá trị gửi lên server.
- Hỗ trợ IME và chế độ mobile: test paste, gõ tiếng Việt, undo/redo.

#### Highlight preview và review

- Bài viết: preview hiện có dùng `marked` + `DOMPurify`. Sau khi submit lỗi, dùng `preview_html` từ server cho trạng thái đã submit; khi người dùng sửa, quay lại preview client-side bình thường và xóa highlight cũ.
- Review: `WeeklyReviewController::show` đã render `$previewHtml`; thêm bước highlight server-side trước khi truyền ra view. Khi AJAX trả `moderation`, thay preview bằng `preview_html`.
- Cách highlight HTML: duyệt text node (bỏ qua `script`, `style`), chạy matcher trên từng text node, bọc match bằng `<mark class="toxic-word-hit" data-term-id="...">`.
- MVP highlight trong một text node. Trường hợp term bị markdown chia cắt thành nhiều node (ví dụ `bad**word**`) vẫn bị chặn nhưng có thể không highlight; ghi nhận là giới hạn đã biết.

#### CSS và accessibility

```css
.toxic-word-hit {
    background: #facc15;
    color: #111827;
    border-bottom: 1px dotted #92400e;
    border-radius: 2px;
    padding: 0 1px;
    scroll-margin: 4rem;
}
```

- Không chỉ dựa vào màu: thêm viền/gạch chân chấm và banner tổng hợp.
- Banner: `Tìm thấy N từ ngữ không phù hợp trong M trường.` kèm nút **Nhảy đến**.
- Nút **Nhảy đến** đặt selection trong textarea tại vị trí lỗi và scroll tới đó; preview scroll tới `mark` đầu tiên.
- Dùng thẻ `<mark>` để screen reader hiểu đây là highlight.
- `aria-describedby` của field trỏ tới message lỗi và banner.
- Không hiển thị term trong tooltip; tooltip chỉ ghi `Từ ngữ không phù hợp`.

#### File dự kiến

- `app/Support/Moderation/ToxicContentException.php`
- `app/Support/Moderation/ToxicWordHighlighter.php`
- `app/Http/Requests/Concerns/ValidatesToxicContent.php`
- `resources/js/admin/toxic-highlight.js`
- `resources/css/admin.css`
- `resources/views/admin/articles/_form.blade.php`
- `resources/views/admin/ai-weekly/review.blade.php`
- `resources/js/admin/ai-weekly.js`

---

## 9. Cache và hiệu năng

- Chỉ load các term `is_active = true`.
- Cache danh sách đã chuẩn hóa theo `config('toxic_words.cache_key')`, TTL 600 giây.
- Model events `saved`, `deleted` xóa cache.
- Matcher biên dịch pattern một lần cho mỗi lần cache miss; lưu pattern đã biên dịch trong cache cùng dữ liệu nếu serializer hỗ trợ, hoặc biên dịch lại từ danh sách đã cache.
- Tách pattern theo:
  - `WORD` + giữ dấu.
  - `WORD` + bỏ dấu.
  - `LOOSE` + giữ dấu.
  - `LOOSE` + bỏ dấu.
- Mỗi lần check chỉ chạy tối đa 2 pattern (một cho mode của term, một cho fold nếu cần).
- Nếu không có term active cho scope: trả ngay, không chạy regex.
- Giới hạn mềm `max_terms`:
  - Nếu vượt, ghi log `critical` và vẫn load để tránh bỏ sót.
  - Không âm thầm cắt bớt danh sách.
- Nội dung bài viết tối đa 200.000 ký tự. Đo hiệu năng với 1.000 term; nếu vượt 50ms cho một request thông thường, chia nội dung thành chunk 20.000 ký tự và dừng ở match đầu tiên.
- Không thêm Redis dependency; dùng cache store hiện có.

**Fallback**

- Nếu cache/DB lỗi khi load từ điển:
  - Ghi log `error`.
  - Dùng `config('toxic_words.baseline')` đã chuẩn hóa.
  - Nếu baseline rỗng, cho phép request đi qua và ghi log `warning` (fail-open) để không chặn toàn bộ hệ thống vì lỗi hạ tầng.
- Không cache kết quả `false` của một lần check.

---

## 10. Bảo mật, quyền riêng tư và quan sát

### 10.1. Quyền

- Quản lý từ điển: chỉ Admin.
- Không expose route hoặc dữ liệu từ điển ra public API.
- Không nhận `normalized_term` từ client.

### 10.2. Log

Khi chặn, ghi log có cấu trúc:

```text
event=toxic_words.blocked
scope=comment
field=content
term_id=12
user_id=45
route=articles.comments.store
content_hash=<sha256 12 ký tự đầu>
```

- Không log toàn bộ nội dung.
- Không log term gốc ở mức `info`; chỉ `term_id`.
- Không log mật khẩu, email, token.
- Có thể thêm kênh `moderation` sau; MVP dùng log channel mặc định.

### 10.3. Kill switch

- `TOXIC_WORDS_ENABLED=false` bỏ qua toàn bộ kiểm tra nhưng vẫn giữ route quản lý.
- Ghi log `warning` một lần mỗi request khi đang tắt? Không; tránh spam. Chỉ ghi khi config được đọc lần đầu hoặc không ghi.

---

## 11. Test plan

### 11.1. Unit

`tests/Unit/ToxicWordMatcherTest.php`:

- Chuẩn hóa lowercase, khoảng trắng, zero-width.
- `WORD` khớp đúng ranh giới từ.
- `WORD` không khớp substring.
- `LOOSE` khớp `bad-word`, `b a d w o r d`, `b4dw0rd`, ký tự lặp.
- Highlighter: escape đúng HTML, bọc đủ nhiều match, giữ nguyên ký tự markdown trong `overlay_html`.
- Highlighter: `preview_html` chỉ chứa `<mark>`, không chứa `<script>`, không chứa term gốc ngoài phần nội dung submitted.
- Highlighter: match trong text node của HTML, bỏ qua `script`/`style`.
- `LOOSE` từ chối term < 4 ký tự ở tầng validation.
- Giữ dấu mặc định: term `từcấm` không khớp `tucam`.
- `fold_diacritics=true`: term `từcấm` khớp `tucam`.
- Không chặn nhầm `các` khi term là `cac` và `fold_diacritics=false`.
- Scope isolation: term chỉ có `comment` không chặn `post`.
- Term inactive không chặn.
- Text rỗng/null trả sạch.

### 11.2. Feature

`tests/Feature/AdminToxicWordTest.php`:

- Guest bị redirect, user thường `403`.
- Admin xem danh sách HTML/JSON.
- Tạo term thành công, chuẩn hóa đúng.
- Tạo trùng trả lỗi.
- Tạo `LOOSE` với term ngắn trả lỗi.
- Cập nhật term, bật/tắt, xóa.
- Cache được xóa sau thay đổi (tạo term rồi request chặn ngay).

Mở rộng test hiện có:

- `AdminArticleTest`: tạo/sửa bài với term ở title, excerpt, content, slug; tag mới; đảm bảo không có record nào được tạo; response/redirect có `moderation` với `overlay_html` và `preview_html` đã escape/sanitize.
- `AdminArticleTest`: comment công khai vi phạm không được chứa key `moderation`.
- `AdminArticleTest`: review chuyển Post vi phạm trả `409` kèm `moderation`.
- Browser/manual: overlay thẳng hàng khi scroll, resize, gõ tiếng Việt, paste nhiều dòng; highlight biến mất khi sửa nội dung.
- `AdminTagTest`: tạo/sửa tag vi phạm.
- `AdminCategoryTest`: tạo/sửa category vi phạm.
- Comment feature test: tạo/sửa comment vi phạm, kiểm tra `422` và không đổi dữ liệu; comment sạch vẫn hoạt động.
- Auth/register test: tên vi phạm.
- `AdminUserTest` (nếu có): tên vi phạm.
- AI Weekly test: `DraftPostCreator` vi phạm ném `DomainException`, không tạo Post; convert trả `409`.
- Seeder test: chạy hai lần không tạo trùng, không bật lại term đã tắt.

### 11.3. Dữ liệu test

- Dùng term trung tính: `badword`, `từcấm`, `cumulative` (để kiểm tra substring).
- Không đưa từ tục thật vào fixture, seeder hay log.
- Factory `ToxicWordFactory` hỗ trợ state `active`, `inactive`, `loose`, `folded`.

### 11.4. Lệnh kiểm tra

```bash
php artisan test --compact tests/Unit/ToxicWordMatcherTest.php
php artisan test --compact tests/Feature/AdminToxicWordTest.php
vendor/bin/pint --dirty --format agent
```

---

## 12. Tiêu chí nghiệm thu

1. Admin quản lý được từ điển: thêm, sửa, bật/tắt, xóa, tìm kiếm, lọc scope.
2. Từ mới có hiệu lực ngay sau khi lưu, không cần restart hoặc clear cache thủ công.
3. Không tạo/cập nhật được post, tag, category, comment, user name chứa term thuộc scope tương ứng.
4. Bài viết AI Weekly vi phạm không chuyển được thành Post và review không bị thay đổi.
5. Nội dung sạch vẫn hoạt động đúng như trước, không thay đổi response thành công hiện có.
6. Không chặn nhầm `các` khi term là `cac` ở cấu hình mặc định.
7. Thông báo lỗi không làm lộ term đã khớp.
8. Kill switch tắt được kiểm tra toàn cục.
9. Command quét nội dung cũ chỉ đọc và không sửa dữ liệu.
10. Submit bài viết/review vi phạm hiển thị highlight vàng đúng vị trí trên editor, preview và review; sửa nội dung thì highlight cũ biến mất.
11. Request công khai không trả `moderation`, không lộ vị trí hoặc từ khóa.
12. Test mới và test hiện có liên quan đều pass; Pint không báo lỗi.

---

## 13. Kế hoạch triển khai

| Bước | Nội dung | File dự kiến |
|---|---|---|
| 1 | Migration, model, factory | `database/migrations/*_create_toxic_words_table.php`, `app/Models/ToxicWord.php`, `database/factories/ToxicWordFactory.php` |
| 2 | Config, seeder | `config/toxic_words.php`, `database/seeders/ToxicWordSeeder.php` |
| 3 | Chuẩn hóa và so khớp | `app/Support/Moderation/ToxicWordNormalizer.php`, `ToxicWordMatcher.php`, `ToxicWordFilter.php`, `ToxicWordRepository.php`, `ToxicWordMatch.php` |
| 4 | Validation rule | `app/Rules/CleanContent.php` |
| 5 | Admin CRUD | `app/Http/Controllers/Admin/ToxicWordController.php`, `app/Http/Requests/Admin/StoreToxicWordRequest.php`, `UpdateToxicWordRequest.php`, `resources/views/admin/toxic-words/index.blade.php` |
| 6 | Route, nav | `routes/web.php`, `resources/views/layouts/admin.blade.php` |
| 7 | Tích hợp request | `StoreArticleRequest`, `UpdateArticleRequest`, `StoreTagRequest`, `UpdateTagRequest`, `StoreCategoryRequest`, `UpdateCategoryRequest`, `StoreCommentRequest`, `UpdateCommentRequest`, `RegisterRequest`, `StoreUserRequest`, `UpdateUserRequest` |
| 8 | AI Weekly | `app/AiWeekly/DraftPostCreator.php` |
| 9 | Quét nội dung cũ | `app/Console/Commands/ScanToxicWords.php` |
| 10 | Highlight editor/review | `ToxicContentException`, `ToxicWordHighlighter`, `ValidatesToxicContent`, `resources/js/admin/toxic-highlight.js`, `resources/css/admin.css`, `_form.blade.php`, `review.blade.php`, `ai-weekly.js` |
| 11 | Test và Pint | `tests/Unit/ToxicWordMatcherTest.php`, `tests/Feature/AdminToxicWordTest.php`, các feature test hiện có |

**Thứ tự ưu tiên:** 1 → 3 → 4 → 7 → 2 → 5 → 6 → 8 → 9 → 10 → 11.

Không tạo bảng audit riêng trong MVP; log có cấu trúc là đủ để theo dõi và tinh chỉnh từ điển.
