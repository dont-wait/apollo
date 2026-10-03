<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apollo - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-[#0b0f19] text-slate-200 flex min-h-screen font-sans antialiased">

    <!-- 1. SIDEBAR BÊN TRÁI -->
    <aside class="w-64 bg-[#070a12] border-r border-slate-800/80 flex flex-col justify-between p-4 shrink-0">
        <div>
            <!-- Brand -->
            <div class="flex items-center gap-3 px-3 py-3 mb-6">
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-bolt text-sm"></i>
                </div>
                <span class="text-base font-bold text-white tracking-wide">Apollo Admin</span>
            </div>

            <!-- Navigation Links -->
            <nav class="space-y-1 text-sm">
                <a href="/admin/dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-sky-600/20 text-sky-400 font-medium border border-sky-500/30">
                    <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Tab Articles (quản lý bài viết) -->
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 transition">
                    <i class="fa-regular fa-newspaper w-5 text-center"></i>
                    <span>Articles</span>
                </a>

                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 transition">
                    <i class="fa-solid fa-folder-tree w-5 text-center"></i>
                    <span>Categories</span>
                </a>

                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 transition">
                    <i class="fa-solid fa-users w-5 text-center"></i>
                    <span>Users</span>
                </a>

                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:bg-slate-800/60 hover:text-slate-200 transition">
                    <i class="fa-solid fa-sliders w-5 text-center"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <!-- Thông tin Admin -->
        <div class="border-t border-slate-800/80 pt-4 flex items-center gap-3 px-2">
            <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-sky-400">
                HT
            </div>
            <div class="text-xs min-w-0">
                <p class="font-medium text-slate-200 truncate">Hoàng Thời</p>
                <p class="text-slate-500">Administrator</p>
            </div>
        </div>
    </aside>

    <!-- 2. NỘI DUNG CHÍNH (MAIN CONTENT) -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <header class="h-16 border-b border-slate-800/80 px-8 flex items-center justify-between bg-[#070a12]/40 backdrop-blur shrink-0">
            <h1 class="text-sm font-semibold text-white">Dashboard Overview</h1>
            <div class="flex items-center gap-4">
                <a href="/" target="_blank" class="text-xs text-slate-400 hover:text-white flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem Website
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 space-y-6">
            <!-- Thống kê / Analytics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-[#111625] p-5 rounded-xl border border-slate-800/90 shadow-sm">
                    <div class="text-xs font-medium text-slate-400 mb-1">Tổng bài viết</div>
                    <div class="text-2xl font-bold text-white tracking-tight">48</div>
                    <div class="mt-2 text-xs text-emerald-400 flex items-center gap-1">
                        <i class="fa-solid fa-arrow-trend-up"></i> +12 bài tháng này
                    </div>
                </div>

                <div class="bg-[#111625] p-5 rounded-xl border border-slate-800/90 shadow-sm">
                    <div class="text-xs font-medium text-slate-400 mb-1">Tổng lượt xem (Views)</div>
                    <div class="text-2xl font-bold text-white tracking-tight">32,840</div>
                    <div class="mt-2 text-xs text-emerald-400 flex items-center gap-1">
                        <i class="fa-solid fa-arrow-trend-up"></i> +8.4%
                    </div>
                </div>

                <div class="bg-[#111625] p-5 rounded-xl border border-slate-800/90 shadow-sm">
                    <div class="text-xs font-medium text-slate-400 mb-1">Bình luận chờ duyệt</div>
                    <div class="text-2xl font-bold text-white tracking-tight">6</div>
                    <div class="mt-2 text-xs text-amber-400 flex items-center gap-1">
                        <i class="fa-regular fa-clock"></i> Cần xử lý
                    </div>
                </div>

                <div class="bg-[#111625] p-5 rounded-xl border border-slate-800/90 shadow-sm">
                    <div class="text-xs font-medium text-slate-400 mb-1">Tổng người dùng</div>
                    <div class="text-2xl font-bold text-white tracking-tight">1,024</div>
                    <div class="mt-2 text-xs text-emerald-400 flex items-center gap-1">
                        <i class="fa-solid fa-arrow-trend-up"></i> +15 user mới
                    </div>
                </div>
            </div>

            <!-- Bảng bài viết gần đây -->
            <div class="bg-[#111625] rounded-xl border border-slate-800/90 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Bài viết gần đây</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Danh sách các bài viết mới cập nhật trong hệ thống</p>
                    </div>
                    <button class="px-3.5 py-1.5 bg-sky-600 hover:bg-sky-500 text-white text-xs font-medium rounded-lg flex items-center gap-2 transition">
                        <i class="fa-solid fa-plus text-[10px]"></i> Tạo bài viết
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="uppercase bg-slate-800/40 text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Tiêu đề</th>
                                <th class="px-4 py-3">Chuyên mục</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3">Lượt xem</th>
                                <th class="px-4 py-3">Ngày tạo</th>
                                <th class="px-4 py-3 text-right">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-normal">
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-4 py-3 text-white font-medium max-w-sm truncate">Tối ưu hoá FlashAttention-3 trên GPU Blackwell B200</td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700/60">AI & Hardware</span></td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-[11px] bg-emerald-950/80 text-emerald-400 border border-emerald-800/50">Published</span></td>
                                <td class="px-4 py-3 text-slate-300">1,420</td>
                                <td class="px-4 py-3 text-slate-400">12/03/2026</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button class="text-sky-400 hover:underline">Sửa</button>
                                    <button class="text-rose-400 hover:underline">Xoá</button>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="px-4 py-3 text-white font-medium max-w-sm truncate">Xây dựng kiến trúc MVC chuẩn trong PHP & Laravel</td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700/60">Backend</span></td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-[11px] bg-amber-950/80 text-amber-400 border border-amber-800/50">Draft</span></td>
                                <td class="px-4 py-3 text-slate-300">0</td>
                                <td class="px-4 py-3 text-slate-400">10/03/2026</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button class="text-sky-400 hover:underline">Sửa</button>
                                    <button class="text-rose-400 hover:underline">Xoá</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

</body>
</html>