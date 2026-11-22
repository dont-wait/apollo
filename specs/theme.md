# Spec: Theme sáng và bộ chọn giao diện Apollo

**Ngày khảo sát:** 19/10/2026  
**Trạng thái:** đặc tả chuẩn bị triển khai; chưa thay đổi giao diện.  
**Phạm vi đã chốt:** toàn ứng dụng, ba lựa chọn Light / Dark / System, mặc định System.

## 1. Mục tiêu và hiện trạng

Bổ sung theme sáng phù hợp với blog công nghệ/AI và khu vực quản trị của Apollo: nền trung tính lạnh, nội dung dễ đọc, xanh dương là màu hành động chính, tím và xanh lá là màu phụ có tiết chế. Giữ bố cục, font Geist / Hanken Grotesk / JetBrains Mono và nghiệp vụ hiện tại. Đính trên navbar

Source hiện có:

- `resources/css/app.css` khai báo màu tối bằng Tailwind `@theme`, đặt `color-scheme: dark` cố định.
- `resources/css/admin.css` import CSS chung nhưng khai báo thêm nhiều màu tương đương dưới tên `surface-container-*`.
- Ba layout `app`, `auth`, `admin` dùng chung `resources/js/app.js` với Alpine; chưa có trạng thái theme hoặc lưu lựa chọn.
- Admin, modal xác nhận và editor có nhiều mã hex viết trực tiếp, kể cả class được JavaScript tạo động. Biểu đồ SVG và logo auth cũng có màu cố định.
- Trang 403 kế thừa layout công khai. Các mục admin đang trỏ tới `#` vẫn là placeholder, không phát triển thêm trong hạng mục này.

Phạm vi bao gồm trang chủ, chi tiết bài viết, đăng nhập/đăng ký, dashboard, articles, categories/tags, media và trang 403 hiện có. Không thêm API, migration, đồng bộ tài khoản hoặc dependency.

## 2. Nghiên cứu và quyết định màu

### 2.1. Cơ sở lựa chọn

Đây là bảng màu thiết kế riêng cho Apollo, không phải palette tự động sinh từ Material. Tham khảo cách phân vai surface, accent và màu nội dung trên nền tương ứng trong [Material Design color roles](https://m3.material.io/styles/color/roles), đồng thời giữ hợp đồng token đang có.

- **Nền xám lạnh `#f6f8fb`:** phù hợp nhóm màu xanh hiện tại; card trắng phân lớp bằng viền và shadow nhẹ. Vùng đọc bài giữ nền sáng trung tính, không phủ gradient màu lên nội dung dài.
- **Primary `#0369a1`:** giữ hướng xanh trời của thương hiệu nhưng đủ đậm trên nền sáng. Màu tối hiện tại `#8ed5ff` chỉ đạt **1.60:1** trên trắng, không dùng lại cho chữ hoặc icon chức năng của theme sáng.
- **Secondary `#4338ca`:** tím chàm cho điểm nhấn AI, nhãn phụ và chuỗi biểu đồ; không dùng cạnh tranh với CTA chính.
- **Tertiary `#04704f`:** xanh ngọc đậm cho tín hiệu tích cực. Ứng viên `#047857` chỉ đạt **4.45:1** trên surface đậm nhất của theme sáng nên đã được thay bằng màu đậm hơn, đạt **4.96:1**.
- **Màu trạng thái:** đỏ cho lỗi/phá hủy, hổ phách cho cảnh báo. Mỗi trạng thái có nhãn hoặc icon đi kèm; không truyền đạt ý nghĩa chỉ bằng màu.

Mục tiêu tương phản: chữ thường ít nhất 4.5:1; chữ lớn ít nhất 3:1 theo [WCAG 1.4.3](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html). Chữ lớn được hiểu là ít nhất 18pt hoặc 14pt đậm, không áp dụng ngưỡng 3:1 cho các nhãn nhỏ của giao diện. Chỉ báo cần thiết để nhận biết control, trạng thái và đồ họa đạt ít nhất 3:1 với nền liền kề theo [WCAG 1.4.11](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).

### 2.2. Bảng token chốt cho theme sáng

Các tên dưới đây có tiền tố CSS `--color-`. Giá trị hex là giá trị triển khai v1, không để người triển khai tự chọn lại palette.

| Token | Light | Vai trò |
|---|---|---|
| `background` | `#f6f8fb` | Nền trang |
| `surface-lowest` | `#ffffff` | Card, modal, sidebar/header admin |
| `surface-low` | `#f1f5f9` | Khối phụ, code block |
| `surface` | `#f8fafc` | Surface mặc định |
| `surface-high` | `#e8eef5` | Hover của vùng trung tính |
| `surface-highest` | `#e2e8f0` | Phân lớp mạnh, nền track |
| `surface-bright` | `#ffffff` | Surface sáng nhất |
| `on-surface` | `#1e293b` | Tiêu đề, nội dung chính |
| `on-surface-variant` | `#475569` | Metadata, mô tả, placeholder có ý nghĩa |
| `outline` | `#64748b` | Viền control cần nhận biết |
| `outline-variant` | `#cbd5e1` | Divider và viền trang trí |
| `primary` | `#0369a1` | Link, icon chính, CTA |
| `primary-hover` | `#075985` | Hover/active của CTA chính |
| `on-primary` | `#ffffff` | Chữ/icon trên primary và primary-hover |
| `primary-container` | `#e0f2fe` | Nền lựa chọn, badge thông tin |
| `on-primary-container` | `#0c4a6e` | Chữ/icon trên primary-container |
| `secondary` | `#4338ca` | Nhấn tím và chuỗi biểu đồ phụ |
| `on-secondary` | `#ffffff` | Chữ trên secondary |
| `secondary-container` | `#e0e7ff` | Badge AI, nền tím nhẹ |
| `on-secondary-container` | `#312e81` | Chữ trên secondary-container |
| `tertiary` | `#04704f` | Tín hiệu tích cực, chuỗi biểu đồ |
| `on-tertiary` | `#ffffff` | Chữ trên tertiary |
| `tertiary-container` | `#d1fae5` | Badge thành công |
| `on-tertiary-container` | `#064e3b` | Chữ trên tertiary-container |
| `error` | `#b91c1c` | Lỗi, hành động phá hủy |
| `on-error` | `#ffffff` | Chữ trên error |
| `error-container` | `#fee2e2` | Nền thông báo lỗi |
| `on-error-container` | `#7f1d1d` | Nội dung thông báo lỗi |
| `warning` | `#92400e` | Cảnh báo |
| `warning-container` | `#fef3c7` | Nền cảnh báo |
| `on-warning-container` | `#92400e` | Nội dung cảnh báo |
| `focus-ring` | `#0369a1` | Focus ngoài control |
| `selection` | `#e0f2fe` | Nền chọn văn bản |
| `on-selection` | `#0c4a6e` | Chữ đang chọn |

Không dùng `outline` cho chữ nhỏ: màu metadata là `on-surface-variant`. Divider trang trí được phép nhạt; viền là dấu hiệu cần thiết để nhận biết input phải dùng `outline` đủ đậm, không giảm opacity.

### 2.3. Theme tối và tương thích token

Giữ các giá trị token tối hiện có trong `app.css`. Bổ sung các token còn thiếu:

| Token mới | Dark |
|---|---|
| `primary-hover` | `#c4e7ff` |
| `on-primary-container` | `#00354a` |
| `on-secondary` | `#202060` |
| `on-secondary-container` | `#e0e7ff` |
| `on-tertiary` | `#003824` |
| `on-tertiary-container` | `#003824` |
| `on-error` | `#690005` |
| `on-error-container` | `#ffdad6` |
| `warning` | `#fcd34d` |
| `warning-container` | `#78350f` |
| `on-warning-container` | `#fef3c7` |
| `focus-ring` | `#8ed5ff` |
| `selection` | `#38bdf8` |
| `on-selection` | `#00354a` |

Chuẩn hóa alias admin theo theme thực tế:

- `on-background` → `on-surface`; `surface-dim` → `background`.
- `surface-container-lowest/low/high/highest` → `surface-lowest/low/high/highest`; `surface-container` → `surface`.
- `primary-fixed` giữ `#c4e7ff`, `primary-fixed-dim` giữ `#7bd0ff` ở cả hai theme; đây là màu fixed, không dùng cho chữ trên surface sáng. Chuyển các nơi đang dùng như link hoặc icon chức năng sang `primary`.
- Thay `hover:bg-surface-tint` hiện có bằng `hover:bg-primary-hover`; không để trạng thái hover phụ thuộc token chưa khai báo.

Tách cặp `on-primary-container` khỏi `on-primary`: chữ trắng của CTA theme sáng không phù hợp trên container xanh nhạt. Selection cũng dùng cặp riêng để không phụ thuộc cách dùng container.

### 2.4. Kết quả tính tương phản

Tính trực tiếp từ mã hex sRGB theo relative luminance và công thức `(L_sáng + 0.05) / (L_tối + 0.05)`. Số bên dưới làm tròn để trình bày; đánh giá đạt/trượt dùng giá trị chưa làm tròn.

| Chữ hoặc chỉ báo / nền | Tỷ lệ | Mục đích |
|---|---:|---|
| `#1e293b` / `#f6f8fb` | 13.75:1 | Nội dung chính |
| `#475569` / `#e2e8f0` | 6.15:1 | Metadata trên surface sáng đậm nhất |
| `#0369a1` / `#e2e8f0` | 4.81:1 | Primary trên surface sáng đậm nhất |
| `#4338ca` / `#e2e8f0` | 6.41:1 | Secondary trên surface sáng đậm nhất |
| `#04704f` / `#e2e8f0` | 4.96:1 | Tertiary trên surface sáng đậm nhất |
| `#b91c1c` / `#e2e8f0` | 5.25:1 | Error trên surface sáng đậm nhất |
| `#ffffff` / `#0369a1` | 5.93:1 | CTA mặc định |
| `#ffffff` / `#075985` | 7.56:1 | CTA hover |
| `#0c4a6e` / `#e0f2fe` | 8.24:1 | Container primary / selection |
| `#312e81` / `#e0e7ff` | 9.27:1 | Container secondary |
| `#064e3b` / `#d1fae5` | 8.57:1 | Container tertiary |
| `#7f1d1d` / `#fee2e2` | 8.20:1 | Container error |
| `#92400e` / `#fef3c7` | 6.37:1 | Container warning |
| `#64748b` / `#e2e8f0` | 3.86:1 | Viền control, không phải chữ nhỏ |

Đây là kiểm chứng cặp màu đặc, chưa phải chứng nhận toàn giao diện đạt WCAG. Opacity, gradient, ảnh nền và màu chồng lớp phải đo trên màu composited thực tế khi triển khai. Rà soát cả dark theme sau khi đổi token; không suy ra dark đã đạt chỉ vì giữ palette cũ.

## 3. Quy tắc áp dụng theo thành phần

| Thành phần | Quy định |
|---|---|
| Trang chủ và bài viết | Nền background, chữ on-surface; link primary có underline trong nội dung; metadata on-surface-variant |
| Card, modal và menu | Nền surface-lowest, viền outline-variant; hover trung tính dùng surface-high |
| Admin sidebar | Nền surface-lowest; mục chọn dùng primary-container + on-primary-container và chỉ báo primary |
| Input, textarea, select | Nền surface-lowest, chữ on-surface, placeholder on-surface-variant, viền outline |
| Nút chính | primary + on-primary; hover primary-hover; không dùng text-white chung cho cả hai theme |
| Badge và alert | Dùng cặp container/on-container tương ứng ở opacity 100%; không dùng text-error trên mọi nền lỗi một cách máy móc |
| Editor và Markdown preview | Thanh công cụ, slash menu, bộ chèn bảng và phần tử tạo động đều dùng token; đổi theme không làm mất nội dung/caret |
| Markdown code và bảng | Code block surface-low, chữ on-surface; hàng/viền bảng phân biệt được, không áp bộ màu syntax tối cố định |
| Biểu đồ SVG | Đường dữ liệu dùng primary/secondary/tertiary, legend dùng on-surface-variant; dùng nét đứt/nhãn để phân biệt chuỗi; grid trang trí dùng outline-variant |
| Ảnh và logo | Giữ nguyên ảnh thumbnail và màu logo bên thứ ba như Google; logo Apollo dùng token khi đặt trực tiếp trên surface |
| Nội dung đè lên ảnh | Cho phép scrim đen và chữ sáng cố định nhưng phải kiểm tra tương phản; không đảo màu ảnh |

Focus dùng outline/ring 2px, offset 2px với màu nền tương ứng để nhìn rõ cả trên nút đặc. Không giảm opacity cho chữ metadata đang cần đọc; thay `text-primary/60`, `text-primary/70` bằng token chữ đủ tương phản. Không áp `opacity` lên toàn bộ card chứa nội dung.

Shadow theme sáng dùng đen/xám ở opacity thấp, ví dụ `0 1px 3px rgb(15 23 42 / 0.08)`; tránh shadow đen dày trên toàn bộ card. Animation hiện có giữ nguyên, không thêm animation đổi màu toàn trang.

## 4. Trải nghiệm và hợp đồng theme

- Component dùng chung có nhãn `Theme` và native select gồm `Light`, `Dark`, `System`; chọn bằng bàn phím, có focus rõ ràng.
- Trang công khai đặt bộ chọn ở header, hiện cả mobile cạnh Menu; admin đặt trong topbar; auth đặt trên form. Trang 403 dùng bộ chọn từ layout công khai.
- Chọn theme có hiệu lực ngay, không reload; giữ dữ liệu form, editor, bộ lọc và modal đang mở.
- System vẫn hiển thị là lựa chọn đang chọn, kể cả khi theme thực tế đang là Light hoặc Dark.

| Thành phần | Hợp đồng |
|---|---|
| localStorage key | `apollo.theme` |
| Preference | `light`, `dark`, `system` |
| Giá trị thiếu/không hợp lệ | Xem như `system` |
| Theme thực tế | `data-theme="light"` hoặc `data-theme="dark"` trên `<html>` |
| Native controls | `color-scheme` khớp theme thực tế |
| Ưu tiên | Preference light/dark; nếu system thì theo `prefers-color-scheme`; không có tín hiệu thì light |

Lưu cả giá trị `system` khi người dùng chọn System. Nghe sự kiện thay đổi hệ thống và chỉ áp dụng khi preference là system. Nghe `storage` để đồng bộ các tab cùng origin, bao gồm xóa key hoặc clear storage. Không ghi storage ngược lại khi chỉ nhận đồng bộ từ tab khác.

Nếu storage không đọc/ghi được, bắt lỗi và tiếp tục hoạt động bằng trạng thái trong bộ nhớ của trang; không cam kết lưu được qua reload. Login/logout không xóa preference; chưa hỗ trợ đồng bộ giữa trình duyệt, thiết bị hoặc tài khoản.

## 5. Hướng triển khai

1. **Token:** giữ khai báo Tailwind `@theme` ở cấp cao nhất; thêm token mới, áp palette bằng selector `:root[data-theme="light"]` / `:root[data-theme="dark"]`. Đặt override token trong CSS không nằm trong layer để không bị các biến `@theme` sinh ra lấn át. Alias admin dùng CSS variables tham chiếu palette chung. Theo [Tailwind v4 theme variables](https://tailwindcss.com/docs/theme), không lồng `@theme` trong selector hoặc media query.
2. **Khởi tạo sớm:** tạo partial bootstrap chung, include trong head của cả ba layout trước `@vite`. Script đọc preference và gắn data-theme đồng bộ trước khi stylesheet render; dùng một bộ quản lý theme chia sẻ cho bootstrap và Alpine, không duy trì hai thuật toán resolve khác nhau. Có thể expose bộ quản lý nhỏ qua `window.apolloTheme`, sau đó đăng ký Alpine store trước `Alpine.start()`.
3. **CSS fallback:** khi chưa có data-theme, dùng palette light mặc định và media query theo thiết bị cho dark. Khi có data-theme, lựa chọn tường minh luôn thắng media query. `color-scheme` theo cùng quy tắc. Khi JavaScript tắt, ẩn bộ chọn chưa hoạt động, giao diện vẫn theo thiết bị.
4. **Bộ chọn:** tạo Blade component dùng chung và kết nối với store; mọi instance hiển thị cùng preference. Tham khảo cách khởi tạo sớm và lựa chọn hệ thống trong [Tailwind dark mode](https://tailwindcss.com/docs/dark-mode).
5. **Áp dụng:** đổi màu cố định theo vai trò thực tế trên toàn bộ view và JavaScript tạo DOM. Không thay tất cả hex bằng cùng một token theo tìm/thay máy móc. Rà soát SVG fill/stroke/stop-color, trạng thái hover, error, selected và confirm modal.

Chỉ chỉnh presentation và theme state. Không đổi endpoint, payload, render Markdown, logic editor, dữ liệu biểu đồ hay hành vi tìm kiếm. Không thêm test framework hoặc thư viện UI.

## 6. Kiểm thử và tiêu chí nghiệm thu

### Tự động

- Dùng Node test runner đang có: preference mặc định, light/dark ghi đè thiết bị, storage sai/thiếu/bị chặn, thay đổi thiết bị chỉ tác động ở System, đồng bộ tab và xóa key.
- Rerun `node --test tests/home-feed.test.mjs` sau khi thay đổi tích hợp JS để phát hiện regression feed.
- Chạy `npm run build`; chạy PHPUnit phạm vi layout/trang bị ảnh hưởng bằng công cụ hiện có. Nếu sửa PHP/Blade, chạy `vendor/bin/pint --dirty --format agent`.

### Trên trình duyệt

- Kiểm tra Light/Dark trên trang chủ, bài viết có code/bảng, login/register với validation, 403, admin dashboard, articles, categories/tags và media.
- Thử editor, slash menu, preview, chèn bảng, modal xác nhận, trạng thái rỗng/lỗi/loading; đổi theme giữa lúc nhập liệu không mất trạng thái.
- Tải trực tiếp từng layout và reload với preference đã lưu; không thấy nháy palette đối lập ở lần hiển thị đầu tiên. Thử System khi đổi theme thiết bị và hai tab đang mở.
- Đo tương phản trên computed colors, cả trạng thái hover/focus và thành phần có opacity. Đặc biệt kiểm tra nhãn nhỏ, thông báo lỗi, legend và SVG.
- Kiểm tra bộ chọn tại viewport 320px và desktop; không chồng Menu, tài khoản hoặc topbar. Giữ phạm vi responsive hiện có, không mở rộng thành thiết kế lại admin mobile.
- Kiểm tra JavaScript bị tắt và storage bị chặn. Không có lỗi JS mới do theme.

**Hoàn thành khi:** cả hai theme được áp dụng đồng bộ trên toàn bộ màn hình trong phạm vi; các cặp màu dùng đúng vai trò và đạt ngưỡng tương phản tương ứng; preference được lưu/đồng bộ đúng; không mất trạng thái thao tác; build và regression test liên quan đạt. Báo rõ browser test nào chưa chạy; build thành công không thay thế nghiệm thu giao diện.

## 7. Nguồn tham khảo

- [W3C: Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) — ngưỡng tương phản chữ và cách tính.
- [W3C: Non-text Contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html) — control, trạng thái và đồ họa có ý nghĩa.
- [Material Design: Color roles](https://m3.material.io/styles/color/roles) — tham khảo phân vai màu; không phải nguồn sinh palette Apollo.
- [Tailwind CSS: Theme variables](https://tailwindcss.com/docs/theme) và [Dark mode](https://tailwindcss.com/docs/dark-mode) — tích hợp với Tailwind v4 hiện có.

Palette và số liệu đo trong spec là quyết định thiết kế/tính toán cho Apollo tại ngày khảo sát. Chưa có screenshot nghiệm thu hoặc thay đổi runtime trong hạng mục soạn spec này.
