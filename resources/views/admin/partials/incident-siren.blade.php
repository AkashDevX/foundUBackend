@php
    $incidentAlerts = \App\Support\IncidentReportAlerts::forHeader();
    $incidentNewCount = (int) ($incidentAlerts['new_count'] ?? 0);
    $incidentBadge = $incidentNewCount > 9 ? '9+' : (string) $incidentNewCount;
@endphp

<div
    class="relative"
    data-incident-menu
    data-latest-id="{{ (int) ($incidentAlerts['latest_id'] ?? 0) }}"
    data-new-count="{{ $incidentNewCount }}"
    data-alerts-url="{{ route('admin.incidents.alerts') }}"
>
    <button
        type="button"
        class="relative inline-flex size-10 items-center justify-center rounded-full border border-red-200 bg-red-50 text-red-600 shadow-sm transition hover:bg-red-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-red-200 {{ $incidentNewCount > 0 ? 'ring-2 ring-red-400 animate-pulse' : '' }}"
        data-incident-menu-toggle
        aria-expanded="false"
        aria-haspopup="menu"
        aria-label="{{ $incidentNewCount > 0 ? $incidentNewCount.' new incident '.($incidentNewCount === 1 ? 'report' : 'reports') : 'Incident reports' }}"
        title="Incident reports"
    >
        <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M6 6.9 3.87 4.78 5.28 3.37 7.4 5.5 6 6.9M13 1v3h-2V1h2m7.13 3.78L18 6.9l-1.4-1.4 2.12-2.13 1.41 1.41M4.5 10.5v2h-3v-2h3m18 0v2h-3v-2h3M6 20h12a2 2 0 0 1 2 2H4a2 2 0 0 1 2-2m6-14a6 6 0 0 1 6 6c0 2.22-1.21 4.16-3 5.2V19H9v-1.8c-1.79-1.04-3-2.98-3-5.2a6 6 0 0 1 6-6Z" />
        </svg>
        @if ($incidentNewCount > 0)
            <span data-incident-badge class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-5 text-white ring-2 ring-white">
                {{ $incidentBadge }}
            </span>
        @endif
    </button>

    <div
        class="absolute right-0 z-50 mt-3 hidden w-[22rem] max-w-[calc(100vw-2rem)] origin-top-right overflow-hidden rounded-2xl border border-red-100 bg-white shadow-2xl shadow-red-900/10 ring-1 ring-black/[0.03]"
        data-incident-menu-panel
        role="menu"
    >
        <div class="flex items-center justify-between gap-3 border-b border-red-100 bg-red-50 px-4 py-3">
            <div>
                <p class="text-sm font-bold text-red-700">Incident reports</p>
                <p class="text-xs text-red-600/80" data-incident-summary>
                    @if ($incidentNewCount > 0)
                        {{ $incidentNewCount }} new {{ $incidentNewCount === 1 ? 'report needs' : 'reports need' }} review
                    @elseif ($incidentAlerts['ready'] ?? false)
                        No new reports
                    @else
                        Not available yet
                    @endif
                </p>
            </div>
            <a href="{{ $incidentAlerts['index_url'] }}" class="text-xs font-semibold text-red-700 underline-offset-2 hover:underline">View all</a>
        </div>

        <div data-incident-list>
            @if (! ($incidentAlerts['ready'] ?? false))
                <p class="px-4 py-5 text-sm text-brand-text-secondary">Incident reporting will appear here after the latest database update is applied.</p>
            @elseif (($incidentAlerts['items'] ?? []) === [])
                <p class="px-4 py-5 text-sm text-brand-text-secondary">No open incidents. New reports from the mobile app show up here.</p>
            @else
                <ul class="max-h-80 overflow-y-auto py-1">
                    @foreach ($incidentAlerts['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" role="menuitem" class="flex items-start gap-3 px-4 py-3 transition hover:bg-red-50/70 {{ $item['status'] === 'new' ? 'bg-red-50/40' : '' }}">
                                <span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full {{ $item['status'] === 'new' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700' }}">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3.2 2.8 19.2h18.4L12 3.2zm0 5.2a.9.9 0 0 1 .9.9v4.2a.9.9 0 1 1-1.8 0V9.3a.9.9 0 0 1 .9-.9zm0 9.1a1.05 1.05 0 1 1 0-2.1 1.05 1.05 0 0 1 0 2.1z"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="truncate text-sm font-semibold text-brand-text">{{ $item['title'] }}</span>
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $item['status'] === 'new' ? 'bg-red-600 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $item['status_label'] }}</span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-brand-text-secondary">{{ $item['subtitle'] }}</span>
                                    @if (($item['detail'] ?? '') !== '')
                                        <span class="mt-1 block text-xs leading-snug text-red-900">{{ $item['detail'] }}</span>
                                    @endif
                                    <span class="mt-0.5 block text-[11px] text-brand-text-secondary">{{ $item['when'] }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

<div id="incident-live-alert" class="pointer-events-none fixed right-4 top-20 z-[70] hidden w-[22rem] max-w-[calc(100vw-2rem)]"></div>

<script>
    (function () {
        var menu = document.querySelector('[data-incident-menu]');
        var toast = document.getElementById('incident-live-alert');
        if (!menu || !toast) return;

        var alertsUrl = menu.getAttribute('data-alerts-url');
        var seenId = Number(menu.getAttribute('data-latest-id') || 0);
        var seenCount = Number(menu.getAttribute('data-new-count') || 0);
        var pendingRaw = sessionStorage.getItem('cruLynkIncidentAlert');
        if (pendingRaw) {
            sessionStorage.removeItem('cruLynkIncidentAlert');
            try { showToast(JSON.parse(pendingRaw)); } catch (e) {}
        }

        function showToast(item) {
            if (!item || !item.url) return;
            toast.replaceChildren();
            var card = document.createElement('a');
            card.href = item.url;
            card.className = 'pointer-events-auto block rounded-2xl border border-red-200 bg-white p-4 shadow-2xl shadow-red-900/20 ring-1 ring-red-100';
            var kicker = document.createElement('p');
            kicker.className = 'text-[11px] font-bold uppercase tracking-wide text-red-600';
            kicker.textContent = 'New incident alert';
            var title = document.createElement('p');
            title.className = 'mt-1 text-sm font-bold text-brand-text';
            title.textContent = item.title || 'Incident report';
            var who = document.createElement('p');
            who.className = 'mt-0.5 text-xs text-brand-text-secondary';
            who.textContent = item.subtitle || '';
            card.append(kicker, title, who);
            if (item.detail) {
                var detail = document.createElement('p');
                detail.className = 'mt-2 text-sm leading-snug text-red-950';
                detail.textContent = item.detail;
                card.append(detail);
            }
            toast.append(card);
            toast.classList.remove('hidden');
            window.setTimeout(function () { toast.classList.add('hidden'); }, 12000);
        }

        function renderList(data) {
            var list = menu.querySelector('[data-incident-list]');
            if (!list) return;
            list.replaceChildren();
            var items = Array.isArray(data.items) ? data.items : [];
            if (!data.ready) {
                var waiting = document.createElement('p');
                waiting.className = 'px-4 py-5 text-sm text-brand-text-secondary';
                waiting.textContent = 'Incident reporting will appear here after the latest database update is applied.';
                list.append(waiting);
                return;
            }
            if (items.length === 0) {
                var empty = document.createElement('p');
                empty.className = 'px-4 py-5 text-sm text-brand-text-secondary';
                empty.textContent = 'No open incidents. New reports from the mobile app show up here.';
                list.append(empty);
                return;
            }
            var ul = document.createElement('ul');
            ul.className = 'max-h-80 overflow-y-auto py-1';
            items.forEach(function (item) {
                var li = document.createElement('li');
                var link = document.createElement('a');
                link.href = item.url || '#';
                link.setAttribute('role', 'menuitem');
                link.className = 'flex items-start gap-3 px-4 py-3 transition hover:bg-red-50/70 bg-red-50/40';
                var body = document.createElement('span');
                body.className = 'min-w-0 flex-1';
                var row = document.createElement('span');
                row.className = 'flex items-center justify-between gap-2';
                var title = document.createElement('span');
                title.className = 'truncate text-sm font-semibold text-brand-text';
                title.textContent = item.title || 'Incident report';
                var badge = document.createElement('span');
                badge.className = 'shrink-0 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white';
                badge.textContent = item.status_label || 'New';
                row.append(title, badge);
                var who = document.createElement('span');
                who.className = 'mt-0.5 block truncate text-xs text-brand-text-secondary';
                who.textContent = item.subtitle || '';
                body.append(row, who);
                if (item.detail) {
                    var detail = document.createElement('span');
                    detail.className = 'mt-1 block text-xs leading-snug text-red-900';
                    detail.textContent = item.detail;
                    body.append(detail);
                }
                var when = document.createElement('span');
                when.className = 'mt-0.5 block text-[11px] text-brand-text-secondary';
                when.textContent = item.when || '';
                body.append(when);
                link.append(body);
                li.append(link);
                ul.append(li);
            });
            list.append(ul);
        }

        function applyAlerts(data) {
            var count = Number(data.new_count || 0);
            var button = menu.querySelector('[data-incident-menu-toggle]');
            var badge = menu.querySelector('[data-incident-badge]');
            var summary = menu.querySelector('[data-incident-summary]');
            if (button) {
                button.classList.toggle('ring-2', count > 0);
                button.classList.toggle('ring-red-400', count > 0);
                button.classList.toggle('animate-pulse', count > 0);
                button.setAttribute('aria-label', count > 0 ? count + ' new incident ' + (count === 1 ? 'report' : 'reports') : 'Incident reports');
            }
            if (count > 0) {
                if (!badge && button) {
                    badge = document.createElement('span');
                    badge.setAttribute('data-incident-badge', '');
                    badge.className = 'absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-5 text-white ring-2 ring-white';
                    button.append(badge);
                }
                if (badge) badge.textContent = count > 9 ? '9+' : String(count);
            } else if (badge) {
                badge.remove();
            }
            if (summary) {
                summary.textContent = count > 0
                    ? count + ' new ' + (count === 1 ? 'report needs' : 'reports need') + ' review'
                    : (data.ready ? 'No new reports' : 'Not available yet');
            }
        }

        function newest(data) {
            var items = Array.isArray(data.items) ? data.items : [];
            var latest = null;
            items.forEach(function (item) {
                if (item.status === 'new' && (!latest || Number(item.id) > Number(latest.id))) latest = item;
            });
            return latest;
        }

        window.setInterval(function () {
            fetch(alertsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    var latestId = Number(data.latest_id || 0);
                    var count = Number(data.new_count || 0);
                    applyAlerts(data);
                    renderList(data);
                    var onDashboard = window.location.pathname === '/admin' || window.location.pathname === '/admin/';
                    if (onDashboard && count !== seenCount) {
                        var item = newest(data);
                        if (item && latestId > seenId) sessionStorage.setItem('cruLynkIncidentAlert', JSON.stringify(item));
                        window.location.reload();
                        return;
                    }
                    if (latestId > seenId) {
                        var arrived = newest(data);
                        if (arrived) showToast(arrived);
                    }
                    seenId = latestId;
                    seenCount = count;
                    menu.setAttribute('data-latest-id', String(latestId));
                    menu.setAttribute('data-new-count', String(count));
                })
                .catch(function () {});
        }, 8000);
    })();
</script>
