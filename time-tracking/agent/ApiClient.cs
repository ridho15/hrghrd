using System.Net;
using System.Net.Http.Headers;
using System.Net.Http.Json;

namespace HrgAgent;

/// <summary>Posts heartbeats and interval data to the HRG HRD backend.</summary>
public sealed class ApiClient
{
    private readonly HttpClient _http;
    private readonly string _baseUrl;

    public static readonly string Version =
        typeof(ApiClient).Assembly.GetName().Version?.ToString(3) ?? "0.0.0";

    public ApiClient(string serverUrl, string agentToken)
    {
        _baseUrl = serverUrl.TrimEnd('/');
        _http = new HttpClient { Timeout = TimeSpan.FromSeconds(20) };
        _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", agentToken);
        _http.DefaultRequestHeaders.UserAgent.ParseAdd($"HrgTracker/{Version}");
    }

    public string Hostname { get; } = Environment.MachineName;
    public bool UsesHttps => _baseUrl.StartsWith("https://", StringComparison.OrdinalIgnoreCase);

    public async Task<bool> HeartbeatAsync(bool paused)
    {
        try
        {
            using var res = await _http.PostAsJsonAsync($"{_baseUrl}/api/agent/heartbeat",
                new { hostname = Hostname, agent_version = Version, paused }).ConfigureAwait(false);
            return res.IsSuccessStatusCode;
        }
        catch { return false; }
    }

    public async Task<SendResult> SendIntervalAsync(IntervalData d)
    {
        try
        {
            var payload = new
            {
                client_id = d.ClientId,
                hostname = Hostname,
                agent_version = Version,
                started_at = d.StartedAtUtc.ToString("o"),
                ended_at = d.EndedAtUtc.ToString("o"),
                active_seconds = d.ActiveSeconds,
                idle_seconds = d.IdleSeconds,
                keystrokes = d.Keystrokes,
                mouse_moves = d.MouseMoves,
                foreground_app = d.ForegroundApp,
            };
            using var res = await _http.PostAsJsonAsync($"{_baseUrl}/api/agent/activity", payload).ConfigureAwait(false);
            if (res.IsSuccessStatusCode) return SendResult.Sent; // 201 new / 200 duplicate
            int code = (int)res.StatusCode;
            // Transient: bad/rotated token (admin may fix it), timeouts, rate limits, server errors.
            if (res.StatusCode is HttpStatusCode.Unauthorized or HttpStatusCode.RequestTimeout
                or HttpStatusCode.TooManyRequests || code >= 500)
                return SendResult.Retry;
            return SendResult.Drop; // 400/413/422: the server will never accept this interval
        }
        catch { return SendResult.Retry; } // network error / timeout
    }
}
