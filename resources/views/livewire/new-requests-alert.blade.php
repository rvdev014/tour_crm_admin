{{--
    Topbar pill for new website requests (see App\Livewire\NewRequestsAlert).
    Inline CSS/JS on purpose: public/build is committed and the prod image doesn't run `npm run build`.
--}}
<div
    wire:poll.10s="check"
    x-data="{
        open: false,
        muted: false,
        baseTitle: document.title,
        init() {
            try { this.muted = localStorage.getItem('newRequestsMuted') === '1'; } catch (e) {}
        },
        toggleMute() {
            this.muted = ! this.muted;
            try { localStorage.setItem('newRequestsMuted', this.muted ? '1' : '0'); } catch (e) {}
        },
        // Tab title gets a (N) prefix so a request is noticeable from a background tab.
        syncTitle(total) {
            document.title = (total > 0 ? '(' + total + ') ' : '') + this.baseTitle;
        },
        // Two short tones, no audio file. Browsers only allow sound after the first click on the page;
        // before that this is silently skipped and the toast still shows.
        beep() {
            if (this.muted) return;
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                const ctx = new Ctx();
                [[880, 0], [660, 0.2]].forEach(([freq, at]) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.frequency.value = freq;
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    const t = ctx.currentTime + at;
                    gain.gain.setValueAtTime(0.0001, t);
                    gain.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.18);
                    osc.start(t);
                    osc.stop(t + 0.2);
                });
            } catch (e) {}
        },
    }"
    x-effect="syncTitle($wire.total)"
    x-on:new-requests-arrived.window="beep()"
    x-on:click.outside="open = false"
    class="nr"
>
    @if (count($summary))
        <button
            type="button"
            class="nr-pill {{ $total > 0 ? 'nr-hot' : '' }}"
            x-on:click="open = ! open"
            x-bind:aria-expanded="open"
            aria-haspopup="true"
        >
            <x-filament::icon icon="heroicon-o-bell-alert" class="nr-ico" />
            <span>
                @if ($total > 0)
                    {{ $total }} {{ __('new requests') }}
                @else
                    {{ __('No new requests') }}
                @endif
            </span>
        </button>

        <div class="nr-menu" x-show="open" x-cloak x-transition.opacity>
            @foreach ($summary as $row)
                <a href="{{ $row['url'] }}" class="nr-row">
                    <span>{{ $row['label'] }}</span>
                    <b class="{{ $row['count'] > 0 ? 'nr-count-hot' : '' }}">{{ $row['count'] }}</b>
                </a>
            @endforeach

            <button type="button" class="nr-mute" x-on:click="toggleMute()">
                <span x-text="muted ? '{{ __('Sound off') }}' : '{{ __('Sound on') }}'"></span>
            </button>
        </div>
    @endif

    <style>
        .nr { position: relative; margin-inline-end: .75rem; }
        .nr-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .8rem; border-radius: 9999px; border: 1px solid rgb(var(--gray-300)); background: transparent; color: rgb(var(--gray-600)); font-size: .8125rem; font-weight: 600; line-height: 1.25rem; cursor: pointer; white-space: nowrap; }
        .dark .nr-pill { border-color: rgb(var(--gray-600)); color: rgb(var(--gray-300)); }
        .nr-ico { width: 1.1rem; height: 1.1rem; }
        .nr-hot { background: rgb(var(--danger-600)); border-color: rgb(var(--danger-600)); color: #fff; animation: nr-pulse 1.6s ease-out infinite; }
        .dark .nr-hot { color: #fff; border-color: rgb(var(--danger-500)); }
        @keyframes nr-pulse {
            0% { box-shadow: 0 0 0 0 rgba(var(--danger-500), .55); }
            70% { box-shadow: 0 0 0 10px rgba(var(--danger-500), 0); }
            100% { box-shadow: 0 0 0 0 rgba(var(--danger-500), 0); }
        }
        @media (prefers-reduced-motion: reduce) { .nr-hot { animation: none; } }
        .nr-menu { position: absolute; inset-inline-end: 0; top: calc(100% + .5rem); z-index: 40; min-width: 16rem; padding: .35rem; border-radius: .75rem; background: #fff; border: 1px solid rgb(var(--gray-200)); box-shadow: 0 10px 30px rgba(0, 0, 0, .12); }
        .dark .nr-menu { background: rgb(var(--gray-900)); border-color: rgb(var(--gray-700)); }
        .nr-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .5rem .75rem; border-radius: .5rem; font-size: .875rem; color: rgb(var(--gray-700)); }
        .dark .nr-row { color: rgb(var(--gray-200)); }
        .nr-row:hover { background: rgb(var(--gray-100)); }
        .dark .nr-row:hover { background: rgb(var(--gray-800)); }
        .nr-count-hot { color: rgb(var(--danger-600)); }
        .dark .nr-count-hot { color: rgb(var(--danger-400)); }
        .nr-mute { display: block; width: 100%; margin-top: .25rem; padding: .5rem .75rem; border-top: 1px solid rgb(var(--gray-200)); background: transparent; text-align: start; font-size: .75rem; color: rgb(var(--gray-500)); cursor: pointer; }
        .dark .nr-mute { border-color: rgb(var(--gray-700)); }
    </style>
</div>
