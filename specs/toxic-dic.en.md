# Spec: Toxic word dictionary and content control

**Issue:** [#31](https://github.com/dont-wait/apollo/issues/31) — Toxic word dictionary, prevent users from adding or using toxic words when creating posts, tags, categories, etc.<br>
**Date:** 24/10/2026<br>
**Status:** ready for implementation<br>
**Scope:** Laravel backend, admin UI, validation, AI content checks<br>
**Vietnamese version:** [toxic-dic.md](toxic-dic.md)

---

## 1. Goals and scope

### 1.1. Goals

- Provide a **toxic word dictionary** managed by Admins, with per-entry enable/disable.
- Block content containing toxic words **before it is written to the database** in these flows:
  - Creating/updating posts.
  - Creating/updating tags.
  - Creating/updating categories.
  - Creating/updating comments.
  - Registration and Admin user create/update (display name).
- Block AI Weekly content when it is converted into a `Post` draft.
- Never expose the matched keyword in error messages returned to users.
- After a failed submit, **highlight matches in yellow** in the editor, post preview, and review screen (Admin only).
- Provide a scan command so Admins can review existing content.

### 1.2. In scope

- `toxic_words` table, model, factory, seeder, baseline config.
- Admin dictionary management page.
- Keyword normalization and matching services.
- Shared custom validation rule.
- Integration into existing `FormRequest` classes.
- Checks inside `DraftPostCreator`.
- Existing-content scan command.
- Caching and tests.

### 1.3. Out of scope

- Automatically editing, deleting, or hiding existing content.
- Calling third-party AI/moderation services.
- Advanced evasion protection such as homoglyphs, OCR, or semantic analysis.
- Applying checks to file names, alt text, media, email, or passwords.
- Changing the existing editorial/moderation workflow.
- Adding new packages/dependencies.

---

## 2. Current state verified from source

| Flow | Current file | Current behavior | Gap |
|---|---|---|---|
| Posts | `app/Http/Requests/Admin/StoreArticleRequest.php`, `UpdateArticleRequest.php` | Validates title, slug, excerpt, markdown, new tags | No toxic-word check |
| Tags | `app/Http/Requests/Admin/StoreTagRequest.php`, `UpdateTagRequest.php` | Validates name, slug, color | No check |
| Categories | `app/Http/Requests/Admin/StoreCategoryRequest.php`, `UpdateCategoryRequest.php` | Validates name, slug, description | No check |
| Comments | `app/Http/Requests/StoreCommentRequest.php`, `UpdateCommentRequest.php` | Validates content, parent, version | No check |
| Users | `app/Http/Requests/RegisterRequest.php`, `Admin/StoreUserRequest.php`, `Admin/UpdateUserRequest.php` | Validates name, email, role | No check on display name |
| AI Weekly | `app/AiWeekly/DraftPostCreator.php` | Creates a `Post` draft from a `WeeklyReview` | No content check |
| Admin UI | `resources/views/admin/categories/index.blade.php` | Category/tag management exists | No dictionary screen |
| Admin navigation | `resources/views/layouts/admin.blade.php` | Has a Taxonomy group | No dictionary entry |

Apollo currently has no table, service, or validation related to toxic words.

---

## 3. Control policy

### 3.1. Definition

A **toxic word** is a string (word or phrase) configured by an Admin to block abusive, harassing, hateful, or otherwise unsuitable content.

Each dictionary entry has:

- `term`: the display string shown to Admins.
- `scopes`: where it applies.
- `match_mode`: how it is matched.
- `fold_diacritics`: whether Vietnamese diacritics are ignored during matching.
- `is_active`: enabled/disabled.

### 3.2. Default behavior — BLOCK

MVP has one behavior: **reject the request and return a validation error**. It does not replace characters, store a masked version, or route content to a review queue.

- Public HTTP request: return `422` with an error on the correct field; return no positions or keywords.
- Admin form: redirect back with `errors`, preserve old input, and include `moderation` so matches can be highlighted in yellow.
- Admin AJAX (review): return `422`/`409` with `moderation`; JavaScript highlights in place without losing form state.
- AI content: throw `ToxicContentException` (extends `DomainException`) with a clear message and highlight payload so the Admin can fix the review before conversion.

BLOCK was chosen over REVIEW because the issue asks to **prevent** adding/using toxic words; the existing editorial workflow remains unchanged.

### 3.3. False-positive principles

Vietnamese toxic words are easy to over-block when all diacritics are stripped. For example, `cac` and `các` are different strings with different meanings. Therefore:

- Match **with diacritics preserved** by default.
- Strip diacritics only when an Admin enables `fold_diacritics` for a specific entry and has checked for false positives.
- Match on **word boundaries** by default, not arbitrary substrings.
- `LOOSE` mode (ignore punctuation/spaces, fold leetspeak) is allowed only for terms with **4 or more** characters.
- Never return the matched term to the client.
- Provide a global kill switch for widespread false-positive incidents.

### 3.4. Scope matrix

| Scope | Fields checked | Flow |
|---|---|---|
| `post` | `title`, `slug`, `excerpt`, `markdown_content` | Admin post create/update; AI Weekly draft |
| `tag` | `name`, `slug` | Admin tag create/update; new tags in post form |
| `category` | `name`, `slug`, `description` | Admin category create/update |
| `comment` | `content` | Member comment create/update |
| `user_name` | `name` | Registration, Admin user create/update |

One entry may apply to one or many scopes. The admin UI defaults to all scopes.

### 3.5. Language support

- The dictionary accepts **both English and Vietnamese** terms and phrases; there is no language field.
- Vietnamese terms keep their diacritics by default.
- English terms are matched case-insensitively.
- Mixed-language phrases are supported as plain Unicode strings.
- Normalization and word boundaries use Unicode-aware handling, not an English-only regex.
- Accent folding remains opt-in per entry because it is unsafe for Vietnamese.
- User-facing errors follow the application locale (`APP_LOCALE`); the Vietnamese/English messages in the specs are examples. If localization is implemented, place messages in `lang/en/moderation.php` and `lang/vi/moderation.php` with `en` as fallback.

### 3.6. Valid overrides

- An Admin can **disable** an entry when special content requires it (for example, an article about language).
- There is no role-based bypass in code. Every request goes through the same validation.
- Kill switch: `TOXIC_WORDS_ENABLED=false` in config/env.

---

## 4. Architecture

```text
HTTP request / AI draft
    -> FormRequest / DraftPostCreator
    -> ToxicWordFilter::check(value, scope)
    -> ToxicWordMatcher (normalize + compiled patterns, cached)
    -> ToxicWordRepository (active terms, cached)
    -> match?
         ├── yes -> ValidationException / DomainException
         └── no  -> continue saving as before
```

### 4.1. Components

| Component | Responsibility |
|---|---|
| `ToxicWord` model | Stores entries, casts `scopes` |
| `ToxicWordRepository` | Loads active terms by scope, caching and fallback |
| `ToxicWordNormalizer` | Normalizes Unicode, case, invisible characters, leetspeak |
| `ToxicWordMatcher` | Compiles patterns and matches text |
| `ToxicWordFilter` | High-level API: `check()`, `matches()`, `assertClean()` |
| `CleanContent` rule | Laravel Validation adapter |
| `AdminToxicWordController` | Dictionary CRUD |
| `ScanToxicWords` command | Read-only scan of existing content |

Matching logic must not live in controllers, model events, or Blade templates.

---

## 5. Data and schema

### 5.1. `toxic_words` table

```text
id                 bigint unsigned, PK
term               varchar(100)        -- original Admin input
normalized_term    varchar(100)        -- normalized form, unique
scopes             json                -- ["post","tag","category","comment","user_name"]
match_mode         varchar(10)         -- WORD | LOOSE, default WORD
fold_diacritics    boolean             -- default false
is_active          boolean             -- default true
note               varchar(255), null  -- internal note
created_by         bigint unsigned, null FK users, nullOnDelete
updated_by         bigint unsigned, null FK users, nullOnDelete
created_at         timestamp
updated_at         timestamp
```

Indexes:

- `unique(normalized_term)`.
- `index(is_active)`.
- Do not index JSON `scopes`; filter by scope in memory after caching.

Constraints:

- `normalized_term` is generated by the server, never accepted from the request.
- `term` is 2–100 characters after trimming.
- `scopes` has at least one item, each from the allowed scope list.
- `match_mode` is `WORD` or `LOOSE`.
- `LOOSE` is valid only when `mb_strlen(normalized_term) >= 4`.
- No duplicate `normalized_term`; return the error on `term`.

### 5.2. Model

`App\Models\ToxicWord`:

- `$fillable`: `term`, `normalized_term`, `scopes`, `match_mode`, `fold_diacritics`, `is_active`, `note`, `created_by`, `updated_by`.
- Casts: `scopes => array`, `fold_diacritics => boolean`, `is_active => boolean`.
- `saved`/`deleted` events increment a database-backed cache version.
- `active()` query scope.

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
        // Seed list; Admins can edit or disable individual entries in the database.
    ],
];
```

- `baseline` contains the initial seed list and is also the fallback when cache/DB fails.
- After seeding, the real list is managed by Admins in the database.
- Never hard-code toxic words in Blade, controllers, or public JS.

### 5.4. Seeder

`database/seeders/ToxicWordSeeder.php`:

- Reads `config('toxic_words.baseline')`.
- `updateOrCreate` by `normalized_term`.
- Does not re-enable entries disabled by an Admin and does not overwrite Admin-edited `scopes`/`match_mode`.
- Only inserts missing entries.

---

## 6. Normalization and matching

### 6.1. Text normalization

Processing order:

1. Normalize to Unicode NFC when `intl` is available; otherwise keep as-is.
2. Lowercase with `mb_strtolower`.
3. Remove invisible characters: zero-width space, zero-width joiner, BOM, bidi controls.
4. Collapse consecutive whitespace to one space.
5. Trim.
6. `LOOSE` only: replace common leetspeak (`0→o`, `1→i`, `3→e`, `4→a`, `5→s`, `7→t`, `@→a`, `$→s`), then build a compact form by removing every non-letter/non-digit character.
7. Only for entries with `fold_diacritics = true`: strip diacritics with `Str::ascii`.

Note: steps 6 and 7 apply to **both the term and the text**. Do not fold text when the entry does not enable `fold_diacritics`.

### 6.2. `WORD` mode (default)

- Match the whole word/phrase against normalized text.
- Unicode word boundaries:

```regex
(?<![\p{L}\p{N}])TERM(?![\p{L}\p{N}])
```

- Case-insensitive.
- Punctuation, hyphens, and spaces are not skipped.
- Suitable for short terms and Vietnamese words with diacritics.

### 6.3. `LOOSE` mode

Catches simple evasion:

- `bad-word` → `badword`.
- `b a d w o r d` → `badword`.
- `b4dw0rd` → `badword`.
- `bad---word` → `badword`.
- Characters repeated 3+ times are collapsed: `baaadword` → `badword`.

Safety rules:

- Only for normalized terms with length ≥ 4.
- Match on the compact form.
- Keep a boundary check on the original text where possible; otherwise flag the mode as false-positive-prone and require Admin confirmation when enabling it.
- Never enable `LOOSE` automatically for all terms.

### 6.4. Test examples with the neutral term `badword`

| Input | WORD | LOOSE | Note |
|---|---|---|---|
| `badword` | match | match | basic |
| `bad word` | no | no | spaces are not removed in WORD |
| `bad-word` | no | match | LOOSE ignores separator |
| `b a d w o r d` | no | match | LOOSE ignores spaces |
| `b4dw0rd` | no | match | leetspeak |
| `badwords` | no | match | LOOSE is substring-based |
| `notbadwordhere` | no | match | LOOSE is substring-based; consider before adding a term |
| `BADWORD` | match | match | case-insensitive |

### 6.5. Known limitations

- Does not handle Unicode homoglyphs (e.g. Cyrillic lookalikes, combining marks).
- Does not inspect images, PDFs, or external embeds.
- Does not understand semantics; a word inside a quotation is still blocked.
- If valid content is blocked, an Admin disables that entry or uses the kill switch.

---

## 7. Use cases

### UC1 — Admin dictionary management

**Routes**

| Method | URI | Name |
|---|---|---|
| GET | `/admin/toxic-words` | `admin.toxic-words.index` |
| POST | `/admin/toxic-words` | `admin.toxic-words.store` |
| PUT | `/admin/toxic-words/{toxicWord}` | `admin.toxic-words.update` |
| PATCH | `/admin/toxic-words/{toxicWord}/status` | `admin.toxic-words.update-status` |
| DELETE | `/admin/toxic-words/{toxicWord}` | `admin.toxic-words.destroy` |

All routes live in the existing `admin` group with `auth`, `auth.session`, and `EnsureUserIsAdmin`.

**Index page**

- Table: term (may be partially masked), scopes, mode, status, creator, last update, actions.
- Search by `term`.
- Filter by scope and status.
- Create/edit form:
  - `term` (required).
  - `scopes` (checkboxes, at least one required).
  - `match_mode` (`WORD`/`LOOSE`).
  - `fold_diacritics` (checkbox, off by default).
  - `note` (optional).
  - `is_active` (on by default).
- Quick enable/disable.
- Hard delete is allowed; use the existing confirm modal.

**Rules**

- Admin only.
- `term` is trimmed before validation.
- `normalized_term` is generated server-side; duplicates return `This term already exists in the dictionary.`
- Reject `LOOSE` with a term shorter than 4 characters.
- Every change clears the cache immediately.
- Do not log full sensitive terms.

**Acceptance**

- A new term blocks the next matching request in the correct scope.
- Disabling a term allows the corresponding content to be saved.
- Editing/deleting a term updates the cache immediately without a restart.
- A regular user receives `403` on admin routes.

### UC2 — Posts

**Applied in**

- `StoreArticleRequest`
- `UpdateArticleRequest`

**Fields**

- `title`: scope `post`.
- `slug`: scope `post`.
- `excerpt`: scope `post`.
- `markdown_content`: scope `post`.
- `new_tags.*`: scope `tag`.
- `tags.*` (existing tag IDs): not re-checked in this request; existing content is handled by UC7.

**Rules**

- Validation runs before entering `DB::transaction`.
- If any field matches, return an error on that field and save nothing.
- Thumbnail and other fields are unaffected.
- On update, existing content containing a toxic word remains blocked until an Admin fixes it; this is intentional.
- New tags in the post form are checked before `resolveTagIds` runs.

**Messages**

- `post.title`: `Title contains inappropriate language. Please revise it.`
- `post.excerpt`: `Excerpt contains inappropriate language. Please revise it.`
- `post.markdown_content`: `Post content contains inappropriate language. Please revise it.`
- `post.slug`: `Slug contains inappropriate language. Please revise it.`
- `tag`: `Tag name contains inappropriate language. Please revise it.`

**Acceptance**

- Creating a post with a matching title is blocked: no `Post` is created, input is preserved, and the error appears under the title.
- A clean post saves normally.
- A post with a new toxic tag creates neither the post nor the tag.
- Updating a clean post works normally.

### UC3 — Tags and categories

**Tags**

- `StoreTagRequest` and `UpdateTagRequest`.
- Check `name` and `slug` with scope `tag`.
- Check before the tag-reuse logic in `TagController::store`; never return `reused=true` for a toxic tag.

**Categories**

- `StoreCategoryRequest` and `UpdateCategoryRequest`.
- Check `name`, `slug`, and `description` with scope `category`.
- Check before the parent-loop validation and before writing.

**Acceptance**

- No tag/category can be created with a term in any checked field.
- Clean tags/categories behave as before.
- Existing JSON responses are unchanged except for `422` on violations.

### UC4 — Comments

**Applied in**

- `StoreCommentRequest`
- `UpdateCommentRequest`

**Field**

- `content`: scope `comment`.

**Rules**

- Run after content is trimmed, before the transaction/idempotency logic.
- A violating request returns `422`, creates no comment, and does not bump the version.
- A violating update returns `422` and changes nothing.
- Existing throttling and idempotency behavior remain intact.
- Existing comments are not automatically hidden; UC7 supports review.

**Acceptance**

- A comment containing a term is blocked with `422` and an error on `content`.
- A clean comment is still created.
- Updating a clean comment keeps the existing version and optimistic-lock behavior.
- Two concurrent requests with the same idempotency key create no comment if both violate the rules.

### UC5 — User names

**Applied in**

- `RegisterRequest`: `name`.
- `Admin\StoreUserRequest`: `name`.
- `Admin\UpdateUserRequest`: `name`.

**Rules**

- Scope `user_name`.
- Email and password are not checked.
- Violations return an error on `name`.

**Acceptance**

- Registering with a matching name creates no user.
- Admin user create/update with a valid name works as before.
- Existing users with matching names can still log in; only name updates are blocked.

### UC6 — AI Weekly draft

**Applied in**

- `App\AiWeekly\DraftPostCreator::create()`.

**Rules**

- Check `title`, `excerpt`, and `markdown_content` with scope `post` before `Post::create`.
- On violation, throw `ToxicContentException` (extends `DomainException`) with:
  `Weekly Review content contains inappropriate language. Please revise the review before converting it to a post.`
- The exception carries `moderation` so the review page can highlight exact positions in yellow.
- `WeeklyReviewController::convert` already catches `DomainException` and returns `409`; keep the status and add `moderation` to the JSON.
- Create no Post and do not change the review status.
- Do not check suggested tag names because tags are synced by existing names; those are existing content and belong to UC7.

**Acceptance**

- A clean review converts as before.
- A violating review returns `409`, creates no Post, and leaves the review state unchanged.
- Converting again after editing the review succeeds.

### UC7 — Existing content scan

**Command**

```bash
php artisan toxic-words:scan --scope=post --limit=100
php artisan toxic-words:scan --scope=comment --since=2026-01-01
```

**Behavior**

- Read-only; no edits or deletes.
- Supports `--scope=post|tag|category|comment|user_name`, repeatable.
- Supports `--limit` (default 500) and `--since`.
- Prints: `id`, `scope`, `field`, `term id`, `term` (truncated), `record id`, `title/name`.
- Never prints full content.
- Exits normally even when matches exist; a non-zero exit code is reserved for command errors.
- Not scheduled automatically.

**Acceptance**

- Running on sample data returns the correct list.
- No database changes occur.
- Current requests are not slowed down.

---

## 8. API and UI contract

### 8.1. Validation error

Comment example:

```json
{
  "message": "Comment contains inappropriate language. Please revise it before posting.",
  "errors": {
    "content": [
      "Comment contains inappropriate language. Please revise it before posting."
    ]
  }
}
```

- HTTP status: `422`.
- Public requests (comments, registration): return no matched term, match position, or dictionary list.
- Admin requests: may include `moderation` with highlight HTML and a list of violating fields; never the original term.
- Admin forms: redirect back, `@error` under the relevant field; highlighting is rendered from `moderation`.

### 8.2. Admin index response

Support both HTML and JSON like `CategoryController`:

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

- JSON may include the full `term` because this is an Admin screen.
- Never expose the dictionary in public pages or public JS.

### 8.3. Navigation

- Add **Toxic Words** to the Taxonomy group in `resources/views/layouts/admin.blade.php`.
- Use an existing Material Symbols icon, e.g. `gpp_bad` or `shield`.
- Do not change other layout areas.

### 8.4. Highlighting toxic words in the editor and review

#### Why Markdown cannot be "colored"

Markdown is plain text. Browsers cannot style a substring inside a `<textarea>` or `<input type="text">`. Color only exists in the **rendered HTML layer**. Therefore:

- Do not modify the source Markdown.
- Do not inject HTML tags into stored Markdown.
- Highlighting is a **presentation layer** over the editor and preview, present only after a failed submit.

#### Options considered

| Option | Pros | Cons | Decision |
|---|---|---|---|
| Preview-only highlight + error list | Simple, no layout drift, little JS | No yellow highlight in the Write tab | Fallback |
| **Mirror overlay behind textarea + preview highlight** | Yellow directly in the editor, no Markdown change, no new dependency | Must sync font, padding, line-height, and scroll; needs careful testing | **Chosen** |
| Rich editor / CodeMirror / Monaco | Robust highlighting and editor features | New dependency, editor UX change, larger bundle and Markdown risk | Not in MVP |

Recommendation: **overlay for textareas + highlighting in preview/review**. If time is tight, ship the preview + error list first; the overlay is an enhancement.

#### `moderation` payload

Only returned on admin routes. Example `422` when submitting a post:

```json
{
  "message": "Post content contains inappropriate language. Please revise it.",
  "errors": {
    "markdown_content": [
      "Post content contains inappropriate language. Please revise it."
    ]
  },
  "moderation": {
    "summary": "Found 2 inappropriate terms in 1 field.",
    "fields": {
      "markdown_content": {
        "count": 2,
        "overlay_html": "&lt;p&gt;This text has &lt;mark class=\"toxic-word-hit\" data-term-id=\"12\"&gt;badword&lt;/mark&gt; to fix.&lt;/p&gt;",
        "preview_html": "<p>This text has <mark class=\"toxic-word-hit\" data-term-id=\"12\">badword</mark> to fix.</p>"
      }
    }
  }
}
```

Payload rules:

- `overlay_html`: the entire text **HTML-escaped**, then matches wrapped in `<mark class="toxic-word-hit" data-term-id="...">`. Preserve Markdown characters so it mirrors the textarea exactly.
- `preview_html`: safely rendered Markdown (`Str::markdown` with `html_input=strip`, `allow_unsafe_links=false`) with highlights in text nodes.
- `count`: number of matches in the field.
- `summary`: human-readable sentence for the banner and screen readers.
- Never return the original term, dictionary list, or patterns.
- `preview_html` must be sanitized; never inject user HTML directly.
- For single-line fields (`title`, `slug`, `excerpt` inputs): return `snippets` instead of an overlay:

```json
{
  "snippets": [
    "…text with <mark class=\"toxic-word-hit\">badword</mark> to fix…"
  ]
}
```

#### Submit flow

**Regular forms (posts, categories, tags)**

1. `FormRequest` validation throws `ToxicContentException`.
2. Exception rendering redirects back with `errors`, old input, and `moderation`.
3. Blade puts `moderation` into the form/editor `data-moderation` attribute.
4. `toxic-highlight.js` reads the payload and renders the overlay and preview highlights.
5. When the user edits content, old highlights are cleared and a small hint appears:
   `Content changed — submit again to re-check.`
6. Re-submit: the server checks from scratch.

**AJAX (AI Weekly review, convert to post)**

1. `ai-weekly.js` receives `422`/`409` with `moderation`.
2. It calls `window.applyToxicHighlights(form, moderation)`.
3. If `preview_html` exists for `markdown_content`, replace the current preview HTML with the highlighted HTML.
4. Show `summary` in `[data-ai-feedback]`; do not reload or lose edits.
5. A failed `convert` returns `409` with `moderation`; the review state is unchanged.

**Public requests (comments, registration)**

- Return only `422` and a generic message.
- Return no `moderation`, no highlight, no positions or keywords.

#### Textarea overlay

Markup:

```html
<div class="toxic-editor" data-toxic-editor>
    <div class="toxic-editor__highlight" data-toxic-highlight aria-hidden="true"></div>
    <textarea name="markdown_content" data-toxic-input>...</textarea>
</div>
```

Technical requirements:

- The overlay sits behind the textarea with identical size, padding, border, font-family, font-size, line-height, letter-spacing, and `tab-size`.
- Overlay uses `white-space: pre-wrap; overflow-wrap: break-word;` and `overflow: hidden`.
- When highlights exist, textarea text becomes `color: transparent; caret-color: #fff;`; the overlay renders the visible content.
- Without JS or `moderation`, the textarea displays normally and the overlay is hidden.
- Sync `scrollTop`/`scrollLeft` on scroll and resize.
- On `input`, clear the overlay and disable the transparent state to avoid misaligned highlights.
- Do not use `contenteditable`; do not change the value submitted to the server.
- Support IME and mobile: test paste, Vietnamese typing, and undo/redo.

#### Preview and review highlighting

- Posts: the existing preview uses `marked` + `DOMPurify`. After a failed submit, use the server-provided `preview_html` for the submitted state; when the user edits, return to the normal client-side preview and clear old highlights.
- Review: `WeeklyReviewController::show` already renders `$previewHtml`; add server-side highlighting before passing it to the view. On an AJAX `moderation` response, replace the preview with `preview_html`.
- HTML highlighting method: walk text nodes (skip `script` and `style`), run the matcher on each text node, and wrap matches with `<mark class="toxic-word-hit" data-term-id="...">`.
- MVP highlights within a single text node. If Markdown splits a term across nodes (e.g. `bad**word**`), it is still blocked but may not be highlighted; document this known limitation.

#### CSS and accessibility

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

- Do not rely on color alone: add the dotted underline and a summary banner.
- Banner: `Found N inappropriate terms in M fields.` with a **Jump to** button.
- **Jump to** sets the textarea selection at the match and scrolls to it; preview scrolls to the first `mark`.
- Use `<mark>` so screen readers understand it as a highlight.
- The field's `aria-describedby` points to the error message and banner.
- Do not show the term in tooltips; the tooltip only says `Inappropriate language`.

#### Expected files

- `app/Support/Moderation/ToxicContentException.php`
- `app/Support/Moderation/ToxicWordHighlighter.php`
- `app/Http/Requests/Concerns/ValidatesToxicContent.php`
- `resources/js/admin/toxic-highlight.js`
- `resources/css/admin.css`
- `resources/views/admin/articles/_form.blade.php`
- `resources/views/admin/ai-weekly/review.blade.php`
- `resources/js/admin/ai-weekly.js`

---

## 9. Caching and performance

- Load only `is_active = true` entries.
- Cache the normalized list under `config('toxic_words.cache_key')` plus a database-backed version, with a 600-second TTL.
- `saved` and `deleted` model events increment the database version, so an old cache entry is not read again even if the cache store failed during invalidation.
- The matcher compiles patterns once per cache miss; store compiled patterns in cache when the serializer supports it, otherwise recompile from the cached list.
- Separate patterns by:
  - `WORD` with diacritics.
  - `WORD` without diacritics.
  - `LOOSE` with diacritics.
  - `LOOSE` without diacritics.
- Each check runs at most two patterns (one for the entry's mode, one for folding if needed).
- If no active terms exist for the scope, return immediately without running regex.
- Soft `max_terms` limit:
  - If exceeded, log `critical` but still load everything to avoid silent gaps.
  - Never silently truncate the list.
- Post content is capped at 200,000 characters. Benchmark with 1,000 terms; if a normal request exceeds 50ms, split content into 20,000-character chunks and stop at the first match.
- Do not add a Redis dependency; use the existing cache store.

**Fallback**

- If loading the dictionary fails:
  - Log `error`.
  - Use the normalized `config('toxic_words.baseline')`.
  - If the baseline is empty, allow the request and log `warning` (fail-open) so an infrastructure failure does not block the whole application.
- Do not cache a negative `false` check result.

---

## 10. Security, privacy, and observability

### 10.1. Authorization

- Dictionary management: Admin only.
- Do not expose dictionary routes or data through public APIs.
- Never accept `normalized_term` from clients.

### 10.2. Logging

On a block, write a structured log:

```text
event=toxic_words.blocked
scope=comment
field=content
term_id=12
user_id=45
route=articles.comments.store
content_hash=<first 12 chars of sha256>
```

- Do not log full content.
- Do not log the original term at `info` level; log `term_id` only.
- Do not log passwords, emails, or tokens.
- A dedicated `moderation` channel can be added later; MVP uses the default channel.

### 10.3. Kill switch

- `TOXIC_WORDS_ENABLED=false` skips all checks while keeping management routes available.
- Do not log on every skipped request; avoid spam.

---

## 11. Test plan

### 11.1. Unit

`tests/Unit/ToxicWordMatcherTest.php`:

- Lowercase, whitespace, and zero-width normalization.
- `WORD` matches on exact word boundaries.
- `WORD` does not match substrings.
- `LOOSE` matches `bad-word`, `b a d w o r d`, `b4dw0rd`, and repeated characters.
- Highlighter: escapes HTML correctly, wraps every match, and preserves Markdown characters in `overlay_html`.
- Highlighter: `preview_html` contains only `<mark>`, no `<script>`, and no original term outside the submitted content.
- Highlighter: matches text nodes and skips `script`/`style`.
- Validation rejects `LOOSE` terms shorter than 4 characters.
- Diacritics are preserved by default: term `từcấm` does not match `tucam`.
- `fold_diacritics=true`: term `từcấm` matches `tucam`.
- Does not block `các` when the term is `cac` and `fold_diacritics=false`.
- Scope isolation: a `comment`-only term does not block `post`.
- Inactive terms do not block.
- Empty/null text is clean.

### 11.2. Feature

`tests/Feature/AdminToxicWordTest.php`:

- Guests are redirected; regular users get `403`.
- Admin can view HTML/JSON lists.
- Creating a term works and normalizes correctly.
- Duplicate terms return an error.
- `LOOSE` with a short term returns an error.
- Update, toggle status, and delete work.
- Cache is cleared after changes (create a term, then the next request is blocked immediately).

Extend existing tests:

- `AdminArticleTest`: title, excerpt, content, slug, and new tag violations; assert no records are created; the response/redirect contains `moderation` with escaped `overlay_html` and sanitized `preview_html`.
- `AdminArticleTest`: a violating public comment contains no `moderation` key.
- `AdminArticleTest`: converting a violating review to a Post returns `409` with `moderation`.
- Browser/manual: overlay alignment on scroll, resize, Vietnamese typing, and multi-line paste; highlights clear when content changes.
- `AdminTagTest`: create/update violations.
- `AdminCategoryTest`: create/update violations.
- Comment feature tests: create/update violations return `422` and change no data; clean comments still work.
- Auth/register test: name violation.
- `AdminUserTest` (if present): name violation.
- AI Weekly test: `DraftPostCreator` throws `DomainException` and creates no Post; convert returns `409`.
- Seeder test: running twice creates no duplicates and does not re-enable disabled terms.

### 11.3. Test data

- Use neutral terms: `badword`, `từcấm`, `cumulative` (to test substrings).
- Do not put real profanity into fixtures, seeders, or logs.
- `ToxicWordFactory` supports `active`, `inactive`, `loose`, and `folded` states.

### 11.4. Verification commands

```bash
php artisan test --compact tests/Unit/ToxicWordMatcherTest.php
php artisan test --compact tests/Feature/AdminToxicWordTest.php
vendor/bin/pint --dirty --format agent
```

---

## 12. Acceptance criteria

1. Admins can manage the dictionary: create, edit, enable/disable, delete, search, and filter by scope.
2. New terms take effect immediately without a restart or manual cache clear.
3. Posts, tags, categories, comments, and user names containing a term in their scope cannot be created or updated.
4. Violating AI Weekly drafts cannot be converted to Posts and the review remains unchanged.
5. Clean content behaves exactly as before, with no changes to existing success responses.
6. `các` is not blocked when the term is `cac` under the default configuration.
7. Error messages do not reveal the matched term.
8. The kill switch disables all checks.
9. The existing-content scan command is read-only and changes no data.
10. A failed post/review submit highlights exact positions in yellow in the editor, preview, and review; editing content clears old highlights.
11. Public requests return no `moderation` and expose no positions or keywords.
12. New tests and affected existing tests pass; Pint reports no issues.

---

## 13. Implementation plan

| Step | Work | Expected files |
|---|---|---|
| 1 | Migration, model, factory | `database/migrations/*_create_toxic_words_table.php`, `app/Models/ToxicWord.php`, `database/factories/ToxicWordFactory.php` |
| 2 | Config, seeder | `config/toxic_words.php`, `database/seeders/ToxicWordSeeder.php` |
| 3 | Normalization and matching | `app/Support/Moderation/ToxicWordNormalizer.php`, `ToxicWordMatcher.php`, `ToxicWordFilter.php`, `ToxicWordRepository.php`, `ToxicWordMatch.php` |
| 4 | Validation rule | `app/Rules/CleanContent.php` |
| 5 | Admin CRUD | `app/Http/Controllers/Admin/ToxicWordController.php`, `app/Http/Requests/Admin/StoreToxicWordRequest.php`, `UpdateToxicWordRequest.php`, `resources/views/admin/toxic-words/index.blade.php` |
| 6 | Routes, navigation | `routes/web.php`, `resources/views/layouts/admin.blade.php` |
| 7 | Request integration | `StoreArticleRequest`, `UpdateArticleRequest`, `StoreTagRequest`, `UpdateTagRequest`, `StoreCategoryRequest`, `UpdateCategoryRequest`, `StoreCommentRequest`, `UpdateCommentRequest`, `RegisterRequest`, `StoreUserRequest`, `UpdateUserRequest` |
| 8 | AI Weekly | `app/AiWeekly/DraftPostCreator.php` |
| 9 | Existing-content scan | `app/Console/Commands/ScanToxicWords.php` |
| 10 | Editor/review highlighting | `ToxicContentException`, `ToxicWordHighlighter`, `ValidatesToxicContent`, `resources/js/admin/toxic-highlight.js`, `resources/css/admin.css`, `_form.blade.php`, `review.blade.php`, `ai-weekly.js` |
| 11 | Tests and Pint | `tests/Unit/ToxicWordMatcherTest.php`, `tests/Feature/AdminToxicWordTest.php`, related existing feature tests |

**Priority order:** 1 → 3 → 4 → 7 → 2 → 5 → 6 → 8 → 9 → 10 → 11.

No separate audit table in MVP; structured logs are sufficient for monitoring and tuning the dictionary.
