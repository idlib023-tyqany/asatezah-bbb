<ul class="space-y-2">
    @php
        $links = [
            [
                'title' => 'Read the Documentation',
                'url' => 'https://laravel.com/docs',
                'type' => 'first', // للبداية
            ],
            [
                'title' => 'Watch Laracasts',
                'url' => 'https://laracasts.com',
                'type' => 'middle', // للمنتصف
            ],
            [
                'title' => 'Laravel News',
                'url' => 'https://laravel-news.com',
                'type' => 'last', // للنهاية
            ],
        ];
    @endphp

    @foreach ($links as $link)
        @php
            // تحديد حدود خط الاتصال الجانبي حسب موقعه
            $borderClass = match ($link['type']) {
                'first' => 'before:top-1/2 before:bottom-0',
                'last' => 'before:top-0 before:bottom-1/2',
                default => 'before:top-0 before:bottom-0',
            };
        @endphp

        <li
            class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] {{ $borderClass }} before:left-[0.4rem] before:absolute">
            <span class="relative py-1 bg-white dark:bg-[#161615]">
                <span
                    class="flex items-center justify-center w-3.5 h-3.5 rounded-full border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#f53003] dark:bg-[#F61500]"></span>
                </span>
            </span>
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                class="font-medium underline underline-offset-4 text-[#1b1b18] dark:text-[#EDEDEC] hover:text-[#f53003] dark:hover:text-[#FF4433]">
                {{ $link['title'] }}
            </a>
        </li>
    @endforeach
</ul>
</div>

<div
    class="bg-[#1b1b18] text-white flex-1 p-6 lg:p-20 rounded-t-lg lg:rounded-t-none lg:rounded-r-lg flex flex-col justify-between">
    <div>
        <h2 class="mb-1 font-medium">Laravel Community</h2>
        <p class="text-[#706f6c]">Connect with developers around the world using Laravel.</p>
    </div>
</div>
</main>
</div>
</body>

</html>
