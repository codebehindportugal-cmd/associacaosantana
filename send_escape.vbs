Set oShell = CreateObject("WScript.Shell")
oShell.AppActivate "cmd.exe"
WScript.Sleep 500
oShell.SendKeys "{ESC}"
WScript.Sleep 500
oShell.SendKeys "{ESC}"
