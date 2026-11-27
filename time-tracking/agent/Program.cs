using System.Windows.Forms;

namespace HrgAgent;

internal static class Program
{
    public const string MutexName = @"Local\HrgTracker_SingleInstance";

    public const string TrackedInfoText =
        "HRG Tracker mencatat, selama jam kerja:\n\n" +
        "  • Waktu aktif vs idle (layar terkunci / dijeda / ditinggal TIDAK dihitung)\n" +
        "  • JUMLAH ketikan keyboard (bukan tombol atau isi apa pun)\n" +
        "  • JUMLAH gerakan mouse\n" +
        "  • Nama aplikasi yang sedang aktif\n\n" +
        "TIDAK mencatat: isi ketikan, password, atau screenshot layar.\n" +
        "Ikon selalu terlihat di tray; Anda bisa jeda atau keluar kapan saja.";

    [STAThread]
    private static void Main()
    {
        // One instance per user session (Local\ so other users on the same PC are tracked too).
        Mutex mutex;
        try
        {
            mutex = new Mutex(true, MutexName, out bool isNew);
            if (!isNew) { mutex.Dispose(); return; }
        }
        catch (Exception) { return; } // e.g. access denied: another instance owns it

        using (mutex)
        {
            ApplicationConfiguration.Initialize();

            var cfg = Config.Load();
            var state = UserState.Load(cfg);

            if (string.IsNullOrWhiteSpace(cfg.AgentToken))
            {
                MessageBox.Show(
                    "Token agent belum diatur.\n\n" +
                    "Jalankan ulang installer dan isi Agent Token, atau isi \"agentToken\" di:\n" +
                    $"{Config.ConfigPath}\n\nToken didapat admin dari dashboard HRG HRD (menu Pengaturan, bagian Kelola karyawan).",
                    "HRG Tracker: konfigurasi", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            // First-run consent: transparency is required, so we ask explicitly.
            if (!state.Consented)
            {
                var r = MessageBox.Show(TrackedInfoText + "\n\nLanjutkan?", "HRG Tracker: persetujuan",
                    MessageBoxButtons.OKCancel, MessageBoxIcon.Information);
                if (r != DialogResult.OK) return;
                state.Consented = true;
                state.Save();
            }

            Application.Run(new TrayApplicationContext(cfg, state));
        }
    }
}
