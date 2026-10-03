# Inspect the RX 590 setup. Does not change the machine unless -Apply is passed
# from an elevated PowerShell.
param([switch]$Apply)

$ErrorActionPreference = "Continue"
Write-Host "Polaris Studio v6 — RX 590 check" -ForegroundColor Cyan

$gpus = Get-CimInstance Win32_VideoController | Select-Object Name, AdapterRAM, DriverVersion, PNPDeviceID
$gpus | Format-List
foreach ($gpu in $gpus) {
    $reported = [int64]$gpu.AdapterRAM
    if ($gpu.Name -match "590" -and $reported -gt 0 -and $reported -le 4GB) {
        Write-Host "NOTE: AdapterRAM reported $reported bytes. That field is 32-bit and wraps at 4GB. An RX 590 is 8GB." -ForegroundColor Yellow
    }
}

$path = "HKLM:\SYSTEM\CurrentControlSet\Control\GraphicsDrivers"
$tdr = $null
try { $tdr = (Get-ItemProperty -Path $path -Name TdrDelay -ErrorAction SilentlyContinue).TdrDelay } catch {}
if (-not $tdr) {
    Write-Host "TdrDelay is not set. Windows will reset the GPU after about 2 seconds. Training will look like it randomly stopped." -ForegroundColor Yellow
} elseif ([int]$tdr -lt 30) {
    Write-Host "TdrDelay is $tdr seconds. Raise it to 60 before a long run." -ForegroundColor Yellow
} else {
    Write-Host "TdrDelay is $tdr seconds." -ForegroundColor Green
}

$cs = Get-CimInstance Win32_ComputerSystem
$page = [int64]$cs.TotalVirtualMemorySize - [int64]$cs.TotalPhysicalMemory
Write-Host ("Pagefile-ish commit headroom (KB, rough): {0}" -f $page)
if ($page -lt 16MB) {
    Write-Host "Pagefile looks small. Set a custom 32768 MB pagefile or training can die with a commit-charge error." -ForegroundColor Yellow
}

if ($Apply) {
    $isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $isAdmin) {
        Write-Host "Re-run from an elevated PowerShell to apply TdrDelay." -ForegroundColor Red
        exit 1
    }
    New-ItemProperty -Path $path -Name TdrDelay -PropertyType DWord -Value 60 -Force | Out-Null
    New-ItemProperty -Path $path -Name TdrDdiDelay -PropertyType DWord -Value 60 -Force | Out-Null
    Write-Host "TdrDelay set to 60. Reboot before training." -ForegroundColor Green
} else {
    Write-Host "No changes made. Pass -Apply from an elevated shell, or import Enable-LongGPUTimeout.reg, then reboot."
}
