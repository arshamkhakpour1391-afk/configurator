# Windows TDR

Timeout Detection and Recovery kills a GPU kernel that runs longer than 2 seconds by default. An SD 1.5 step on an RX 590 often exceeds that. The symptom is a window flash, a driver popup, and a Python process that is simply gone.

## Fix

1. Run `scripts/Check-Rx590.ps1`.
2. If `TdrDelay` is missing or below 30, double-click `scripts/Enable-LongGPUTimeout.reg` or run the script with `-Apply` from an elevated PowerShell.
3. Reboot. The key is not live until then.
4. The file sets `TdrDelay` and `TdrDdiDelay` to 60 seconds under `HKLM\SYSTEM\CurrentControlSet\Control\GraphicsDrivers`.

This does not make a bad overclock stable. If the card artifacts, lower clocks, do not raise TDR further.

Linux has no TDR. Look at `dmesg` for `amdgpu` and `GPU reset` instead.
