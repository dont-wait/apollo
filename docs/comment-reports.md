# Khiếu nại comment (Comment Reports)

Cho phép người dùng báo cáo comment tiêu cực/spam/quấy rối, và admin xử lý các báo cáo đó.
Bổ sung cho từ điển toxic words (chặn lúc đăng): report xử lý những comment **đã lọt qua** bộ lọc.

## Luồng tổng quan

```
Reader bấm "Report" ──► POST /articles/{slug}/comments/{id}/report
                              │  tạo comment_reports (status = OPEN)
                              ▼
Admin vào /admin/comment-reports ──► chọn 1 trong 3 hành động
   ├─ Hide comment   → comment.status = HIDDEN,  mọi report OPEN của comment → RESOLVED
   ├─ Delete comment → comment.status = DELETED, mọi report OPEN của comment → RESOLVED
   └─ Dismiss        → chỉ report đó → DISMISSED, comment giữ nguyên
```

## Database

Bảng `comment_reports` (migration `2026_10_27_000001_create_comment_reports_table.php`):

| Cột | Ý nghĩa |
|---|---|
| `comment_id` | Comment bị báo cáo (xoá comment thì xoá report theo – cascade) |
| `reporter_id` | Người báo cáo (cascade theo user) |
| `reason` | `SPAM`, `OFFENSIVE`, `HARASSMENT`, `MISLEADING`, `OTHER` |
| `details` | Mô tả thêm, tối đa 1000 ký tự, không bắt buộc |
| `status` | `OPEN` → `RESOLVED` hoặc `DISMISSED` |
| `handled_by`, `handled_at` | Admin xử lý và thời điểm |

- `unique(comment_id, reporter_id)`: mỗi người chỉ report một comment một lần.
- Danh sách lý do và nhãn hiển thị nằm ở hằng số `CommentReport::REASONS` (dùng chung cho validate và UI).

## Phía người dùng

- Route: `POST articles/{article:slug}/comments/{comment}/report` (tên `articles.comments.report`), middleware `auth`, `auth.session`, `throttle:comment-reports` (5 lần/phút/user, 20/phút/IP – khai báo trong `AppServiceProvider`).
- Controller: `App\Http\Controllers\CommentReportController@store`, validate bằng `StoreCommentReportRequest`.
- Quy tắc:
  - Bài viết phải đang published; comment phải `VISIBLE` và truy cập công khai được, nếu không → `404`.
  - Không được report comment của chính mình → `403`.
  - Report lặp lại không tạo bản ghi mới (dùng `firstOrCreate`): trả `200` kèm "đã report rồi"; lần đầu trả `201`.
- UI: nút **Report** ở mỗi comment (`resources/views/articles/show.blade.php`), logic Alpine trong `window.articleComments` (`resources/js/app.js`: `canReport`, `startReport`, `submitReport`). Nút chỉ hiện khi đã đăng nhập, comment `VISIBLE` và không phải của mình.

## Phía admin

- Menu **Community → Comment Reports** → `/admin/comment-reports` (`admin.comment-reports.index`), nằm trong group admin nên đã có middleware `EnsureUserIsAdmin`.
- Có tab `Open / Resolved / Dismissed` kèm số lượng; mặc định hiện `Open`. Mỗi dòng hiện người báo, lý do, nội dung comment, bài viết, và số report đang mở trên cùng comment.
- Xử lý: `PATCH /admin/comment-reports/{report}` với `action` = `hide` | `delete` | `dismiss` (`UpdateCommentReportRequest`).
- Logic trong `Admin\CommentReportController@update`, chạy trong transaction và `lockForUpdate` report: nếu report không còn `OPEN` (admin khác đã xử lý) thì bỏ qua, không làm gì.
- `hide`/`delete` dùng cùng cách cập nhật comment như trang Comments Moderation (`moderated_by`, `moderated_at`, tăng `version`), nên comment trở lại bình thường nếu admin khôi phục ở trang đó.

## File chính

- `app/Models/CommentReport.php`, `database/factories/CommentReportFactory.php`
- `app/Http/Controllers/CommentReportController.php`, `app/Http/Controllers/Admin/CommentReportController.php`
- `app/Http/Requests/StoreCommentReportRequest.php`, `app/Http/Requests/Admin/UpdateCommentReportRequest.php`
- `resources/views/admin/comment-reports/index.blade.php`
- `tests/Feature/CommentReportTest.php`

## Chưa làm (có thể mở rộng)

- Tự động ẩn comment khi vượt ngưỡng số report (dễ bị lạm dụng nếu nhiều người cùng báo sai).
- Thông báo cho admin khi có report mới (hiện phải vào trang để xem).
- Báo cho người báo cáo biết kết quả xử lý.
- Trang Comments Moderation chưa hiện số report của từng comment.
