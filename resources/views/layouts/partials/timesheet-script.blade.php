{{-- Shared live-timer + clock in/out logic for every timesheet widget (sidebar + top bar).
     Guarded so it initialises once and drives all .ts-worked-* instances on the page. --}}
<script>
(function () {
    if (window.__tsTimesheet) return;
    window.__tsTimesheet = true;

    var worked = {{ (int) ($tsWorked ?? 0) }};
    var goal = {{ (int) ($tsGoalSeconds ?? 28800) }};
    var running = {{ ($tsClockedIn ?? false) ? 'true' : 'false' }};

    function fmt(s) { return Math.floor(s/3600) + 'h ' + Math.floor((s%3600)/60) + 'm'; }
    function paint() {
        var p = Math.min(100, Math.max(0, Math.round(worked / goal * 100)));
        document.querySelectorAll('.ts-worked-timer').forEach(function (e) { e.textContent = fmt(worked); });
        document.querySelectorAll('.ts-worked-bar').forEach(function (e) { e.style.width = p + '%'; });
        document.querySelectorAll('.ts-worked-pct').forEach(function (e) { e.textContent = p; });
    }
    if (running) { setInterval(function () { worked++; paint(); }, 1000); }

    // Clock in/out from any widget; best-effort geolocation, server enforces geofencing
    // and blocks biometric-only users with a message.
    window.tsAttendance = window.tsAttendance || function (action) {
        var run = function (lat, lng) {
            var body = { _token: '{{ csrf_token() }}' };
            if (lat != null && lng != null) { body.lat = lat; body.lng = lng; }
            fetch('/attendance/' + action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body)
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (res.d && res.d.success) { window.location.reload(); }
                else { alert((res.d && res.d.message) || 'Could not update attendance.'); }
            })
            .catch(function () { alert('A network error occurred.'); });
        };
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (p) { run(p.coords.latitude, p.coords.longitude); },
                function () { run(null, null); },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        } else { run(null, null); }
    };
})();
</script>
