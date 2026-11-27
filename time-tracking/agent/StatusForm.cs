using System.Drawing;
using System.Windows.Forms;

namespace HrgAgent;

/// <summary>
/// Visible status window (opened from the tray). Keeps monitoring transparent:
/// the employee always sees exactly what has been recorded today.
/// Visual language follows DESIGN.md: one focal number in the brand color,
/// everything else in plain ink, borders instead of shadows, no emoji.
/// Font is Segoe UI because this is a native Windows window (the system face).
/// </summary>
public sealed class StatusForm : Form
{
    private static readonly Color Bg = Color.FromArgb(248, 250, 252);       // slate-50
    private static readonly Color Line = Color.FromArgb(226, 232, 240);     // slate-200
    private static readonly Color Track = Color.FromArgb(241, 245, 249);    // slate-100
    private static readonly Color Ink = Color.FromArgb(15, 23, 42);         // slate-900
    private static readonly Color Muted = Color.FromArgb(71, 85, 105);      // slate-600
    private static readonly Color Brand = Color.FromArgb(4, 120, 87);       // emerald-700
    private static readonly Color BrandBar = Color.FromArgb(5, 150, 105);   // emerald-600
    private static readonly Color Warn = Color.FromArgb(180, 83, 9);        // amber-700
    private static readonly Color Danger = Color.FromArgb(190, 18, 60);     // rose-700

    private readonly ActivityTracker _tracker;
    private readonly OfflineQueue _queue;
    private readonly Config _cfg;
    private readonly ApiClient _api;

    private readonly Label _state = MakeLabel(10f, FontStyle.Bold, Ink);
    private readonly Label _work = MakeLabel(30f, FontStyle.Bold, Ink);
    private readonly Panel _rule = new() { BackColor = Track, Height = 6 };
    private readonly Panel _ruleFill = new() { BackColor = BrandBar, Height = 6, Width = 0 };
    private readonly Label _ruleCaption = MakeLabel(9f, FontStyle.Regular, Muted);
    private readonly Label _activity = MakeLabel(14f, FontStyle.Bold, Ink);
    private readonly Label _keys = MakeLabel(14f, FontStyle.Bold, Ink);
    private readonly Label _mouse = MakeLabel(14f, FontStyle.Bold, Ink);
    private readonly Label _footer = MakeLabel(8.5f, FontStyle.Regular, Muted);
    private readonly System.Windows.Forms.Timer _ui = new() { Interval = 1000 };

    private const int Pad = 16;
    private const int Width_ = 340;
    private const double TargetHours = 8;

    public StatusForm(ActivityTracker tracker, OfflineQueue queue, Config cfg, ApiClient api)
    {
        _tracker = tracker;
        _queue = queue;
        _cfg = cfg;
        _api = api;

        Text = "HRG Tracker";
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedSingle;
        MaximizeBox = false;
        AutoScaleMode = AutoScaleMode.Dpi;
        ClientSize = new Size(Width_, 400);
        BackColor = Bg;
        Font = new Font("Segoe UI", 9f);

        int y = 14;
        _state.Location = new Point(Pad, y);
        Controls.Add(_state);
        y += 30;

        // Focal surface: working time today, with the time rule beneath it.
        var focus = Surface(y, 120);
        focus.Controls.Add(Caption("Waktu kerja hari ini", 12, 10));
        _work.Location = new Point(10, 28);
        focus.Controls.Add(_work);
        _rule.Location = new Point(12, 82);
        _rule.Width = Width_ - 2 * Pad - 24;
        _rule.Controls.Add(_ruleFill);
        focus.Controls.Add(_rule);
        _ruleCaption.Location = new Point(10, 94);
        focus.Controls.Add(_ruleCaption);
        Controls.Add(focus);
        y += 132;

        // Secondary figures in one row, plain ink.
        var row = Surface(y, 70);
        int col = (Width_ - 2 * Pad) / 3;
        AddMetric(row, "Waktu aktif", _activity, 0);
        AddMetric(row, "Ketikan", _keys, col);
        AddMetric(row, "Gerakan mouse", _mouse, col * 2);
        Controls.Add(row);
        y += 82;

        _footer.Location = new Point(Pad, y);
        _footer.MaximumSize = new Size(Width_ - 2 * Pad, 0);
        Controls.Add(_footer);

        _ui.Tick += (_, _) => RefreshValues();
        _ui.Start();
        RefreshValues();
    }

    private static Label MakeLabel(float size, FontStyle style, Color color) =>
        new() { AutoSize = true, Font = new Font("Segoe UI", size, style), ForeColor = color, BackColor = Color.Transparent };

    private static Label Caption(string text, int x, int y)
    {
        var l = MakeLabel(9f, FontStyle.Regular, Muted);
        l.Text = text;
        l.Location = new Point(x, y);
        return l;
    }

    private Panel Surface(int y, int height)
    {
        var p = new Panel { Location = new Point(Pad, y), Size = new Size(Width_ - 2 * Pad, height), BackColor = Color.White };
        p.Paint += (_, e) =>
        {
            using var pen = new Pen(Line);
            e.Graphics.DrawRectangle(pen, 0, 0, p.Width - 1, p.Height - 1);
        };
        return p;
    }

    private static void AddMetric(Panel parent, string title, Label value, int x)
    {
        parent.Controls.Add(Caption(title, x + 12, 10));
        value.Location = new Point(x + 10, 30);
        parent.Controls.Add(value);
    }

    private void RefreshValues()
    {
        int active = _tracker.TodayActiveSeconds, idle = _tracker.TodayIdleSeconds;
        int work = active + idle;
        var t = TimeSpan.FromSeconds(work);
        _work.Text = $"{(int)t.TotalHours}:{t.Minutes:00} jam";
        double share = Math.Min(1.0, work / (TargetHours * 3600));
        _ruleFill.Width = (int)(_rule.Width * share);
        _ruleCaption.Text = $"{share * 100:0}% dari {TargetHours:0} jam";
        _activity.Text = work > 0 ? $"{active * 100.0 / work:0}%" : "0%";
        _keys.Text = _tracker.TodayKeystrokes.ToString("N0");
        _mouse.Text = _tracker.TodayMouseMoves.ToString("N0");

        if (_tracker.Paused) { _state.Text = "Dijeda: tidak ada yang dihitung"; _state.ForeColor = Warn; }
        else if (_tracker.Locked) { _state.Text = "Layar terkunci: tidak dihitung"; _state.ForeColor = Muted; }
        else if (_tracker.Away) { _state.Text = "Tidak ada aktivitas, dianggap istirahat"; _state.ForeColor = Muted; }
        else { _state.Text = "Sedang melacak"; _state.ForeColor = Brand; }

        var lines = new List<string>
        {
            _queue.LastSentUtc is DateTime sent
                ? $"Terakhir terkirim pukul {sent.ToLocalTime():HH.mm}." : "Belum ada data terkirim sejak agent dibuka.",
        };
        if (_queue.Count > 0) lines.Add($"{_queue.Count} interval menunggu dikirim.");
        if (_queue.LastError.Length > 0) lines.Add(_queue.LastError);
        if (!_tracker.HooksInstalled) lines.Add("Penghitung keyboard dan mouse tidak aktif.");
        if (!_api.UsesHttps) lines.Add("Koneksi ke server tidak terenkripsi (http).");
        lines.Add($"Server {_cfg.ServerUrl}, laporan tiap {_cfg.ReportIntervalSeconds / 60} menit, versi {ApiClient.Version}.");
        _footer.Text = string.Join(Environment.NewLine, lines);
        _footer.ForeColor = !_tracker.HooksInstalled || !_api.UsesHttps ? Danger : Muted;
    }

    // Closing the window just hides it; the agent keeps running in the tray.
    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (e.CloseReason == CloseReason.UserClosing) { e.Cancel = true; Hide(); }
        else base.OnFormClosing(e);
    }

    protected override void Dispose(bool disposing)
    {
        if (disposing) _ui.Dispose();
        base.Dispose(disposing);
    }
}
