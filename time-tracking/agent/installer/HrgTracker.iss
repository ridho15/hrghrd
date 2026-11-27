; Inno Setup 6 script: one-click, per-user installer for the HRG Tracker agent.
; Build (on Windows, after `dotnet publish ... -o agent\dist`):
;   iscc /DMyAppVersion=0.2.0 agent\installer\HrgTracker.iss  -> Output\HrgTracker-Setup-<ver>.exe
; The GitHub Actions workflow does both steps automatically.
;
; Interactive:  double-click, enter Server URL + Agent Token, Next, Finish.
; Silent/mass:  HrgTracker-Setup-<ver>.exe /VERYSILENT /SERVER="https://hrd.example.com" /TOKEN="..."
; Upgrades keep the existing Server URL + token when none are given.

#ifndef MyAppVersion
  #define MyAppVersion "0.2.0"
#endif
#define MyAppName "HRG Tracker"
#define MyAppPublisher "HRG HRD"
#define MyAppExe "HrgTracker.exe"

[Setup]
AppId={{8F9A3C21-5B7D-4E62-9A10-7C3E2D4F1B88}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
; Per-user install (no admin rights). The agent keeps its queue/state in the same folder.
DefaultDirName={localappdata}\HrgTracker
PrivilegesRequired=lowest
DisableProgramGroupPage=yes
DisableDirPage=yes
; We stop the running agent ourselves in PrepareToInstall.
CloseApplications=no
OutputDir=Output
OutputBaseFilename=HrgTracker-Setup-{#MyAppVersion}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
UninstallDisplayName={#MyAppName}
UninstallDisplayIcon={app}\{#MyAppExe}

[Languages]
; Standard (English) wizard messages; our own pages/messages are in Indonesian.
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "..\dist\{#MyAppExe}"; DestDir: "{app}"; Flags: ignoreversion

[Registry]
; Autostart for this user (also correct for silent installs). Removed on uninstall.
; The agent removes it again at startup if the employee turned autostart off.
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "HrgTracker"; ValueData: """{app}\{#MyAppExe}"""; Flags: uninsdeletevalue

[Run]
; Also runs after a silent install, so tracking starts without a reboot
; (the employee still sees the consent dialog on first run).
Filename: "{app}\{#MyAppExe}"; Description: "Jalankan HRG Tracker sekarang"; Flags: nowait postinstall

[UninstallRun]
Filename: "{sys}\taskkill.exe"; Parameters: "/IM {#MyAppExe} /F"; Flags: runhidden; RunOnceId: "KillAgent"

[UninstallDelete]
; Token (appsettings), unsent queue and per-user state must not be left behind.
Type: files; Name: "{app}\appsettings.json"
Type: files; Name: "{app}\pending.json"
Type: files; Name: "{app}\pending.json.tmp"
Type: files; Name: "{app}\pending.json.corrupt-*"
Type: files; Name: "{app}\state.json"
Type: files; Name: "{app}\state.json.tmp"
Type: dirifempty; Name: "{app}"

[Code]
var
  ConfigPage: TInputQueryWizardPage;
  ExistingServer, ExistingToken: String;

{ Read a /NAME=value command-line switch. }
function GetParam(const Name: String): String;
var
  I: Integer;
  P, Prefix: String;
begin
  Result := '';
  Prefix := '/' + Uppercase(Name) + '=';
  for I := 1 to ParamCount do
  begin
    P := ParamStr(I);
    if Pos(Prefix, Uppercase(P)) = 1 then
      Result := Trim(Copy(P, Length(Prefix) + 1, MaxInt));
  end;
end;

function Pick(const A, B: String): String;
begin
  if A <> '' then Result := A else Result := B;
end;

{ Minimal reader for "key": "value" in the existing appsettings.json. }
function ReadJsonString(const Json, Key: String): String;
var
  P: Integer;
  S: String;
begin
  Result := '';
  P := Pos('"' + Key + '"', Json);
  if P = 0 then Exit;
  S := Copy(Json, P + Length(Key) + 2, MaxInt);
  P := Pos(':', S);
  if P = 0 then Exit;
  S := Copy(S, P + 1, MaxInt);
  P := Pos('"', S);
  if P = 0 then Exit;
  S := Copy(S, P + 1, MaxInt);
  P := Pos('"', S);
  if P = 0 then Exit;
  Result := Copy(S, 1, P - 1);
end;

procedure LoadExisting;
var
  Json: AnsiString;
begin
  ExistingServer := '';
  ExistingToken := '';
  if LoadStringFromFile(ExpandConstant('{localappdata}\HrgTracker\appsettings.json'), Json) then
  begin
    ExistingServer := ReadJsonString(String(Json), 'serverUrl');
    ExistingToken := ReadJsonString(String(Json), 'agentToken');
  end;
end;

{ Printable ASCII without spaces, quotes or backslashes (keeps the JSON valid). }
function IsSafeValue(const S: String): Boolean;
var
  I: Integer;
begin
  Result := Length(S) > 0;
  for I := 1 to Length(S) do
    if (Ord(S[I]) < 33) or (Ord(S[I]) > 126) or (S[I] = '"') or (S[I] = '\') then
    begin
      Result := False;
      Exit;
    end;
end;

function IsHttpUrl(const S: String): Boolean;
begin
  Result := (Pos('http://', Lowercase(S)) = 1) or (Pos('https://', Lowercase(S)) = 1);
end;

function InitializeSetup: Boolean;
var
  Server, Token: String;
begin
  Result := True;
  LoadExisting;
  if WizardSilent then
  begin
    Server := Pick(GetParam('SERVER'), ExistingServer);
    Token := Pick(GetParam('TOKEN'), ExistingToken);
    if not (IsSafeValue(Server) and IsHttpUrl(Server) and IsSafeValue(Token)) then
    begin
      Log('Silent install aborted: valid /SERVER= and /TOKEN= are required (or an existing configuration).');
      Result := False;
    end;
  end;
end;

procedure InitializeWizard;
begin
  ConfigPage := CreateInputQueryPage(wpSelectDir,
    'Konfigurasi HRG Tracker',
    'Masukkan alamat server dan token karyawan.',
    'Keduanya didapat dari admin dashboard HRG HRD (menu Pengaturan, bagian Kelola karyawan). ' +
    'Token unik untuk setiap karyawan.');
  ConfigPage.Add('Server URL (mis. https://hrd.perusahaan.co.id):', False);
  ConfigPage.Add('Agent Token:', False);
  ConfigPage.Values[0] := Pick(GetParam('SERVER'), ExistingServer);
  ConfigPage.Values[1] := Pick(GetParam('TOKEN'), ExistingToken);
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  Server, Token: String;
begin
  Result := True;
  if WizardSilent or (CurPageID <> ConfigPage.ID) then Exit;
  Server := Trim(ConfigPage.Values[0]);
  Token := Trim(ConfigPage.Values[1]);
  if not (IsSafeValue(Server) and IsHttpUrl(Server)) then
  begin
    MsgBox('Server URL harus diawali http:// atau https:// dan tanpa spasi.', mbError, MB_OK);
    Result := False;
    Exit;
  end;
  if not IsSafeValue(Token) then
  begin
    MsgBox('Agent Token wajib diisi (salin dari dashboard: menu Pengaturan, bagian Kelola karyawan, tombol Salin token).', mbError, MB_OK);
    Result := False;
    Exit;
  end;
  if (Pos('http://', Lowercase(Server)) = 1) and (Pos('localhost', Lowercase(Server)) = 0)
     and (Pos('127.0.0.1', Server) = 0) then
    if MsgBox('Server memakai http:// (tidak terenkripsi), sehingga token dikirim tanpa enkripsi.' + #13#10 +
              'Disarankan memakai https://. Tetap lanjutkan?', mbConfirmation, MB_YESNO) = IDNO then
      Result := False;
end;

{ Stop a running agent so its exe can be replaced during an upgrade. }
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ResultCode: Integer;
begin
  Exec(ExpandConstant('{sys}\taskkill.exe'), '/IM {#MyAppExe} /F', '', SW_HIDE,
       ewWaitUntilTerminated, ResultCode);
  Sleep(500);
  Result := '';
end;

{ Write appsettings.json next to the exe (UTF-8). Unchanged values keep the file as-is. }
procedure WriteConfig;
var
  Server, Token: String;
  Lines: TArrayOfString;
begin
  if WizardSilent then
  begin
    Server := Pick(GetParam('SERVER'), ExistingServer);
    Token := Pick(GetParam('TOKEN'), ExistingToken);
  end
  else
  begin
    Server := Trim(ConfigPage.Values[0]);
    Token := Trim(ConfigPage.Values[1]);
  end;
  if not (IsSafeValue(Server) and IsSafeValue(Token)) then Exit;
  if (Server = ExistingServer) and (Token = ExistingToken) then Exit;

  SetArrayLength(Lines, 8);
  Lines[0] := '{';
  Lines[1] := '  "serverUrl": "' + Server + '",';
  Lines[2] := '  "agentToken": "' + Token + '",';
  Lines[3] := '  "reportIntervalSeconds": 600,';
  Lines[4] := '  "heartbeatSeconds": 60,';
  Lines[5] := '  "idleThresholdSeconds": 60,';
  Lines[6] := '  "awayThresholdSeconds": 600';
  Lines[7] := '}';
  if not SaveStringsToUTF8File(ExpandConstant('{app}\appsettings.json'), Lines, False) then
    if WizardSilent then Log('Failed to write appsettings.json')
    else MsgBox('Gagal menulis konfigurasi agent.', mbError, MB_OK);
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssPostInstall then
    WriteConfig;
end;
