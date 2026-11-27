using Microsoft.Win32;

namespace HrgAgent;

/// <summary>Per-user autostart via HKCU Run key. No admin rights required.</summary>
internal static class Autostart
{
    private const string RunKey = @"Software\Microsoft\Windows\CurrentVersion\Run";
    private const string ValueName = "HrgTracker";

    private static string ExePath =>
        Environment.ProcessPath ?? Path.Combine(AppContext.BaseDirectory, "HrgTracker.exe");

    public static void Enable()
    {
        try
        {
            using var key = Registry.CurrentUser.OpenSubKey(RunKey, writable: true)
                            ?? Registry.CurrentUser.CreateSubKey(RunKey);
            key?.SetValue(ValueName, $"\"{ExePath}\"");
        }
        catch { /* best effort */ }
    }

    public static void Disable()
    {
        try
        {
            using var key = Registry.CurrentUser.OpenSubKey(RunKey, writable: true);
            key?.DeleteValue(ValueName, throwOnMissingValue: false);
        }
        catch { /* best effort */ }
    }

    public static bool IsEnabled()
    {
        try
        {
            using var key = Registry.CurrentUser.OpenSubKey(RunKey, writable: false);
            return key?.GetValue(ValueName) != null;
        }
        catch { return false; }
    }
}
