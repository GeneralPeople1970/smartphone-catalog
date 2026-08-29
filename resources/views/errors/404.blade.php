{{--
    Public 404 page. Deliberately self-contained: no bundled assets, no Alpine,
    no shared layout. An error page has to render even when the build manifest is
    missing or the request died before the app was fully booted, so the only
    external asset it depends on is the logo — and that degrades to alt text.

    public/404.html is the static twin of this page, for web servers configured
    with `error_page 404 /404.html;` (requests that never reach PHP).
    ErrorPageTest keeps the two in sync.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>页面未找到 - {{ config('app.name', '智能手机参数站') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">
        <style>
            :root {
                color-scheme: light dark;
                --page-bg: #f8fafc;
                --panel-bg: #ffffff;
                --border: #e2e8f0;
                --text: #0f172a;
                --muted: #64748b;
                --accent: #1d4ed8;
                --accent-text: #ffffff;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --page-bg: #0f172a;
                    --panel-bg: #1e293b;
                    --border: #334155;
                    --text: #e2e8f0;
                    --muted: #94a3b8;
                    --accent: #3b82f6;
                    --accent-text: #0f172a;
                }
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                display: flex;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
                background-color: var(--page-bg);
                color: var(--text);
                font-family: system-ui, -apple-system, 'Segoe UI', 'Microsoft YaHei', sans-serif;
                line-height: 1.6;
            }

            .error-panel {
                width: 100%;
                max-width: 32rem;
                border: 1px solid var(--border);
                border-radius: 0.75rem;
                background-color: var(--panel-bg);
                padding: 2.5rem 2rem;
                text-align: center;
                box-shadow: 0 10px 30px rgb(15 23 42 / 8%);
            }

            .error-logo {
                width: 64px;
                height: 64px;
                object-fit: contain;
            }

            .error-code {
                margin: 0.75rem 0 0;
                font-size: clamp(3.5rem, 12vw, 5.5rem);
                font-weight: 800;
                line-height: 1;
                letter-spacing: 0.02em;
                color: var(--accent);
            }

            .error-title {
                margin: 0.5rem 0 0;
                font-size: 1.35rem;
                font-weight: 700;
            }

            .error-text {
                margin: 0.75rem 0 0;
                color: var(--muted);
            }

            .error-actions {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.75rem;
                margin-top: 1.75rem;
            }

            .error-button {
                display: inline-flex;
                min-height: 2.5rem;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--accent);
                border-radius: 0.375rem;
                padding: 0 1.25rem;
                background-color: var(--accent);
                color: var(--accent-text);
                font-size: 0.95rem;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
            }

            .error-button-secondary {
                border-color: var(--border);
                background-color: transparent;
                color: var(--text);
            }

            .error-button:hover,
            .error-button:focus-visible {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <main class="error-panel">
            <img src="{{ asset('assets/logo.png') }}" alt="{{ config('app.name', '智能手机参数站') }}" class="error-logo" width="64" height="64">
            <p class="error-code">404</p>
            <h1 class="error-title">页面未找到</h1>
            <p class="error-text">该地址不存在，或页面已被移动。可以回到首页重新查找机型。</p>

            <div class="error-actions">
                <a class="error-button" href="{{ url('/') }}">返回首页</a>
                <a class="error-button error-button-secondary" href="{{ url('/category') }}">浏览品牌</a>
            </div>
        </main>
    </body>
</html>
