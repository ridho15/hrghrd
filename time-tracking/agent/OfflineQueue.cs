using System.Text.Json;

namespace HrgAgent;

public enum SendResult { Sent, Retry, Drop }

/// <summary>
/// Durable, thread-safe queue of unsent intervals (LocalAppData\HrgTracker\pending.json).
/// Only one flush runs at a time; it stops at the first network/server failure and
/// keeps the rest for later. Each interval carries a client id, so the server ignores
/// anything re-sent after a crash, so nothing is counted twice.
/// </summary>
public sealed class OfflineQueue
{
    private readonly string _file;
    private readonly object _lock = new();
    private readonly SemaphoreSlim _flushGate = new(1, 1);
    private readonly List<IntervalData> _pending = new();
    private const int MaxPending = 5000; // ~34 days of 10-minute intervals

    public DateTime? LastSentUtc { get; private set; }
    public string LastError { get; private set; } = "";

    public OfflineQueue(string dataDir)
    {
        Directory.CreateDirectory(dataDir);
        _file = Path.Combine(dataDir, "pending.json");
        Load();
    }

    public int Count { get { lock (_lock) return _pending.Count; } }

    public void Enqueue(IntervalData d)
    {
        lock (_lock)
        {
            _pending.Add(d);
            if (_pending.Count > MaxPending) _pending.RemoveRange(0, _pending.Count - MaxPending);
            Save();
        }
    }

    /// <summary>Send queued intervals in order. No-op if a flush is already running.</summary>
    public async Task FlushAsync(Func<IntervalData, Task<SendResult>> sender)
    {
        if (!await _flushGate.WaitAsync(0).ConfigureAwait(false)) return;
        try
        {
            int sinceSave = 0;
            while (true)
            {
                IntervalData? next;
                lock (_lock) next = _pending.Count > 0 ? _pending[0] : null;
                if (next is null) break;

                var result = await sender(next).ConfigureAwait(false);
                if (result == SendResult.Retry)
                {
                    LastError = "Server tidak terjangkau. Data disimpan dan dikirim ulang nanti.";
                    break;
                }
                lock (_lock)
                {
                    _pending.Remove(next);
                    if (++sinceSave >= 25) { Save(); sinceSave = 0; }
                }
                if (result == SendResult.Sent) { LastSentUtc = DateTime.UtcNow; LastError = ""; }
                else LastError = "Satu interval ditolak server dan diabaikan.";
            }
            lock (_lock) Save();
        }
        finally { _flushGate.Release(); }
    }

    private void Load()
    {
        try
        {
            if (!File.Exists(_file)) return;
            var items = JsonSerializer.Deserialize<List<IntervalData>>(File.ReadAllText(_file));
            if (items != null) _pending.AddRange(items.Where(i => !string.IsNullOrEmpty(i.ClientId)));
        }
        catch
        {
            // Keep the unreadable file for inspection instead of silently overwriting it.
            try { File.Move(_file, _file + $".corrupt-{DateTime.UtcNow:yyyyMMddHHmmss}", overwrite: true); } catch { }
        }
    }

    // Caller holds _lock. Write-then-rename so a crash mid-write can't corrupt the queue.
    private void Save()
    {
        try
        {
            string tmp = _file + ".tmp";
            File.WriteAllText(tmp, JsonSerializer.Serialize(_pending));
            File.Move(tmp, _file, overwrite: true);
        }
        catch { /* best effort; retried on the next save */ }
    }
}
