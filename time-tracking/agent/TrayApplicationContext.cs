using System.Drawing;
using System.Windows.Forms;
using Microsoft.Win32;

namespace HrgAgent;

/// <summary>
/// Owns the tray icon and the once-per-second loop. The icon is ALWAYS visible:
/// the agent is not a hidden process; the employee can open the status window,
/// pause tracking, or quit at any time.
/// </summary>
public sealed class TrayApplicationContext : ApplicationContext
{
    private readonly Config _cfg;
    private readonly UserState _state;
    private readonly ActivityTracker _tracker;
    private readonly ApiClient _api;
    private readonly OfflineQueue _queue;
    private readonly NotifyIcon _tray;
    private readonly System.Windows.Forms.Timer _timer = new() { Interval = 1000 };
    private readonly ToolStripMenuItem _pauseItem;
    private readonly ToolStripMenuItem _autostartItem;
    private StatusForm? _status;

    private int _secondsSinceFlush;
    private int _secondsSinceHeartbeat;
    private bool _quitting;

    public TrayApplicationContext(Config cfg, UserState state)
    {
        _cfg = cfg;
        _state = state;
        _tracker = new ActivityTracker(cfg.IdleThresholdSeconds, cfg.AwayThresholdSeconds);
        _api = new ApiClient(cfg.ServerUrl, cfg.AgentToken);
        _queue = new OfflineQueue(UserState.DataDir);

        _pauseItem = new ToolStripMenuItem("Jeda pelacakan", null, (_, _) => TogglePause());
        _autostartItem = new ToolStripMenuItem("Jalan otomatis saat Windows nyala", null, (_, _) => ToggleAutostart());

        var menu = new ContextMenuStrip();
        menu.Items.Add(new ToolStripMenuItem("Buka HRG Tracker", null, (_, _) => ShowStatus()));
        menu.Items.Add(_pauseItem);
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add(_autostartItem);
        menu.Items.Add(new ToolStripMenuItem("Apa yang dilacak?", null, (_, _) => ShowConsentInfo()));
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add(new ToolStripMenuItem("Keluar", null, (_, _) => Quit()));

        _tray = new NotifyIcon
        {
            Icon = SystemIcons.Application,
            Text = "HRG Tracker: aktif",
            Visible = true,
            ContextMenuStrip = menu,
        };
        _tray.DoubleClick += (_, _) => ShowStatus();

        // Respect the user's autostart choice (the installer may have re-added the Run key).
        if (_state.Autostart) Autostart.Enable(); else Autostart.Disable();
        _autostartItem.Checked = Autostart.IsEnabled();

        _tracker.Start();
        if (!_tracker.HooksInstalled)
            _tray.ShowBalloonTip(6000, "HRG Tracker", "Penghitung keyboard/mouse gagal aktif. Waktu kerja tetap tercatat.", ToolTipIcon.Warning);

        SystemEvents.SessionSwitch += OnSessionSwitch;
        SystemEvents.PowerModeChanged += OnPowerModeChanged;
        SystemEvents.SessionEnding += OnSessionEnding;
        Application.ApplicationExit += (_, _) => Quit();

        _timer.Tick += (_, _) => OnTick();
        _timer.Start();

        _tray.ShowBalloonTip(4000, "HRG Tracker", "Pelacakan waktu aktif (ikon ada di tray).", ToolTipIcon.Info);
        _ = _api.HeartbeatAsync(false);
        _ = _queue.FlushAsync(_api.SendIntervalAsync); // resend anything left from last time
    }

    private void OnTick()
    {
        _tracker.Tick();

        if (++_secondsSinceHeartbeat >= _cfg.HeartbeatSeconds)
        {
            _secondsSinceHeartbeat = 0;
            _ = _api.HeartbeatAsync(_tracker.Paused); // also while paused, so we don't look "offline"
            if (_queue.Count > 0) _ = _queue.FlushAsync(_api.SendIntervalAsync);
        }

        if (++_secondsSinceFlush >= _cfg.ReportIntervalSeconds)
        {
            _secondsSinceFlush = 0;
            CloseIntervalAndSend();
        }
        UpdateTrayText();
    }

    /// <summary>Close the current interval, persist it, and kick off a (non-blocking) send.</summary>
    private void CloseIntervalAndSend(bool send = true)
    {
        var data = _tracker.FlushInterval();
        if (data != null) _queue.Enqueue(data); // persisted to disk immediately
        if (send) _ = _queue.FlushAsync(_api.SendIntervalAsync);
    }

    // ---- Session / power events (may arrive on a SystemEvents thread; tracker & queue are thread-safe) ----
    private void OnSessionSwitch(object? sender, SessionSwitchEventArgs e)
    {
        switch (e.Reason)
        {
            case SessionSwitchReason.SessionLock:
            case SessionSwitchReason.ConsoleDisconnect:
            case SessionSwitchReason.RemoteDisconnect:
                CloseIntervalAndSend();
                _tracker.Locked = true;   // locked screen is not working time
                break;
            case SessionSwitchReason.SessionUnlock:
            case SessionSwitchReason.ConsoleConnect:
            case SessionSwitchReason.RemoteConnect:
                _tracker.StartNewInterval();
                _tracker.Locked = false;
                break;
        }
    }

    private void OnPowerModeChanged(object? sender, PowerModeChangedEventArgs e)
    {
        if (e.Mode == PowerModes.Suspend) CloseIntervalAndSend(send: false); // saved to disk before sleep
        else if (e.Mode == PowerModes.Resume)
        {
            _tracker.StartNewInterval();
            _ = _queue.FlushAsync(_api.SendIntervalAsync);
        }
    }

    // Logoff / shutdown: save the last minutes to disk (no time for the network).
    private void OnSessionEnding(object? sender, SessionEndingEventArgs e) => CloseIntervalAndSend(send: false);

    private void UpdateTrayText()
    {
        string s = _tracker.Paused ? "dijeda" : _tracker.Locked ? "layar terkunci" : "aktif";
        if (_queue.Count > 0) s += $" · {_queue.Count} antre";
        string text = $"HRG Tracker: {s}";
        _tray.Text = text.Length > 63 ? text[..63] : text;
    }

    private void ShowStatus()
    {
        if (_status == null || _status.IsDisposed) _status = new StatusForm(_tracker, _queue, _cfg, _api);
        _status.Show();
        _status.WindowState = FormWindowState.Normal;
        _status.BringToFront();
        _status.Activate();
    }

    private void TogglePause()
    {
        if (!_tracker.Paused)
        {
            CloseIntervalAndSend();   // report work done so far, then stop counting
            _tracker.Paused = true;
        }
        else
        {
            _tracker.StartNewInterval();
            _tracker.Paused = false;
        }
        _pauseItem.Text = _tracker.Paused ? "Lanjutkan pelacakan" : "Jeda pelacakan";
        _ = _api.HeartbeatAsync(_tracker.Paused);
        UpdateTrayText();
        _tray.ShowBalloonTip(2000, "HRG Tracker",
            _tracker.Paused ? "Pelacakan dijeda. Tidak ada yang dihitung." : "Pelacakan dilanjutkan.", ToolTipIcon.Info);
    }

    private void ToggleAutostart()
    {
        _state.Autostart = !Autostart.IsEnabled();
        if (_state.Autostart) Autostart.Enable(); else Autostart.Disable();
        _state.Save();
        _autostartItem.Checked = Autostart.IsEnabled();
    }

    private void ShowConsentInfo() => MessageBox.Show(Program.TrackedInfoText, "Apa yang dilacak?",
        MessageBoxButtons.OK, MessageBoxIcon.Information);

    private void Quit()
    {
        if (_quitting) return;
        _quitting = true;
        _timer.Stop();
        CloseIntervalAndSend(send: false);
        // Run the final send on the thread pool (no UI-thread deadlock); give it a few seconds.
        try { Task.Run(() => _queue.FlushAsync(_api.SendIntervalAsync)).Wait(TimeSpan.FromSeconds(4)); } catch { }

        SystemEvents.SessionSwitch -= OnSessionSwitch;
        SystemEvents.PowerModeChanged -= OnPowerModeChanged;
        SystemEvents.SessionEnding -= OnSessionEnding;
        _tracker.Dispose();
        _status?.Dispose();
        _tray.Visible = false;
        _tray.Dispose();
        ExitThread();
    }
}
