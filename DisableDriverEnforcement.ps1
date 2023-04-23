#############
# FUNCTIONS
#############

Function Disable-SecureBoot($biospassword) {
    $bios = Get-WmiObject -Namespace root/hp/instrumentedBIOS -Class HP_BIOSSettingInterface
    if ($biospassword -eq "") {
        $returncode = $bios.setbiossetting("Secure Boot", "Disable", "")
    } else {
        $returncode = $bios.setbiossetting("Secure Boot", "Disable", "<utf-16/>$biospassword")
    }
    $returncodeout = $returncode.return
    return $returncodeout
}

#############
# PROCESS
#############

#Bitlocker Encryption
$BLDrive = Get-BitLockerVolume
if ($BLDrive.ProtectionStatus -eq "Off") {
    Write-Host "Bitlocker Already Disabled"
} else {
    Disable-BitLocker -MountPoint $BLDrive | out-null
    Write-Host "Bitlocker Disabled"
}

#Disable SecureBoot
if ((Disable-SecureBoot "bios_pw_1") -eq 0) {
    Write-Host "Secure Boot Disabled"
} else {
    if ((Disable-SecureBoot "bios_pw_2") -eq 0) {
        Write-Host "Secure Boot Disabled"
    } else {
        if ((Disable-SecureBoot "") -eq 0) {
            Write-Host "Secure Boot Disabled"
        } else {
            $HPBIOSSettings = Get-WmiObject -Namespace root\HP\InstrumentedBIOS -Class HP_BIOSSetting
            $SecureBootCurrent = ($HPBIOSSettings | Where-Object {$_.name -eq "secure boot"} | Select currentvalue)
            if ($SecureBootCurrent -eq "Disable") {
                Write-Host "Secure Boot Already Disabled"
            } else {
                Write-Host "Secure Boot Not Disabled (Likely Unknown BIOS Password)"
            }
        }
    }
}

#Create Scheduled Task to Disable Driver Signature Enforcement after Reboot
if (!(Test-Path C:\temp\temp)) {
    New-Item C:\temp\temp -ItemType directory | out-null
}
"bcdedit /set nointegritychecks on
Remove-Item -Recurse -Force C:\temp\temp
" | Out-File "C:\temp\temp\temp.ps1"

#Create Scheduled Task
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-executionpolicy bypass -File C:\temp\temp\temp.ps1"
$trigger = New-ScheduledTaskTrigger -AtLogon
$task = New-ScheduledTask -Action $action -Trigger $trigger 
$task.Triggers[0].EndBoundary = "{0:yyyy-MM-dd'T'HH:mm:ss}" -f (get-date).addminutes(15)
$task.Settings = (New-ScheduledTaskSettingsSet -DeleteExpiredTaskAfter (New-TimeSpan -Seconds 0) -AllowStartIfOnBatteries)
Register-ScheduledTask -TaskName "Disable Driver Signature" -InputObject $task -User "NT AUTHORITY\SYSTEM" | out-null
Write-Host "Task Created"

#Wait for Bitlocker to Decrypt and then restart
While (1) {
    Write-Host "Bitlocker Decryption In Process..."
    if ((Get-BitLockerVolume).VolumeStatus -eq "FullyDecrypted") {
        shutdown /r /t 0
    }
    Start-Sleep -Seconds 30
}