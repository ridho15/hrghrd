using System.Diagnostics;
using System.Threading;

namespace HrgAgent;

/// <summary>A snapshot of one reporting interval. Counts only, never key contents.</summary>
public sealed record IntervalData(
    string ClientId,
    DateTime StartedAtUtc,
    DateTime EndedAtUtc,
    int ActiveSeconds,
    int IdleSeconds,
    long Keystrokes,
    long MouseMoves,
    string ForegroundApp);

/// <summary>
/// Counts input events with low-level hooks on a dedicated thread (so a busy UI
/// thread can never stall system input or get the hooks silently removed), and
/// measures active / idle time from the real clock once per second.
///
/// Nothing is counted while the employee has paused tracking, while the
/// session is locked, or once they've been away longer than the away threshold.
/// All public members are thread-safe.
/// </summary>
public sealed class ActivityTracker : IDisposable
{
    private readonly long _idleThresholdMs;
    private readonly long _awayThresholdMs;
    private readonly object _gate = new();

    // Hooks (owned by the hook thread)
    private readonly NativeMethods.HookProc _kbProc;
    private readonly NativeMethods.HookProc _mouseProc;
    private IntPtr _kbHook, _mouseHook;
    private Thread? _hookThread;
    private uint _hookThreadId;
    private readonly ManualResetEventSlim _hooksReady = new(false);

    // Current interval
    private long _keystrokes, _mouseMoves;   // written by the hook thread (Interlocked)
    private double _activeSec, _idleSec;
    private DateTime _intervalStartUtc = DateTime.UtcNow;
    private readonly Dictionary<string, double> _appSeconds = new(StringComparer.OrdinalIgnoreCase);
    private readonly Stopwatch _clock = Stopwatch.StartNew();
    private long _lastTickMs;

    // Today's totals (local date) from already-flushed intervals
    private DateOnly _day = DateOnly.FromDateTime(DateTime.Now);
    private double _dayActive, _dayIdle;
    private long _dayKeys, _dayMouse;

    private volatile bool _paused;
    private volatile bool _locked;

    public bool HooksInstalled { get; private set; }
    public bool Paused { get => _paused; set => _paused = value; }
    public bool Locked { get => _locked; set => _locked = value; }
    public bool Away { get; private set; }

    public ActivityTracker(int idleThresholdSeconds, int awayThresholdSeconds)
    {
        _idleThresholdMs = idleThresholdSeconds * 1000L;
        _awayThresholdMs = Math.Max(awayThresholdSeconds, idleThresholdSeconds) * 1000L;
        _kbProc = KeyboardCallback;   // keep delegates alive for the lifetime of the hooks
        _mouseProc = MouseCallback;
    }

    public void Start()
    {
        _hookThread = new Thread(HookLoop) { IsBackground = true, Name = "HrgTracker input hooks" };
        _hookThread.Start();
        _hooksReady.Wait(TimeSpan.FromSeconds(5));
        lock (_gate) { _lastTickMs = _clock.ElapsedMilliseconds; }
    }

    private void HookLoop()
    {
        _hookThreadId = NativeMethods.GetCurrentThreadId();
        IntPtr hMod = NativeMethods.GetModuleHandle(null);
        _kbHook = NativeMethods.SetWindowsHookEx(NativeMethods.WH_KEYBOARD_LL, _kbProc, hMod, 0);
        _mouseHook = NativeMethods.SetWindowsHookEx(NativeMethods.WH_MOUSE_LL, _mouseProc, hMod, 0);
        HooksInstalled = _kbHook != IntPtr.Zero && _mouseHook != IntPtr.Zero;
        _hooksReady.Set();

        // Low-level hooks are delivered through this thread's message loop.
        while (NativeMethods.GetMessage(out var msg, IntPtr.Zero, 0, 0) > 0)
        {
            NativeMethods.TranslateMessage(ref msg);
            NativeMethods.DispatchMessage(ref msg);
        }

        if (_kbHook != IntPtr.Zero) NativeMethods.UnhookWindowsHookEx(_kbHook);
        if (_mouseHook != IntPtr.Zero) NativeMethods.UnhookWindowsHookEx(_mouseHook);
        _kbHook = _mouseHook = IntPtr.Zero;
    }

    private bool Counting => !_paused && !_locked;

    private IntPtr KeyboardCallback(int nCode, IntPtr wParam, IntPtr lParam)
    {
        if (nCode >= 0 && Counting)
        {
            int msg = wParam.ToInt32();
            if (msg == NativeMethods.WM_KEYDOWN || msg == NativeMethods.WM_SYSKEYDOWN)
                Interlocked.Increment(ref _keystrokes); // count only; lParam (the key) is never read
        }
        return NativeMethods.CallNextHookEx(_kbHook, nCode, wParam, lParam);
    }

    private IntPtr MouseCallback(int nCode, IntPtr wParam, IntPtr lParam)
    {
        if (nCode >= 0 && Counting && wParam.ToInt32() == NativeMethods.WM_MOUSEMOVE)
            Interlocked.Increment(ref _mouseMoves);
        return NativeMethods.CallNextHookEx(_mouseHook, nCode, wParam, lParam);
    }

    /// <summary>Call about once per second.</summary>
    public void Tick()
    {
        string app = NativeMethods.GetForegroundAppName(); // outside the lock (may be slow)
        long idle = NativeMethods.GetIdleMilliseconds();
        lock (_gate)
        {
            long now = _clock.ElapsedMilliseconds;
            double delta = (now - _lastTickMs) / 1000.0;
            _lastTickMs = now;
            if (delta <= 0) return;
            if (delta > 5) delta = 1; // timer was stalled (sleep / blocked): don't invent time

            RollDayIfNeeded();
            Away = idle < 0 || idle >= _awayThresholdMs;
            if (!Counting || Away) return; // paused, locked, or away: not working time

            if (idle < _idleThresholdMs) _activeSec += delta;
            else _idleSec += delta;
            if (app.Length > 0)
                _appSeconds[app] = (_appSeconds.TryGetValue(app, out var c) ? c : 0) + delta;
        }
    }

    private void RollDayIfNeeded()
    {
        var today = DateOnly.FromDateTime(DateTime.Now);
        if (today == _day) return;
        _day = today;
        _dayActive = _dayIdle = 0;
        _dayKeys = _dayMouse = 0;
    }

    /// <summary>
    /// Close the current interval and start a new one. Returns null when nothing
    /// was recorded (e.g. the whole interval was paused or away).
    /// </summary>
    public IntervalData? FlushInterval()
    {
        lock (_gate)
        {
            var now = DateTime.UtcNow;
            var start = _intervalStartUtc;
            long keys = Interlocked.Exchange(ref _keystrokes, 0);
            long mouse = Interlocked.Exchange(ref _mouseMoves, 0);
            int span = (int)Math.Floor((now - start).TotalSeconds);
            int active = Math.Min((int)Math.Round(_activeSec), Math.Max(span, 0));
            int idle = Math.Max(0, Math.Min((int)Math.Round(_idleSec), span - active));
            string topApp = _appSeconds.Count > 0
                ? _appSeconds.OrderByDescending(kv => kv.Value).First().Key
                : "";

            RollDayIfNeeded();
            _dayActive += active;
            _dayIdle += idle;
            _dayKeys += keys;
            _dayMouse += mouse;

            _activeSec = _idleSec = 0;
            _appSeconds.Clear();
            _intervalStartUtc = now;

            if (active + idle == 0 || span <= 0) return null;
            return new IntervalData(Guid.NewGuid().ToString("D"), start, now, active, idle, keys, mouse, topApp);
        }
    }

    /// <summary>Discard the in-progress interval and start fresh (after resume / unlock / wake).</summary>
    public void StartNewInterval()
    {
        lock (_gate)
        {
            Interlocked.Exchange(ref _keystrokes, 0);
            Interlocked.Exchange(ref _mouseMoves, 0);
            _activeSec = _idleSec = 0;
            _appSeconds.Clear();
            _intervalStartUtc = DateTime.UtcNow;
            _lastTickMs = _clock.ElapsedMilliseconds;
        }
    }

    // ---- Live values for the status window (today, including the current interval) ----
    public int TodayActiveSeconds { get { lock (_gate) { RollDayIfNeeded(); return (int)(_dayActive + _activeSec); } } }
    public int TodayIdleSeconds { get { lock (_gate) { RollDayIfNeeded(); return (int)(_dayIdle + _idleSec); } } }
    public long TodayKeystrokes { get { lock (_gate) { RollDayIfNeeded(); return _dayKeys + Interlocked.Read(ref _keystrokes); } } }
    public long TodayMouseMoves { get { lock (_gate) { RollDayIfNeeded(); return _dayMouse + Interlocked.Read(ref _mouseMoves); } } }

    public void Dispose()
    {
        if (_hookThread is { IsAlive: true } && _hookThreadId != 0)
        {
            NativeMethods.PostThreadMessage(_hookThreadId, NativeMethods.WM_QUIT, IntPtr.Zero, IntPtr.Zero);
            _hookThread.Join(TimeSpan.FromSeconds(2));
        }
        _hooksReady.Dispose();
    }
}
