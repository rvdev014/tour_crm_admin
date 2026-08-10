<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Error' }} · East Asia Point</title>
    <style>
        /*
         * This page renders standalone (no Filament layout, no built app.css —
         * it has to survive a 500 even if the asset pipeline is the thing
         * that's broken), so it can't reference the design tokens in
         * resources/css/filament/admin/theme.css directly. The values below
         * are kept in sync with that file by hand: teal primary (--primary-*),
         * green secondary (--success-*), slate surfaces (--surface-*), same
         * radius/shadow scale. Light is the default surface; dark follows the
         * OS preference via prefers-color-scheme, since this page has no
         * access to the panel's own theme toggle state.
         */
        :root {
            --ep-page: #F8FAFC;
            --ep-card: rgba(255, 255, 255, 0.9);
            --ep-card-border: #E2E8F0;
            --ep-text: #0F172A;
            --ep-text-muted: #64748B;
            --ep-code: #0D9488;
            --ep-footer: #94A3B8;
            --ep-error-id-bg: rgba(15, 23, 42, 0.04);
            --ep-error-id-border: #E2E8F0;
            --ep-btn-secondary-bg: rgba(15, 23, 42, 0.04);
            --ep-btn-secondary-border: #E2E8F0;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ep-page: #020617;
                --ep-card: rgba(15, 23, 42, 0.82);
                --ep-card-border: rgba(255, 255, 255, 0.08);
                --ep-text: #F8FAFC;
                --ep-text-muted: #94A3B8;
                --ep-code: #2DD4BF;
                --ep-footer: rgba(148, 163, 184, 0.55);
                --ep-error-id-bg: rgba(255, 255, 255, 0.05);
                --ep-error-id-border: rgba(255, 255, 255, 0.08);
                --ep-btn-secondary-bg: rgba(255, 255, 255, 0.04);
                --ep-btn-secondary-border: rgba(255, 255, 255, 0.08);
            }
        }

        @keyframes blob-drift-1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%       { transform: translate(40px, -30px) scale(1.06); }
            66%       { transform: translate(-20px, 20px) scale(0.96); }
        }
        @keyframes blob-drift-2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%       { transform: translate(-30px, 40px) scale(0.94); }
            66%       { transform: translate(25px, -20px) scale(1.04); }
        }
        @keyframes card-enter {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: var(--ep-page);
            color: var(--ep-text);
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        body::before {
            content: '';
            position: fixed;
            width: 720px;
            height: 720px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(13, 148, 136, 0.12) 0%, transparent 68%);
            top: -180px;
            left: -180px;
            animation: blob-drift-1 12s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            width: 560px;
            height: 560px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(22, 163, 74, 0.08) 0%, transparent 68%);
            bottom: -120px;
            right: -120px;
            animation: blob-drift-2 15s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        .dot-grid {
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: 36px 36px;
            pointer-events: none;
            z-index: 0;
        }

        .card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 460px;
            background: var(--ep-card);
            backdrop-filter: blur(28px) saturate(1.4);
            -webkit-backdrop-filter: blur(28px) saturate(1.4);
            border: 1px solid var(--ep-card-border);
            border-radius: 16px;
            box-shadow:
                0 0 0 1px rgba(13, 148, 136, 0.1),
                0 20px 60px rgba(15, 23, 42, 0.16),
                inset 0 1px 0 rgba(255, 255, 255, 0.06);
            padding: 44px 40px 36px;
            text-align: center;
            animation: card-enter 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .code {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--ep-code);
            margin: 0 0 14px;
        }

        h1 {
            font-size: 1.375rem;
            font-weight: 600;
            color: var(--ep-text);
            margin: 0 0 12px;
            line-height: 1.35;
        }

        p.message {
            font-size: 0.9375rem;
            color: var(--ep-text-muted);
            line-height: 1.6;
            margin: 0 0 28px;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: background .15s ease, box-shadow .15s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0D9488, #0F766E);
            color: #fff;
            box-shadow: 0 4px 20px rgba(13, 148, 136, 0.35), 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 28px rgba(13, 148, 136, 0.5), 0 2px 6px rgba(0, 0, 0, 0.25);
        }

        .btn-secondary {
            background: var(--ep-btn-secondary-bg);
            color: var(--ep-text-muted);
            border: 1px solid var(--ep-btn-secondary-border);
        }

        .btn-secondary:hover {
            background: rgba(13, 148, 136, 0.08);
        }

        .error-id {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--ep-card-border);
        }

        .error-id-label {
            font-size: 0.6875rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ep-footer);
            margin: 0 0 6px;
        }

        .error-id-value {
            display: inline-block;
            font-family: "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.8125rem;
            color: var(--ep-text-muted);
            background: var(--ep-error-id-bg);
            border: 1px solid var(--ep-error-id-border);
            border-radius: 6px;
            padding: 5px 10px;
            user-select: all;
        }

        .footer {
            position: relative;
            z-index: 1;
            text-align: center;
            margin-top: 22px;
            color: var(--ep-footer);
            font-size: 0.6875rem;
            letter-spacing: 0.05em;
        }

        .wrap { position: relative; z-index: 1; }
    </style>
</head>
<body>
    <div class="dot-grid"></div>
    <div class="wrap">
        <div class="card">
            <p class="code">{{ $code ?? '' }}</p>
            <h1>{{ $title ?? 'Something went wrong' }}</h1>
            <p class="message">{{ $message ?? '' }}</p>
            <div class="actions">
                <a href="{{ url('/admin') }}" class="btn btn-primary">Back to dashboard</a>
                @isset($secondaryAction)
                    {{ $secondaryAction }}
                @endisset
            </div>
            @isset($errorId)
                <div class="error-id">
                    <p class="error-id-label">Error ID — quote this to support</p>
                    <span class="error-id-value">{{ $errorId }}</span>
                </div>
            @endisset
        </div>
        <div class="footer">© {{ date('Y') }} East Asia Point</div>
    </div>
</body>
</html>
