<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0D9488">
    <title>{{ $title ?? __('driver.app_name') }} · East Asia Point</title>
    <style>
        /*
         * Self-contained on purpose (same approach as errors/layout.blade.php): no Vite/Tailwind build,
         * so nothing to rebuild or commit into public/build, and one fewer request on a driver's mobile
         * connection. Colors follow the CRM: teal primary, slate surfaces. Dark mode follows the OS.
         */
        :root {
            color-scheme: light dark;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --line: #E2E8F0;
            --text: #0F172A;
            --muted: #64748B;
            --primary: #0D9488;
            --primary-press: #0F766E;
            --on-primary: #FFFFFF;
            --danger: #BE123C;
            --danger-bg: #FFE4E6;
            --ok: #15803D;
            --ok-bg: #DCFCE7;
            --chip: #F1F5F9;
            --radius: 12px;

            --b-gray: #475569;     --b-gray-bg: #F1F5F9;
            --b-info: #1D4ED8;     --b-info-bg: #DBEAFE;
            --b-warning: #B45309;  --b-warning-bg: #FEF3C7;
            --b-primary: #0F766E;  --b-primary-bg: #CCFBF1;
            --b-success: #15803D;  --b-success-bg: #DCFCE7;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #020617;
                --card: #0F172A;
                --line: #1E293B;
                --text: #F8FAFC;
                --muted: #94A3B8;
                --primary: #2DD4BF;
                --primary-press: #5EEAD4;
                --on-primary: #042F2E;
                --danger: #FDA4AF;
                --danger-bg: #4C0519;
                --ok: #86EFAC;
                --ok-bg: #052E16;
                --chip: #1E293B;

                --b-gray: #CBD5E1;     --b-gray-bg: #1E293B;
                --b-info: #93C5FD;     --b-info-bg: #172554;
                --b-warning: #FCD34D;  --b-warning-bg: #451A03;
                --b-primary: #5EEAD4;  --b-primary-bg: #042F2E;
                --b-success: #86EFAC;  --b-success-bg: #052E16;
            }
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 16px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding-bottom: env(safe-area-inset-bottom);
        }
        a { color: inherit; }
        button { font: inherit; }
        :focus-visible { outline: 3px solid var(--primary); outline-offset: 2px; }

        /* ── header ─────────────────────────────────────────────── */
        .top {
            position: sticky; top: 0; z-index: 10;
            background: var(--primary); color: var(--on-primary);
            padding: calc(10px + env(safe-area-inset-top)) 16px 10px;
            display: flex; align-items: center; gap: 12px; min-height: 56px;
        }
        .top__title { flex: 1; min-width: 0; margin: 0; font-size: 18px; font-weight: 700;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .top__back { display: inline-grid; place-items: center; width: 44px; height: 44px; margin-left: -10px;
            color: inherit; text-decoration: none; }
        .lang { display: flex; gap: 2px; }
        .lang a { min-width: 40px; height: 40px; display: inline-grid; place-items: center; border-radius: 8px;
            font-size: 13px; font-weight: 700; text-decoration: none; opacity: .75; }
        .lang a[aria-current="true"] { opacity: 1; background: rgba(255,255,255,.22); }
        .icon-btn { width: 44px; height: 44px; display: inline-grid; place-items: center; border: 0; border-radius: 10px;
            background: transparent; color: inherit; cursor: pointer; }

        main { max-width: 640px; margin: 0 auto; padding-bottom: 32px; }

        /* ── dispatcher tabs ────────────────────────────────────── */
        .tabs { display: flex; background: var(--card); border-bottom: 1px solid var(--line); }
        .tabs a { flex: 1; min-height: 46px; display: grid; place-items: center; padding: 0 8px; font-weight: 600;
            text-decoration: none; color: var(--muted); border-bottom: 3px solid transparent; }
        .tabs a[aria-current="page"] { color: var(--primary); border-bottom-color: var(--primary); }

        /* ── date strip ─────────────────────────────────────────── */
        .strip { display: flex; gap: 6px; overflow-x: auto; padding: 12px 16px;
            scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none;
            background: var(--card); border-bottom: 1px solid var(--line); }
        .strip::-webkit-scrollbar { display: none; }
        .day { flex: 0 0 auto; min-width: 54px; padding: 8px 6px; border-radius: 10px; text-align: center;
            text-decoration: none; scroll-snap-align: center; background: var(--chip); position: relative; }
        .day__wd { display: block; font-size: 12px; text-transform: uppercase; color: var(--muted); }
        .day__num { display: block; font-size: 20px; font-weight: 700; line-height: 1.2; }
        .day__dot { display: block; height: 6px; margin-top: 4px; }
        /* display:block, not inline-block: an inline box sits on the 22px line box and hangs out of the chip. */
        .day__dot::after { content: ""; display: block; width: 6px; height: 6px; margin: 0 auto; border-radius: 50%; background: var(--primary); }
        .day--empty .day__dot::after { visibility: hidden; }
        .day--active { background: var(--primary); color: var(--on-primary); }
        .day--active .day__wd { color: inherit; opacity: .85; }
        .day--active .day__dot::after { background: var(--on-primary); }
        .day--today:not(.day--active) { box-shadow: inset 0 0 0 2px var(--primary); }

        /* ── list ───────────────────────────────────────────────── */
        .list { list-style: none; margin: 0; padding: 12px 16px; display: grid; gap: 10px; }
        .card { display: flex; align-items: stretch; background: var(--card); border: 1px solid var(--line);
            border-radius: var(--radius); overflow: hidden; }
        .card__main { flex: 1; min-width: 0; display: flex; gap: 14px; padding: 14px; text-decoration: none; }
        .card__time { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; min-width: 3.4ch; }
        .card__body { min-width: 0; flex: 1; }
        .card__title { display: block; font-weight: 600; overflow-wrap: anywhere; }
        .card__sub { display: block; color: var(--muted); font-size: 14px; overflow-wrap: anywhere; margin-top: 2px; }
        .jump { display: block; text-align: center; padding: 12px; color: var(--primary); font-weight: 600; text-decoration: none; }
        .card__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; font-size: 13px; color: var(--muted); }
        .card__map { flex: 0 0 50px; display: grid; place-items: center; border-left: 1px solid var(--line);
            color: var(--primary); text-decoration: none; }
        .card__who { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 14px; font-weight: 600; color: var(--primary); }
        .card__who svg { flex: 0 0 auto; }
        .empty { text-align: center; color: var(--muted); padding: 48px 16px; }

        /* ── badge ──────────────────────────────────────────────── */
        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 999px;
            font-size: 13px; font-weight: 600; white-space: nowrap; }
        .badge--gray { color: var(--b-gray); background: var(--b-gray-bg); }
        .badge--info { color: var(--b-info); background: var(--b-info-bg); }
        .badge--warning { color: var(--b-warning); background: var(--b-warning-bg); }
        .badge--primary { color: var(--b-primary); background: var(--b-primary-bg); }
        .badge--success { color: var(--b-success); background: var(--b-success-bg); }
        .badge--danger { color: var(--danger); background: var(--danger-bg); }

        /* ── detail ─────────────────────────────────────────────── */
        .panel { margin: 12px 16px; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); }
        .hero { padding: 16px; display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 14px; }
        .hero__time { font-size: 32px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .hero__date { color: var(--muted); }
        .hero .badge { margin-left: auto; }
        .rows { margin: 0; padding: 0; }
        /* Map button sits UNDER the address, not beside it: a long airport address squeezed into a narrow column wraps to 5 lines. */
        .row { padding: 12px 16px; border-top: 1px solid var(--line); display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; }
        .row__text { flex: 1 1 100%; min-width: 0; }
        .row dt { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); }
        .row dd { margin: 2px 0 0; overflow-wrap: anywhere; }
        .row__map { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 12px;
            border-radius: 10px; border: 1px solid var(--line); color: var(--primary); font-weight: 600;
            font-size: 14px; text-decoration: none; }

        /* ── pickup sign + comment, client contact ──────────────── */
        .callout { margin: 0 16px 4px; padding: 14px 16px; border-radius: 10px; background: var(--b-warning-bg); color: var(--b-warning); }
        .callout__label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; opacity: .85; }
        .callout__label + .callout__sign, .callout__label + .callout__text { margin-top: 2px; }
        .callout__sign { font-size: 26px; font-weight: 800; line-height: 1.2; overflow-wrap: anywhere; margin-bottom: 10px; }
        .callout__text { font-size: 16px; font-weight: 600; white-space: pre-line; overflow-wrap: anywhere; }
        .client { padding: 12px 16px 14px; }
        .client .callout__label { color: var(--muted); }
        .contact { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .contact__btn { flex: 1 1 96px; min-height: 64px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            padding: 8px 6px; border-radius: 12px; color: #fff; font-size: 13px; font-weight: 700; text-decoration: none; text-align: center; }
        .contact__btn--call { background: var(--primary); color: var(--on-primary); }
        .contact__btn--whatsapp { background: #25D366; color: #06331a; }
        .contact__btn--telegram { background: #229ED9; color: #fff; }
        /* On the list card: the sign to look for, and a two-line preview of the operator's note. */
        .card__sign { display: block; margin-top: 6px; font-size: 14px; font-weight: 700; overflow-wrap: anywhere; }
        .card__k { font-weight: 600; color: var(--muted); text-transform: uppercase; font-size: 11px; letter-spacing: .04em; margin-right: 4px; }
        .card__note { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 4px;
            font-size: 14px; color: var(--muted); white-space: pre-line; overflow-wrap: anywhere; }

        /* ── expenses ───────────────────────────────────────────── */
        .panel__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 16px 10px; }
        .panel__head h2 { margin: 0; font-size: 18px; }
        .expense { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 12px 16px; border-top: 1px solid var(--line); }
        .expense__main { flex: 1 1 60%; min-width: 0; }
        .expense__actions { display: flex; gap: 8px; flex: 1 1 100%; }
        .expense__actions a { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 12px; border: 1px solid var(--line);
            border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; color: var(--primary); }
        .expense__actions .expense__delete { color: var(--danger); }
        .panel__foot { padding: 12px 16px 16px; border-top: 1px solid var(--line); }
        .btn--add { min-height: 52px; font-size: 16px; gap: 8px; }
        .amount { display: flex; align-items: center; gap: 10px; }
        .amount input { flex: 1; min-width: 0; }
        .amount__cur { font-weight: 700; color: var(--muted); }
        .field select { width: 100%; min-height: 52px; padding: 0 14px; font-size: 16px; color: var(--text);
            background: var(--card); border: 1px solid var(--line); border-radius: 10px; }
        .field input[type="file"] { padding: 12px 14px; min-height: 52px; }
        .field .hint { color: var(--muted); font-size: 13px; margin: 6px 0 0; }
        .form-panel { padding: 16px; }

        /* ── people (dispatcher: driver blocks, directory) ─────────── */
        .person__name { font-weight: 600; }
        .person__meta { color: var(--muted); font-size: 14px; margin-top: 2px; }
        .person--off { opacity: .6; }
        .row__map--solid { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }
        .setstatus label { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); margin-bottom: 6px; }
        /* 16px minimum keeps iOS Safari from zooming the page when the select is focused. */
        .setstatus select { width: 100%; min-height: 52px; padding: 0 12px; margin-bottom: 10px; font-size: 16px;
            color: var(--text); background: var(--card); border: 1px solid var(--line); border-radius: 10px; }

        /* ── action bar ─────────────────────────────────────────── */
        .action { position: sticky; bottom: 0; padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
            background: linear-gradient(to top, var(--bg) 70%, transparent); }
        /* The dispatcher's status picker is taller than a single button: a solid bar, not the fade-out one,
           or the label is drawn over the text scrolling underneath it. */
        .action--form { background: var(--bg); border-top: 1px solid var(--line); }
        .btn { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 56px; padding: 0 20px;
            border: 0; border-radius: var(--radius); background: var(--primary); color: var(--on-primary);
            font-size: 18px; font-weight: 700; cursor: pointer; text-decoration: none; }
        .btn:active { background: var(--primary-press); }
        .btn--ghost { background: transparent; color: var(--text); border: 1px solid var(--line); margin-top: 10px; }
        .done { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 56px; border-radius: var(--radius);
            background: var(--ok-bg); color: var(--ok); font-weight: 700; }

        /* ── flash + forms ──────────────────────────────────────── */
        .flash { margin: 12px 16px 0; padding: 12px 14px; border-radius: 10px; font-weight: 600; }
        .flash--ok { background: var(--ok-bg); color: var(--ok); }
        .flash--err { background: var(--danger-bg); color: var(--danger); }
        .auth { max-width: 400px; margin: 0 auto; padding: 32px 16px; }
        .auth h1 { font-size: 24px; margin: 0 0 4px; }
        .auth p { color: var(--muted); margin: 0 0 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-weight: 600; margin-bottom: 6px; }
        /* 16px minimum: anything smaller makes iOS Safari zoom the page on focus. */
        .field input { width: 100%; min-height: 52px; padding: 0 14px; font-size: 16px; color: var(--text);
            background: var(--card); border: 1px solid var(--line); border-radius: 10px; }
        .field .err { color: var(--danger); font-size: 14px; margin-top: 6px; }
        .auth .lang { justify-content: center; margin-top: 24px; }
        .auth .lang a { color: var(--muted); }
        .auth .lang a[aria-current="true"] { background: var(--chip); color: var(--text); }
        .confirm { padding: 24px 16px; text-align: center; }
        .confirm h1 { font-size: 22px; margin: 0 0 8px; }
        .confirm p { color: var(--muted); margin: 0 0 20px; }
    </style>
</head>
<body>
    @yield('body')
</body>
</html>
