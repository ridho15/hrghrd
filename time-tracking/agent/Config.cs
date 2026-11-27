using System.Text.Json;
using System.Text.Json.Serialization;

namespace HrgAgent;

/// <summary>
/// Install-time settings (appsettings.json next to the exe; written by the installer).
/// Values are clamped to safe ranges; HRG_SERVER_URL / HRG_AGENT_TOKEN env vars override.
/// </summary>
public sealed class Config
{
    [JsonPropertyName("serverUrl")] public string ServerUrl { get; set; } = "http://localhost:3000";
    [JsonPropertyName("agentToken")] public string AgentToken { get; set; } = "";
    [JsonPropertyName("reportIntervalSeconds")] public int ReportIntervalSeconds { get; set; } = 600;
    [JsonPropertyName("heartbeatSeconds")] public int HeartbeatSeconds { get; set; } = 60;
    [JsonPropertyName("idleThresholdSeconds")] public int IdleThresholdSeconds { get; set; } = 60;
    [JsonPropertyName("awayThresholdSeconds")] public int AwayThresholdSeconds { get; set; } = 600;

    // Legacy fields (v0.1 kept user state here); read once to migrate into UserState.
    [JsonPropertyName("consented")] public bool? LegacyConsented { get; set; }
    [JsonPropertyName("autostart")] public bool? LegacyAutostart { get; set; }

    public static string ConfigPath => Path.Combine(AppContext.BaseDirectory, "appsettings.json");

    public static Config Load()
    {
        Config cfg;
        try
        {
            cfg = File.Exists(ConfigPath)
                ? JsonSerializer.Deserialize<Config>(File.ReadAllText(ConfigPath)) ?? new Config()
                : new Config();
        }
        catch { cfg = new Config(); }

        cfg.ServerUrl = (Environment.GetEnvironmentVariable("HRG_SERVER_URL") ?? cfg.ServerUrl ?? "").Trim();
        cfg.AgentToken = (Environment.GetEnvironmentVariable("HRG_AGENT_TOKEN") ?? cfg.AgentToken ?? "").Trim();
        cfg.ReportIntervalSeconds = Math.Clamp(cfg.ReportIntervalSeconds, 60, 3600);
        cfg.HeartbeatSeconds = Math.Clamp(cfg.HeartbeatSeconds, 15, 600);
        cfg.IdleThresholdSeconds = Math.Clamp(cfg.IdleThresholdSeconds, 5, 600);
        cfg.AwayThresholdSeconds = Math.Clamp(cfg.AwayThresholdSeconds, cfg.IdleThresholdSeconds, 7200);
        return cfg;
    }
}

/// <summary>Per-user choices (consent, autostart), never touched by the installer.</summary>
public sealed class UserState
{
    [JsonPropertyName("consented")] public bool Consented { get; set; }
    [JsonPropertyName("autostart")] public bool Autostart { get; set; } = true;

    public static string DataDir => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "HrgTracker");
    private static string StatePath => Path.Combine(DataDir, "state.json");

    public static UserState Load(Config legacy)
    {
        try
        {
            if (File.Exists(StatePath))
                return JsonSerializer.Deserialize<UserState>(File.ReadAllText(StatePath)) ?? new UserState();
        }
        catch { /* fall through to defaults */ }
        return new UserState
        {
            Consented = legacy.LegacyConsented ?? false,
            Autostart = legacy.LegacyAutostart ?? true,
        };
    }

    public void Save()
    {
        try
        {
            Directory.CreateDirectory(DataDir);
            string tmp = StatePath + ".tmp";
            File.WriteAllText(tmp, JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true }));
            File.Move(tmp, StatePath, overwrite: true);
        }
        catch { /* best effort */ }
    }
}
