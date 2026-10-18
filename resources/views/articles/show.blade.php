@extends('layouts.app')

@section('title', e($article->title).' · Apollo Blog')

@section('content')
    <article class="mx-auto max-w-4xl">
        <a class="inline-flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-[0.12em] text-on-surface-variant transition-colors hover:text-primary" href="{{ route('home') }}#articles">
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_back</span>
            Back to articles
        </a>

        <header class="mt-8 border-b border-outline-variant/30 pb-8">
            <div class="flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-[0.1em]">
                <span class="inline-flex items-center gap-1.5 rounded bg-tertiary/10 px-2 py-0.5 text-tertiary">
                    <span class="size-1.5 rounded-full bg-tertiary"></span>
                    Published
                </span>
                @if ($article->category)
                    <span class="text-outline-variant">{{ $article->category->name }}</span>
                @endif
                <span class="text-outline-variant">·</span>
                <span class="text-outline">{{ $article->published_at?->format('M d, Y') }}</span>
            </div>

            <h1 class="mt-5 font-display text-4xl font-bold leading-tight tracking-[-0.035em] text-on-surface sm:text-5xl">
                {{ $article->title }}
            </h1>

            @if ($article->excerpt)
                <p class="mt-5 max-w-3xl text-lg leading-8 text-on-surface-variant">{{ $article->excerpt }}</p>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3 font-mono text-[10px] uppercase tracking-[0.1em] text-on-surface-variant">
                <span class="inline-flex items-center gap-2">
                    <span class="flex size-6 items-center justify-center rounded-full bg-primary/15 text-primary">{{ str($article->author?->name ?? 'A')->substr(0, 1)->upper() }}</span>
                    {{ $article->author?->name ?? 'Apollo Editorial' }}
                </span>
                <span class="text-outline-variant">·</span>
                <span>{{ number_format($article->view_count) }} views</span>
            </div>
        </header>

        @if ($article->thumbnail)
            <img class="mt-8 max-h-[30rem] w-full rounded-xl object-cover shadow-xl shadow-black/20" src="{{ $article->thumbnail->url }}" alt="{{ $article->title }}">
        @endif

        <div class="mt-8 text-base leading-8 text-on-surface-variant [&_a]:text-primary [&_a]:underline [&_blockquote]:my-6 [&_blockquote]:border-l-2 [&_blockquote]:border-primary [&_blockquote]:pl-5 [&_code]:rounded [&_code]:bg-surface-high [&_code]:px-1.5 [&_code]:py-0.5 [&_h1]:mb-5 [&_h1]:font-display [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:text-on-surface [&_h2]:mb-4 [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-on-surface [&_h3]:mb-3 [&_h3]:mt-6 [&_h3]:font-display [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-on-surface [&_li]:my-1 [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-5 [&_pre]:my-6 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-surface-low [&_pre]:p-4 [&_pre]:text-sm [&_pre]:leading-6 [&_strong]:font-semibold [&_strong]:text-on-surface [&_ul]:list-disc">
            {!! $content !!}
        </div>

        @if ($article->tags->isNotEmpty())
            <footer class="mt-10 flex flex-wrap gap-2 border-t border-outline-variant/30 pt-5">
                @foreach ($article->tags as $tag)
                    <span class="rounded bg-surface-high px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.08em] text-on-surface-variant">#{{ $tag->name }}</span>
                @endforeach
            </footer>
        @endif
    </article>

    <section
        id="comments"
        class="mx-auto mt-14 max-w-4xl border-t border-outline-variant/30 pt-10"
        x-data="articleComments(@js([
            'indexUrl' => route('articles.comments.index', ['article' => $article->slug]),
            'storeUrl' => route('articles.comments.store', ['article' => $article->slug]),
            'repliesUrlTemplate' => route('articles.comments.replies', ['article' => $article->slug, 'comment' => '__COMMENT__']),
            'updateUrlTemplate' => route('articles.comments.update', ['article' => $article->slug, 'comment' => '__COMMENT__']),
            'deleteUrlTemplate' => route('articles.comments.destroy', ['article' => $article->slug, 'comment' => '__COMMENT__']),
            'authenticated' => auth()->check(),
            'currentUserId' => auth()->id(),
            'loginUrl' => route('login', ['redirect' => url()->current().'#comments']),
        ]))"
        x-init="init()"
    >
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-[0.14em] text-primary">Community</p>
                <h2 class="mt-2 font-display text-2xl font-semibold text-on-surface">Comments</h2>
            </div>
            <span class="font-mono text-[10px] uppercase tracking-[0.12em] text-on-surface-variant" x-text="summaryLabel()"></span>
        </div>

        <div class="mt-6 rounded-xl border border-outline-variant/40 bg-surface-low p-5 sm:p-6">
            <template x-if="!authenticated">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-display text-base font-semibold text-on-surface">Join the conversation</p>
                        <p class="mt-1 text-sm leading-6 text-on-surface-variant">Sign in to share your perspective or reply to another reader.</p>
                    </div>
                    <a class="inline-flex shrink-0 items-center justify-center rounded-md bg-primary px-4 py-2.5 font-display text-sm font-semibold text-on-primary transition-colors hover:bg-surface-tint" :href="loginUrl">Sign in to comment</a>
                </div>
            </template>

            <template x-if="authenticated">
                <form @submit.prevent="submitComment()">
                    <label class="font-mono text-[10px] uppercase tracking-[0.12em] text-on-surface-variant" for="comment-content">Add a comment</label>
                    <textarea
                        id="comment-content"
                        class="mt-3 min-h-28 w-full resize-y rounded-lg border border-outline-variant/60 bg-background px-3 py-3 text-sm leading-6 text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        x-model="draft"
                        maxlength="2000"
                        placeholder="Share a thoughtful response..."
                        :disabled="submitting === 'root'"
                        @keydown.meta.enter.prevent="submitComment()"
                        @keydown.ctrl.enter.prevent="submitComment()"
                    ></textarea>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-error" x-cloak x-show="formErrors.root" x-text="formErrors.root"></p>
                        <p class="text-xs text-emerald-300" x-cloak x-show="successMessages.root" x-text="successMessages.root"></p>
                        <span class="ml-auto font-mono text-[10px] text-on-surface-variant" x-text="`${draft.length}/2000`"></span>
                    </div>
                    <button class="mt-4 inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2.5 font-display text-sm font-semibold text-on-primary transition-colors hover:bg-surface-tint disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="submitting === 'root' || !draft.trim()">
                        <span class="material-symbols-outlined text-[17px]" aria-hidden="true">send</span>
                        <span x-text="submitting === 'root' ? 'Posting...' : 'Post comment'"></span>
                    </button>
                </form>
            </template>
        </div>

        <div class="mt-8" aria-live="polite">
            <div class="space-y-5" x-cloak x-show="loading">
                <div class="h-28 animate-pulse rounded-xl bg-surface-high"></div>
                <div class="h-28 animate-pulse rounded-xl bg-surface-high"></div>
            </div>

            <div class="rounded-xl border border-outline-variant/30 bg-surface-low px-5 py-8 text-center" x-cloak x-show="!loading && error">
                <p class="font-display text-base font-semibold text-on-surface">Comments could not be loaded.</p>
                <p class="mt-1 text-sm text-on-surface-variant">Please check your connection and try again.</p>
                <button class="mt-4 rounded-md border border-outline-variant px-4 py-2 font-mono text-[10px] uppercase tracking-[0.12em] text-on-surface-variant transition-colors hover:border-primary hover:text-primary" type="button" @click="loadComments()">Try again</button>
            </div>

            <div class="rounded-xl border border-outline-variant/30 bg-surface-low px-5 py-8 text-center" x-cloak x-show="!loading && !error && comments.length === 0">
                <p class="font-display text-base font-semibold text-on-surface">Be the first to join the conversation.</p>
                <p class="mt-1 text-sm text-on-surface-variant">Thoughtful comments make the article better for everyone.</p>
            </div>

            <div class="space-y-1" x-cloak x-show="!loading && !error && comments.length > 0">
                <template x-for="comment in comments" :key="`comment-${comment.id}`">
                    <article
                        class="relative py-3 transition-all first:pt-0"
                        :class="comment.depth === 0 ? 'border-b border-outline-variant/30' : 'border-l-2 border-primary/20 pl-4 sm:pl-5'"
                        x-cloak
                        x-show="isVisible(comment)"
                        :style="{ marginLeft: `${Math.min(comment.depth, 5) * 1}rem` }"
                    >
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/15 font-display text-xs font-semibold text-primary" x-text="comment.author.initial"></span>
                            <div class="flex min-w-0 items-center gap-3">
                                <p class="truncate font-display text-xs font-semibold text-on-surface" x-text="comment.author.name"></p>
                                <span class="text-outline-variant">·</span>
                                <p class="font-mono text-[9px] uppercase tracking-[0.08em] text-on-surface-variant" :title="comment.created_at" x-text="comment.created_at_human"></p>
                            </div>
                            <div class="relative ml-auto" x-show="isOwnComment(comment)" @click.outside="openMenu = null">
                                <button class="flex size-7 items-center justify-center rounded-md text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" type="button" aria-label="Comment actions" :aria-expanded="openMenu === comment.id" @click.stop="toggleMenu(comment.id)">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">more_horiz</span>
                                </button>
                                <div class="absolute right-0 top-8 z-20 min-w-28 rounded-lg border border-outline-variant/60 bg-surface-high p-1 shadow-xl" x-cloak x-show="openMenu === comment.id">
                                    <button class="flex w-full rounded-md px-3 py-2 text-left text-xs text-on-surface transition-colors hover:bg-surface-highest hover:text-primary" type="button" @click="startEditing(comment)">Edit</button>
                                    <button class="flex w-full rounded-md px-3 py-2 text-left text-xs text-error transition-colors hover:bg-surface-highest" type="button" @click="deleteComment(comment)">Delete</button>
                                </div>
                            </div>
                        </div>

                        <template x-if="editing !== comment.id">
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-on-surface-variant" x-text="comment.content"></p>
                        </template>

                        <template x-if="editing === comment.id">
                            <form class="mt-3" @submit.prevent="saveEdit(comment)">
                                <textarea class="min-h-20 w-full resize-y rounded-lg border border-outline-variant/60 bg-background px-3 py-3 text-sm leading-6 text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20" x-model="editDrafts[comment.id]" maxlength="2000" :disabled="editSubmitting === comment.id"></textarea>
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-xs text-error" x-show="editErrors[comment.id]" x-text="editErrors[comment.id]"></p>
                                    <div class="ml-auto flex items-center gap-3">
                                        <button class="font-mono text-[10px] uppercase tracking-[0.1em] text-on-surface-variant hover:text-primary" type="button" @click="cancelEditing()">Cancel</button>
                                        <button class="rounded-md bg-primary px-3 py-2 font-mono text-[10px] uppercase tracking-[0.1em] text-on-primary transition-colors hover:bg-surface-tint disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="editSubmitting === comment.id || !(editDrafts[comment.id] || '').trim()" x-text="editSubmitting === comment.id ? 'Saving...' : 'Save'"></button>
                                    </div>
                                </div>
                            </form>
                        </template>

                        <div class="mt-2 flex flex-wrap items-center gap-3" x-show="editing !== comment.id">
                            <button class="font-mono text-[10px] uppercase tracking-[0.1em] text-on-surface-variant transition-colors hover:text-primary" type="button" @click="startReply(comment)">Reply</button>
                            <span class="text-outline-variant" x-show="comment.replies_count > 0">·</span>
                            <button class="inline-flex items-center gap-1 font-mono text-[10px] uppercase tracking-[0.1em] text-on-surface-variant transition-colors hover:text-primary" type="button" x-show="comment.replies_count > 0" @click="toggleReplies(comment)">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true" x-text="comment.expanded ? 'keyboard_arrow_up' : 'keyboard_arrow_down'"></span>
                                <span x-text="comment.expanded ? 'Hide replies' : `View ${comment.replies_count} ${comment.replies_count === 1 ? 'reply' : 'replies'}`"></span>
                            </button>
                        </div>

                        <div class="mt-3 space-y-2" x-cloak x-show="comment.expanded">
                            <div class="text-xs text-on-surface-variant" x-show="comment.childrenLoading">Loading replies...</div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-error" x-cloak x-show="comment.childrenError">
                                <span x-text="comment.childrenError"></span>
                                <button class="font-mono text-[10px] uppercase tracking-[0.1em] text-primary hover:text-on-surface" type="button" @click="loadReplies(comment, comment.nextRepliesUrl)">Try again</button>
                            </div>
                            <button class="font-mono text-[10px] uppercase tracking-[0.1em] text-primary transition-colors hover:text-on-surface" type="button" x-show="comment.nextRepliesUrl && !comment.childrenLoading" @click="loadReplies(comment, comment.nextRepliesUrl)">Load more replies</button>
                        </div>

                        <p class="mt-2 text-xs text-emerald-300" x-cloak x-show="successMessages[comment.id]" x-text="successMessages[comment.id]"></p>
                        <form class="mt-3" x-cloak x-show="replyingTo === comment.id && authenticated" @submit.prevent="submitReply(comment)">
                            <label class="sr-only" :for="`reply-${comment.id}`">Reply to this comment</label>
                            <textarea :id="`reply-${comment.id}`" class="min-h-20 w-full resize-y rounded-lg border border-outline-variant/60 bg-background px-3 py-3 text-sm leading-6 text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20" x-model="replyDrafts[comment.id]" maxlength="2000" placeholder="Write a reply..." :disabled="submitting === comment.id"></textarea>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs text-error" x-show="formErrors[comment.id]" x-text="formErrors[comment.id]"></p>
                                <div class="ml-auto flex items-center gap-3">
                                    <button class="font-mono text-[10px] uppercase tracking-[0.1em] text-on-surface-variant hover:text-primary" type="button" @click="cancelReply()">Cancel</button>
                                    <button class="rounded-md bg-primary px-3 py-2 font-mono text-[10px] uppercase tracking-[0.1em] text-on-primary transition-colors hover:bg-surface-tint disabled:cursor-not-allowed disabled:opacity-60" type="submit" :disabled="submitting === comment.id || !(replyDrafts[comment.id] || '').trim()" x-text="submitting === comment.id ? 'Posting...' : 'Post reply'"></button>
                                </div>
                            </div>
                        </form>
                    </article>
                </template>
            </div>

            <div class="mt-6 text-center" x-cloak x-show="nextCommentsUrl && !loading && !error">
                <button class="rounded-md border border-outline-variant px-4 py-2.5 font-mono text-[10px] uppercase tracking-[0.12em] text-on-surface-variant transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="loadingMore" @click="loadMoreComments()" x-text="loadingMore ? 'Loading...' : 'Load more comments'"></button>
                <p class="mt-2 text-xs text-error" x-cloak x-show="loadMoreError" x-text="loadMoreError"></p>
            </div>
        </div>
    </section>
    <x-confirm-modal />
@endsection
